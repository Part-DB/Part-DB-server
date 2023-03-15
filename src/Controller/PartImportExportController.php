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

namespace App\Controller;

use App\Entity\Parts\Part;
use App\Form\AdminPages\ImportType;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\Parts\PartsTableActionHandler;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use UnexpectedValueException;

class PartImportExportController extends AbstractController
{
    private PartsTableActionHandler $partsTableActionHandler;
    private EntityImporter $entityImporter;
    private EventCommentHelper $commentHelper;

    public function __construct(PartsTableActionHandler $partsTableActionHandler,
        EntityImporter $entityImporter, EventCommentHelper $commentHelper)
    {
        $this->partsTableActionHandler = $partsTableActionHandler;
        $this->entityImporter = $entityImporter;
        $this->commentHelper = $commentHelper;
    }

    /**
     * @Route("/parts/import", name="parts_import")
     * @param  Request  $request
     * @return Response
     */
    public function importParts(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@parts.import');

        $import_form = $this->createForm(ImportType::class, ['entity_class' => Part::class]);
        $import_form->handleRequest($request);

        if ($import_form->isSubmitted() && $import_form->isValid()) {
            /** @var UploadedFile $file */
            $file = $import_form['file']->getData();
            $data = $import_form->getData();

            if ($data['format'] === 'auto') {
                $format = $this->entityImporter->determineFormat($file->getClientOriginalExtension());
                if (null === $format) {
                    $this->addFlash('error', 'parts.import.flash.error.unknown_format');
                    goto ret;
                }
            } else {
                $format = $data['format'];
            }

            $options = [
                'create_unknown_datastructures' => $data['create_unknown_datastructures'],
                'path_delimiter' => $data['path_delimiter'],
                'format' => $format,
                'part_category' => $data['part_category'],
                'class' => Part::class,
                'csv_delimiter' => $data['csv_delimiter'],
            ];

            $this->commentHelper->setMessage('Import '.$file->getClientOriginalName());

            $entities = [];

            try {
                $errors = $this->entityImporter->importFileAndPersistToDB($file, $options, $entities);
            } catch (UnexpectedValueException $e) {
                $this->addFlash('error', 'parts.import.flash.error.invalid_file');
                goto ret;
            }

            if (!isset($errors) || $errors) {
                $this->addFlash('error', 'parts.import.flash.error');
            } else {
                $this->addFlash('success', 'parts.import.flash.success');
            }
        }


        ret:
        return $this->renderForm('parts/import/parts_import.html.twig', [
            'import_form' => $import_form,
            'imported_entities' => $entities ?? [],
            'import_errors' => $errors ?? [],
        ]);
    }

    /**
     * @Route("/parts/export", name="parts_export", methods={"GET"})
     * @return Response
     */
    public function exportParts(Request $request, EntityExporter $entityExporter): Response
    {
        $ids = $request->query->get('ids', '');
        $parts = $this->partsTableActionHandler->idStringToArray($ids);

        if (empty($parts)) {
            throw new \RuntimeException('No parts found!');
        }

        //Ensure that we have access to the parts
        foreach ($parts as $part) {
            $this->denyAccessUnlessGranted('read', $part);
        }

        return $entityExporter->exportEntityFromRequest($parts, $request);
    }
}