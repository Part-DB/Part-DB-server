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

namespace App\Controller\AdminPages;

use App\Entity\Attachments\AttachmentType;

use App\Entity\Parts\Storelocation;
use App\Form\AdminPages\BaseEntityAdminForm;
use App\Form\AdminPages\StorelocationAdminForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/store_location")
 * @package App\Controller
 */
class StorelocationController extends BaseAdminController
{

    protected $entity_class = Storelocation::class;
    protected $twig_template = 'AdminPages/StorelocationAdmin.html.twig';
    protected $form_class = StorelocationAdminForm::class;
    protected $route_base = "store_location";

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="store_location_edit")
     * @Route("/{id}/", requirements={"id"="\d+"})
     */
    public function edit(Storelocation $entity, Request $request, EntityManagerInterface $em)
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="store_location_new")
     * @Route("/")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        return $this->_new($request, $em, $importer);
    }

    /**
     * @Route("/{id}", name="store_location_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Storelocation $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/export", name="store_location_export_all")
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
     * @Route("/{id}/export", name="store_location_export")
     * @param Request $request
     * @param AttachmentType $entity
     */
    public function exportEntity(Storelocation $entity, EntityExporter $exporter, Request $request)
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }

}