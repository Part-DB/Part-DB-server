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

use App\Settings\AppSettings;
use Jbtronics\SettingsBundle\Form\SettingsFormFactoryInterface;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SettingsController extends AbstractController
{
    public function __construct(private readonly SettingsManagerInterface $settingsManager, private readonly SettingsFormFactoryInterface $settingsFormFactory)
    {}

    #[Route("/settings", name: "system_settings")]
    public function systemSettings(Request $request, TagAwareCacheInterface $cache): Response
    {
        //Create a clone of the settings object
        $settings = $this->settingsManager->createTemporaryCopy(AppSettings::class);

        //Create a form builder for the settings object
        $builder = $this->settingsFormFactory->createSettingsFormBuilder($settings);

        //Add a submit button to the form
        $builder->add('submit', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, ['label' => 'save']);

        //Create the form
        $form = $builder->getForm();
        $form->handleRequest($request);

        //If the form was submitted and is valid, save the settings
        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsManager->mergeTemporaryCopy($settings);
            $this->settingsManager->save($settings);

            //It might be possible, that the tree settings have changed, so clear the cache
            $cache->invalidateTags(['tree_treeview', 'sidebar_tree_update']);
        }




        //Render the form
        return $this->render('settings/settings.html.twig', [
            'form' => $form
        ]);
    }
}