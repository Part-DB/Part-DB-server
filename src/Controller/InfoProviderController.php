<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\Controller;

use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Form\InfoProviderSystem\PartSearchType;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

#[Route('/tools/info_providers')]
class InfoProviderController extends  AbstractController
{

    public function __construct(private readonly ProviderRegistry $providerRegistry,
        private readonly PartInfoRetriever $infoRetriever,
        private readonly EntityManagerInterface $em)
    {

    }

    #[Route('/providers', name: 'info_providers_list')]
    public function listProviders(): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        return $this->render('info_providers/providers_list/providers_list.html.twig', [
            'active_providers' => $this->providerRegistry->getActiveProviders(),
            'disabled_providers' => $this->providerRegistry->getDisabledProviders(),
        ]);
    }

    /**
     * Looks for parts in the local database that match the results from the info provider, so the user doesn't
     * accidentally add a duplicate
     *
     * @param array $partsList Array of Arrays. Outer Array contains one entry per search result from the info Provider
     * Inner array contains one entry "dto" for the dto from the info provider
     * and one entry "localPart" where the local Part will be put if it exists
     * Form: [["dto" => dto from provider, "localPart" => null]["dto" => dto from provider, "localPart" => null]]
     * This function might modify the original array, not sure
     * @return array Same format as the input array, but for parts that exist locally null will be replaced by a Part
     */
    private function matchResultsToKnownParts(array $partsList): array
    {
        //we need a manufacturer object to look for a manufacturer
        $manufacturerQb = $this->em->getRepository(Manufacturer::class)->createQueryBuilder("manufacturer");
        $manufacturerQb->where($manufacturerQb->expr()->like("LOWER(manufacturer.name)", "LOWER(:manufacturer_name)"));


        //check if both manufacturer and Manufacturer part namber matches. If so, it must be the same part
        //use LOWER to make the search independent of case
        $mpnQb = $this->em->getRepository(Part::class)->createQueryBuilder("part");
        $mpnQb->where($mpnQb->expr()->like("LOWER(part.manufacturer_product_number)", "LOWER(:mpn)"));
        $mpnQb->andWhere($mpnQb->expr()->eq("part.manufacturer", ":manufacturer"));

        foreach ($partsList as $index => $part) {
            $manufacturerQb->setParameter("manufacturer_name", $part["dto"]->manufacturer);
            $manufacturers = $manufacturerQb->getQuery()->getResult();
            if(!$manufacturers) {
                continue;
            }

            $mpnQb->setParameter("manufacturer", $manufacturers);
            $mpnQb->setParameter("mpn", $part["dto"]->mpn);
            $localParts = $mpnQb->getQuery()->getResult();
            if(!$localParts) {
                continue;
            }
            //We only use the first matching part. If a user already has duplicate parts they will get a random one
            $partsList[$index]["localPart"] = $localParts[0];
        }
        return $partsList;
    }

    #[Route('/search', name: 'info_providers_search')]
    #[Route('/update/{target}', name: 'info_providers_update_part_search')]
    public function search(Request $request, #[MapEntity(id: 'target')] ?Part $update_target, LoggerInterface $exceptionLogger): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        $form = $this->createForm(PartSearchType::class);
        $form->handleRequest($request);

        $results = null;

        //When we are updating a part, use its name as keyword, to make searching easier
        //However we can only do this, if the form was not submitted yet
        if ($update_target !== null && !$form->isSubmitted()) {
            $form->get('keyword')->setData($update_target->getName());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $keyword = $form->get('keyword')->getData();
            $providers = $form->get('providers')->getData();

            try {
                $results = $this->infoRetriever->searchByKeyword(keyword: $keyword, providers: $providers);
            } catch (ClientException $e) {
                $this->addFlash('error', t('info_providers.search.error.client_exception'));
                $this->addFlash('error',$e->getMessage());
                //Log the exception
                $exceptionLogger->error('Error during info provider search: ' . $e->getMessage(), ['exception' => $e]);
            }
            // modify the array to an array of arrays that has a field for a matching local Part
            // the advantage to use that format even when we don't look for local parts is that we
            // always work with the same interface
            $results = array_map(function ($result) {return ["dto" => $result,"localPart" => null];}, $results);
            if(!$update_target) {
                $results = $this->matchResultsToKnownParts($results);
            }
        }

        return $this->render('info_providers/search/part_search.html.twig', [
            'form' => $form,
            'results' => $results,
            'update_target' => $update_target
        ]);
    }
}