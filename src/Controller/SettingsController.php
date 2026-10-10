<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\Providers\AIDocumentProvider;
use App\Services\InfoProviderSystem\Providers\AIWebProvider;
use App\Services\InfoProviderSystem\Providers\GenericWebProvider;
use App\Settings\AppSettings;
use Jbtronics\SettingsBundle\Form\SettingsFormFactoryInterface;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function Symfony\Component\Translation\t;

class SettingsController extends AbstractController
{
    /**
     * The features, which depend on certain info providers. These are highlighted in the provider settings list.
     * Maps provider keys to a list of features (with translation key of the label and the icon).
     */
    private const PROVIDER_FEATURES = [
        GenericWebProvider::PROVIDER_KEY => [['label' => 'info_providers.from_url.title', 'icon' => 'fa-book-atlas']],
        AIWebProvider::PROVIDER_KEY => [['label' => 'info_providers.from_url.title', 'icon' => 'fa-book-atlas']],
        AIDocumentProvider::PROVIDER_KEY => [['label' => 'info_providers.from_file.title', 'icon' => 'fa-file-lines']],
    ];

    public function __construct(private readonly SettingsManagerInterface $settingsManager, private readonly SettingsFormFactoryInterface $settingsFormFactory)
    {}

    #[Route("/settings", name: "system_settings")]
    public function systemSettings(Request $request, TagAwareCacheInterface $cache, ProviderRegistry $providerRegistry): Response
    {
        $this->denyAccessUnlessGranted('@config.change_system_settings');
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        //Create a clone of the settings object
        $settings = $this->settingsManager->createTemporaryCopy(AppSettings::class);

        //Create a form builder for the settings object
        $builder = $this->settingsFormFactory->createSettingsFormBuilder($settings, formOptions: [
            'warn_on_unsaved_changes' => true,
        ]);

        //Add a submit button to the form
        $builder->add('submit', SubmitType::class, ['label' => 'save']);

        //Create the form
        $form = $builder->getForm();
        $form->handleRequest($request);

        //If the form was submitted and is valid, save the settings
        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsManager->mergeTemporaryCopy($settings);
            $this->settingsManager->save($settings);

            //It might be possible, that the tree settings have changed, so clear the cache
            $cache->invalidateTags(['tree_tools', 'tree_treeview', 'sidebar_tree_update', 'synonyms']);

            $this->addFlash('success', t('settings.flash.saved'));
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', t('settings.flash.invalid'));
        }

        //Render the form
        return $this->render('settings/settings.html.twig', [
            'form' => $form,
            //The info provider specific settings are edited on their own pages, so we only link them
            'providers_with_settings' => array_filter($providerRegistry->getProviders(),
                static fn($provider) => $provider->getProviderInfo()->settingsClass !== null),
            'provider_features' => self::PROVIDER_FEATURES,
        ]);
    }
}
