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

use App\Entity\Attachments\LabelAttachment;
use App\Entity\LabelSystem\LabelProfile;
use App\Form\AdminPages\LabelProfileAdminForm;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\Trees\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
/**
 * @see \App\Tests\Controller\AdminPages\LabelProfileControllerTest
 */
#[Route(path: '/label_profile')]
class LabelProfileController extends BaseAdminController
{
    protected string $entity_class = LabelProfile::class;
    protected string $twig_template = 'admin/label_profile_admin.html.twig';
    protected string $form_class = LabelProfileAdminForm::class;
    protected string $route_base = 'label_profile';
    protected string $attachment_class = LabelAttachment::class;
    //Just a placeholder
    protected ?string $parameter_class = null;

    #[Route(path: '/{id}', name: 'label_profile_delete', methods: ['DELETE'])]
    public function delete(Request $request, LabelProfile $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    #[Route(path: '/{id}/edit/{timestamp}', name: 'label_profile_edit', requirements: ['id' => '\d+'])]
    #[Route(path: '/{id}', requirements: ['id' => '\d+'])]
    public function edit(LabelProfile $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        return $this->_edit($entity, $request, $em, $timestamp);
    }

    #[Route(path: '/new', name: 'label_profile_new')]
    #[Route(path: '/{id}/clone', name: 'label_profile_clone')]
    #[Route(path: '/')]
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?LabelProfile $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    #[Route(path: '/export', name: 'label_profile_export_all')]
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    #[Route(path: '/{id}/export', name: 'label_profile_export')]
    public function exportEntity(LabelProfile $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
