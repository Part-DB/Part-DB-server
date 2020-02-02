<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Controller\AdminPages;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Parts\MeasurementUnit;
use App\Form\AdminPages\MeasurementUnitAdminForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/measurement_unit")
 */
class MeasurementUnitController extends BaseAdminController
{
    protected $entity_class = MeasurementUnit::class;
    protected $twig_template = 'AdminPages/MeasurementUnitAdmin.html.twig';
    protected $form_class = MeasurementUnitAdminForm::class;
    protected $route_base = 'measurement_unit';
    protected $attachment_class = MeasurementUnitAttachment::class;

    /**
     * @Route("/{id}", name="measurement_unit_delete", methods={"DELETE"})
     * @param  Request  $request
     * @param  MeasurementUnit  $entity
     * @param  StructuralElementRecursionHelper  $recursionHelper
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function delete(Request $request, MeasurementUnit $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="measurement_unit_edit")
     * @Route("/{id}", requirements={"id"="\d+"})
     * @param  MeasurementUnit  $entity
     * @param  Request  $request
     * @param  EntityManagerInterface  $em
     * @return Response
     */
    public function edit(MeasurementUnit $entity, Request $request, EntityManagerInterface $em)
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="measurement_unit_new")
     * @Route("/")
     *
     * @param  Request  $request
     * @param  EntityManagerInterface  $em
     * @param  EntityImporter  $importer
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer): Response
    {
        return $this->_new($request, $em, $importer);
    }

    /**
     * @Route("/export", name="measurement_unit_export_all")
     *
     * @param  EntityManagerInterface  $em
     * @param  EntityExporter  $exporter
     * @param  Request  $request
     * @return Response
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="measurement_unit_export")
     *
     * @param  AttachmentType  $entity
     * @param  EntityExporter  $exporter
     * @param  Request  $request
     * @return Response
     */
    public function exportEntity(AttachmentType $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
