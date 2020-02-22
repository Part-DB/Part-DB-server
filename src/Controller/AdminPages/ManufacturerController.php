<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Parts\Manufacturer;
use App\Form\AdminPages\CompanyForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/manufacturer")
 */
class ManufacturerController extends BaseAdminController
{
    protected $entity_class = Manufacturer::class;
    protected $twig_template = 'AdminPages/ManufacturerAdmin.html.twig';
    protected $form_class = CompanyForm::class;
    protected $route_base = 'manufacturer';
    protected $attachment_class = ManufacturerAttachment::class;

    /**
     * @Route("/{id}", name="manufacturer_delete", methods={"DELETE"})
     * @param  Request  $request
     * @param  Manufacturer  $entity
     * @param  StructuralElementRecursionHelper  $recursionHelper
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function delete(Request $request, Manufacturer $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="manufacturer_edit")
     * @Route("/{id}", requirements={"id"="\d+"})
     * @param  Manufacturer  $entity
     * @param  Request  $request
     * @param  EntityManagerInterface  $em
     * @return Response
     */
    public function edit(Manufacturer $entity, Request $request, EntityManagerInterface $em)
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="manufacturer_new")
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
     * @Route("/export", name="manufacturer_export_all")
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
     * @Route("/{id}/export", name="manufacturer_export")
     *
     * @param  Manufacturer  $entity
     *
     * @param  EntityExporter  $exporter
     * @param  Request  $request
     * @return Response
     */
    public function exportEntity(Manufacturer $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
