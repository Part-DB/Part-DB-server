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

use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Parts\Category;
use App\Form\AdminPages\CategoryAdminForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/category")
 */
class CategoryController extends BaseAdminController
{
    protected $entity_class = Category::class;
    protected $twig_template = 'AdminPages/CategoryAdmin.html.twig';
    protected $form_class = CategoryAdminForm::class;
    protected $route_base = 'category';
    protected $attachment_class = CategoryAttachment::class;

    /**
     * @Route("/{id}", name="category_delete", methods={"DELETE"})
     *
     * @return RedirectResponse
     */
    public function delete(Request $request, Category $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="category_edit")
     * @Route("/{id}", requirements={"id"="\d+"})
     *
     * @return Response
     */
    public function edit(Category $entity, Request $request, EntityManagerInterface $em)
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="category_new")
     * @Route("/")
     *
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        return $this->_new($request, $em, $importer);
    }

    /**
     * @Route("/export", name="category_export_all")
     *
     * @return Response
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request)
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="category_export")
     *
     * @return Response
     */
    public function exportEntity(Category $entity, EntityExporter $exporter, Request $request)
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
