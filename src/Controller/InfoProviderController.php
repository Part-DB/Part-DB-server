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
use App\Exceptions\OAuthReconnectRequiredException;
use App\Form\InfoProviderSystem\PartSearchType;
use App\Services\InfoProviderSystem\ExistingPartFinder;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\Providers\GenericWebProvider;
use App\Settings\AppSettings;
use App\Settings\InfoProviderSystem\InfoProviderGeneralSettings;
use Doctrine\ORM\EntityManagerInterface;
use Jbtronics\SettingsBundle\Form\SettingsFormFactoryInterface;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

use function Symfony\Component\Translation\t;

#[Route('/tools/info_providers')]
class InfoProviderController extends  AbstractController
{

    public function __construct(private readonly ProviderRegistry $providerRegistry,
        private readonly PartInfoRetriever $infoRetriever,
        private readonly ExistingPartFinder $existingPartFinder,
        private readonly SettingsManagerInterface $settingsManager,
        private readonly SettingsFormFactoryInterface $settingsFormFactory
    )
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

    #[Route('/provider/{provider}/settings', name: 'info_providers_provider_settings')]
    public function providerSettings(string $provider, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@config.change_system_settings');
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        $providerInstance = $this->providerRegistry->getProviderByKey($provider);
        $settingsClass = $providerInstance->getProviderInfo()['settings_class'] ?? throw new \LogicException('Provider ' . $provider . ' does not have a settings class defined');

        //Create a clone of the settings object
        $settings = $this->settingsManager->createTemporaryCopy($settingsClass);

        //Create a form builder for the settings object
        $builder = $this->settingsFormFactory->createSettingsFormBuilder($settings);

        //Add a submit button to the form
        $builder->add('submit', SubmitType::class, ['label' => 'save']);

        //Create the form
        $form = $builder->getForm();
        $form->handleRequest($request);

        //If the form was submitted and is valid, save the settings
        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsManager->mergeTemporaryCopy($settings);
            $this->settingsManager->save($settings);

            $this->addFlash('success', t('settings.flash.saved'));
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', t('settings.flash.invalid'));
        }

        //Render the form
        return $this->render('info_providers/settings/provider_settings.html.twig', [
            'form' => $form,
            'info_provider_key' => $provider,
            'info_provider_info' => $providerInstance->getProviderInfo(),
        ]);
    }

    #[Route('/search', name: 'info_providers_search')]
    #[Route('/update/{target}', name: 'info_providers_update_part_search')]
    public function search(Request $request, #[MapEntity(id: 'target')] ?Part $update_target, LoggerInterface $exceptionLogger, InfoProviderGeneralSettings $infoProviderSettings): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        $form = $this->createForm(PartSearchType::class);
        $form->handleRequest($request);

        $results = null;

        //When we are updating a part, use its name as keyword, to make searching easier
        //However we can only do this, if the form was not submitted yet
        if ($update_target !== null && !$form->isSubmitted()) {
            //Use the provider reference if available, otherwise use the manufacturer product number
            $keyword = $update_target->getProviderReference()->getProviderId() ?? $update_target->getManufacturerProductNumber();
            //Or the name if both are not available
            if ($keyword === "") {
                $keyword = $update_target->getName();
            }

            $form->get('keyword')->setData($keyword);

            //If we are updating a part, which already has a provider, preselect that provider in the form
            if ($update_target->getProviderReference()->getProviderKey() !== null) {
                try {
                    $form->get('providers')->setData([$this->providerRegistry->getProviderByKey($update_target->getProviderReference()->getProviderKey())]);
                } catch (\InvalidArgumentException $e) {
                    //If the provider is not found, just ignore it
                }
            }
        }

        //If the providers form is still empty, use our default value from the settings
        if (count($form->get('providers')->getData() ?? []) === 0) {
            $default_providers = $infoProviderSettings->defaultSearchProviders;
            $provider_objects = [];
            foreach ($default_providers as $provider_key) {
                try {
                    $tmp = $this->providerRegistry->getProviderByKey($provider_key);
                    if ($tmp->isActive()) {
                        $provider_objects[] = $tmp;
                    }
                } catch (\InvalidArgumentException $e) {
                    //If the provider is not found, just ignore it
                }
            }
            $form->get('providers')->setData($provider_objects);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $keyword = $form->get('keyword')->getData();
            $providers = $form->get('providers')->getData();

            $dtos = [];

            try {
                $dtos = $this->infoRetriever->searchByKeyword(keyword: $keyword, providers: $providers);
            } catch (ClientException $e) {
                $this->addFlash('error', t('info_providers.search.error.client_exception'));
                $this->addFlash('error',$e->getMessage());
                //Log the exception
                $exceptionLogger->error('Error during info provider search: ' . $e->getMessage(), ['exception' => $e]);
            } catch (OAuthReconnectRequiredException $e) {
                $this->addFlash('error', t('info_providers.search.error.oauth_reconnect', ['%provider%' => $e->getProviderName()]));
            } catch (TransportException $e) {
                $this->addFlash('error', t('info_providers.search.error.transport_exception'));
                $exceptionLogger->error('Transport error during info provider search: ' . $e->getMessage(), ['exception' => $e]);
            } catch (\RuntimeException $e) {
                $this->addFlash('error', t('info_providers.search.error.general_exception', ['%type%' => (new \ReflectionClass($e))->getShortName()]));
                //Log the exception
                $exceptionLogger->error('Error during info provider search: ' . $e->getMessage(), ['exception' => $e]);
            }


            // modify the array to an array of arrays that has a field for a matching local Part
            // the advantage to use that format even when we don't look for local parts is that we
            // always work with the same interface
            $results = array_map(function ($result) {return ['dto' => $result, 'localPart' => null];}, $dtos);
            if(!$update_target) {
                foreach ($results as $index => $result) {
                    $results[$index]['localPart'] = $this->existingPartFinder->findFirstExisting($result['dto']);
                }
            }
        }

        return $this->render('info_providers/search/part_search.html.twig', [
            'form' => $form,
            'results' => $results,
            'update_target' => $update_target
        ]);
    }

    #[Route('/from_url', name: 'info_providers_from_url')]
    public function fromURL(Request $request, GenericWebProvider $provider): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        if (!$provider->isActive()) {
            $this->addFlash('error', "Generic Web Provider is not active. Please enable it in the provider settings.");
            return $this->redirectToRoute('info_providers_list');
        }

        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('url', UrlType::class, [
            'label' => 'info_providers.from_url.url.label',
            'required' => true,
        ]);
        $formBuilder->add('submit', SubmitType::class, [
            'label' => 'info_providers.search.submit',
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        $partDetail = null;
        if ($form->isSubmitted() && $form->isValid()) {
            //Try to retrieve the part detail from the given URL
            $url = $form->get('url')->getData();
            try {
                $searchResult = $this->infoRetriever->searchByKeyword(
                    keyword: $url,
                    providers: [$provider]
                );

                if (count($searchResult) === 0) {
                    $this->addFlash('warning', t('info_providers.from_url.no_part_found'));
                } else {
                    $searchResult = $searchResult[0];
                    //Redirect to the part creation page with the found part detail
                    return $this->redirectToRoute('info_providers_create_part', [
                        'providerKey' => $searchResult->provider_key,
                        'providerId' => urlencode($searchResult->provider_id),
                    ]);
                }
            } catch (ExceptionInterface $e) {
                $this->addFlash('error', t('info_providers.search.error.general_exception', ['%type%' => (new \ReflectionClass($e))->getShortName()]));
            }
        }

        return $this->render('info_providers/from_url/from_url.html.twig', [
            'form' => $form,
            'partDetail' => $partDetail,
        ]);

    }
}
