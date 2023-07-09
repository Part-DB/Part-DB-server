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

use App\Form\InfoProviderSystem\PartSearchType;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tools/info_providers')]
class InfoProviderController extends  AbstractController
{

    #[Route('/providers', name: 'info_providers_list')]
    public function listProviders(ProviderRegistry $providerRegistry): Response
    {
        return $this->render('info_providers/providers_list/providers_list.html.twig', [
            'active_providers' => $providerRegistry->getActiveProviders(),
            'disabled_providers' => $providerRegistry->getDisabledProviders(),
        ]);
    }

    #[Route('/search', name: 'info_providers_search')]
    public function search(Request $request, PartInfoRetriever $infoRetriever): Response
    {
        $form = $this->createForm(PartSearchType::class);
        $form->handleRequest($request);

        $results = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $keyword = $form->get('keyword')->getData();
            $providers = $form->get('providers')->getData();

            $results = $infoRetriever->searchByKeyword(keyword: $keyword, providers: $providers);
        }

        return $this->render('info_providers/search/part_search.html.twig', [
            'form' => $form,
            'results' => $results,
        ]);
    }
}