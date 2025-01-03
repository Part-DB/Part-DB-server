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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Parameters\FootprintParameter;
use App\Entity\Parts\Footprint;
use App\Form\AdminPages\FootprintAdminForm;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\Trees\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see \App\Tests\Controller\AdminPages\FootprintControllerTest
 */
#[Route(path: '/footprint')]
class FootprintController extends BaseAdminController
{
    protected string $entity_class = Footprint::class;
    protected string $twig_template = 'admin/footprint_admin.html.twig';
    protected string $form_class = FootprintAdminForm::class;
    protected string $route_base = 'footprint';
    protected string $attachment_class = FootprintAttachment::class;
    protected ?string $parameter_class = FootprintParameter::class;

    #[Route(path: '/{id}', name: 'footprint_delete', methods: ['DELETE'])]
    public function delete(Request $request, Footprint $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    #[Route(path: '/{id}/edit/{timestamp}', name: 'footprint_edit', requirements: ['id' => '\d+'])]
    #[Route(path: '/{id}', requirements: ['id' => '\d+'])]
    public function edit(Footprint $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        return $this->_edit($entity, $request, $em, $timestamp);
    }

    #[Route(path: '/new', name: 'footprint_new')]
    #[Route(path: '/{id}/clone', name: 'footprint_clone')]
    #[Route(path: '/')]
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?Footprint $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    #[Route(path: '/export', name: 'footprint_export_all')]
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    #[Route(path: '/{id}/export', name: 'footprint_export')]
    public function exportEntity(AttachmentType $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
