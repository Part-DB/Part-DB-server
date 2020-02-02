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

use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\PriceInformations\Currency;
use App\Form\AdminPages\CurrencyAdminForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/currency")
 *
 * Class CurrencyController
 */
class CurrencyController extends BaseAdminController
{
    protected $entity_class = Currency::class;
    protected $twig_template = 'AdminPages/CurrencyAdmin.html.twig';
    protected $form_class = CurrencyAdminForm::class;
    protected $route_base = 'currency';
    protected $attachment_class = CurrencyAttachment::class;

    /**
     * @Route("/{id}", name="currency_delete", methods={"DELETE"})
     *
     * @param  Request  $request
     * @param  Currency  $entity
     * @param  StructuralElementRecursionHelper  $recursionHelper
     * @return RedirectResponse
     */
    public function delete(Request $request, Currency $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="currency_edit")
     * @Route("/{id}", requirements={"id"="\d+"})
     *
     * @param  Currency  $entity
     * @param  Request  $request
     * @param  EntityManagerInterface  $em
     * @return Response
     */
    public function edit(Currency $entity, Request $request, EntityManagerInterface $em): Response
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="currency_new")
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
     * @Route("/export", name="currency_export_all")
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
     * @Route("/{id}/export", name="currency_export")
     *
     * @param  Currency  $entity
     * @param  EntityExporter  $exporter
     * @param  Request  $request
     * @return Response
     */
    public function exportEntity(Currency $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }
}
