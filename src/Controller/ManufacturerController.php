<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Controller;


use App\Entity\AttachmentType;

use App\Entity\Manufacturer;
use App\Entity\Supplier;
use App\Form\BaseEntityAdminForm;
use App\Form\CompanyForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/manufacturer")
 * @package App\Controller
 */
class ManufacturerController extends BaseAdminController
{

    protected $entity_class = Manufacturer::class;
    protected $twig_template = 'AdminPages/ManufacturerAdmin.html.twig';
    protected $form_class = CompanyForm::class;
    protected $route_base = 'manufacturer';

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="manufacturer_edit")
     * @Route("/{id}/", requirements={"id"="\d+"})
     */
    public function edit(Manufacturer $entity, Request $request, EntityManagerInterface $em)
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="manufacturer_new")
     * @Route("/")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        return $this->_new($request, $em, $importer);
    }

    /**
     * @Route("/{id}", name="manufacturer_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Manufacturer $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/export", name="manufacturer_export_all")
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request)
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="manufacturer_export")
     * @param Request $request
     * @param Supplier $entity
     */
    public function exportEntity(Manufacturer $entity, EntityExporter $exporter, Request $request)
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }

}