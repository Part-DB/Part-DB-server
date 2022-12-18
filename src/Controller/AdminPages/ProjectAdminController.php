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

use App\Entity\Attachments\ProjectAttachment;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parameters\ProjectParameter;
use App\Form\AdminPages\BaseEntityAdminForm;
use App\Form\AdminPages\ProjectAdminForm;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\Trees\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/device")
 */
class ProjectAdminController extends BaseAdminController
{
    protected $entity_class = Project::class;
    protected $twig_template = 'AdminPages/DeviceAdmin.html.twig';
    protected $form_class = ProjectAdminForm::class;
    protected $route_base = 'device';
    protected $attachment_class = ProjectAttachment::class;
    protected $parameter_class = ProjectParameter::class;

    /**
     * @Route("/{id}", name="device_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Project $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/{id}/edit/{timestamp}", requirements={"id"="\d+"}, name="device_edit")
     * @Route("/{id}", requirements={"id"="\d+"})
     */
    public function edit(Project $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        return $this->_edit($entity, $request, $em, $timestamp);
    }

    /**
     * @Route("/new", name="device_new")
     * @Route("/{id}/clone", name="device_clone")
     * @Route("/")
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?Project $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    /**
     * @Route("/export", name="device_export_all")
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="device_export")
     */
    public function exportEntity(Project $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
