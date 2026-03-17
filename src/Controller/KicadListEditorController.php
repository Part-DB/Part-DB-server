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

use App\Form\Settings\KicadListEditorType;
use App\Services\EDA\KicadListFileManager;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

final class KicadListEditorController extends AbstractController
{
    #[Route('/settings/misc/kicad-lists', name: 'settings_kicad_lists')]
    public function __invoke(Request $request, KicadListFileManager $fileManager): Response
    {
        $this->denyAccessUnlessGranted('@config.change_system_settings');

        $form = $this->createForm(KicadListEditorType::class, [
            'footprints' => $fileManager->getFootprintsContent(),
            'symbols' => $fileManager->getSymbolsContent(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $fileManager->save($data['footprints'], $data['symbols']);
                $this->addFlash('success', t('settings.flash.saved'));

                return $this->redirectToRoute('settings_kicad_lists');
            } catch (RuntimeException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', t('settings.flash.invalid'));
        }

        return $this->render('settings/kicad_list_editor.html.twig', [
            'form' => $form,
        ]);
    }
}
