<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Controller\AdminPages;

use App\Entity\Attachments\StorageLocationAttachment;
use App\Entity\Parameters\StorageLocationParameter;
use App\Entity\Parts\StorageLocation;
use App\Form\AdminPages\StorelocationAdminForm;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\Trees\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see \App\Tests\Controller\AdminPages\StorelocationControllerTest
 */
#[Route(path: '/store_location')]
class StorageLocationController extends BaseAdminController
{
    protected string $entity_class = StorageLocation::class;
    protected string $twig_template = 'admin/storelocation_admin.html.twig';
    protected string $form_class = StorelocationAdminForm::class;
    protected string $route_base = 'store_location';
    protected string $attachment_class = StorageLocationAttachment::class;
    protected ?string $parameter_class = StorageLocationParameter::class;

    #[Route(path: '/{id}', name: 'store_location_delete', methods: ['DELETE'])]
    public function delete(Request $request, StorageLocation $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    #[Route(path: '/{id}/edit/{timestamp}', name: 'store_location_edit', requirements: ['id' => '\d+'])]
    #[Route(path: '/{id}', requirements: ['id' => '\d+'])]
    public function edit(StorageLocation $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        return $this->_edit($entity, $request, $em, $timestamp);
    }

    #[Route(path: '/new', name: 'store_location_new')]
    #[Route(path: '/{id}/clone', name: 'store_location_clone')]
    #[Route(path: '/')]
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?StorageLocation $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    #[Route(path: '/export', name: 'store_location_export_all')]
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    #[Route(path: '/{id}/export', name: 'store_location_export')]
    public function exportEntity(StorageLocation $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
