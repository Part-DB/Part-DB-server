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


use App\Controller\AdminPages\BaseAdminController;
use App\Entity\UserSystem\Group;
use App\Form\AdminPages\GroupAdminForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/group")
 */
class GroupController extends BaseAdminController
{
    protected $entity_class = Group::class;
    protected $twig_template = 'AdminPages/GroupAdmin.html.twig';
    protected $form_class = GroupAdminForm::class;
    protected $route_base = 'group';

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="group_edit")
     * @Route("/{id}/", requirements={"id"="\d+"})
     * @param Group $entity
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function edit(Group $entity, Request $request, EntityManagerInterface $em)
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="group_new")
     * @Route("/")
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param EntityImporter $importer
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        return $this->_new($request, $em, $importer);
    }

    /**
     * @Route("/{id}", name="group_delete", methods={"DELETE"})
     * @param Request $request
     * @param Group $entity
     * @param StructuralElementRecursionHelper $recursionHelper
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function delete(Request $request, Group $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/export", name="group_export_all")
     * @param EntityManagerInterface $em
     * @param EntityExporter $exporter
     * @param Request $request
     * @return Response
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request)
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="group_export")
     * @param Group $entity
     * @param EntityExporter $exporter
     * @param Request $request
     * @return Response
     */
    public function exportEntity(Group $entity, EntityExporter $exporter, Request $request)
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}