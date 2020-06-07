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

namespace App\Controller;

use App\Controller\AdminPages\BaseAdminController;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parameters\GroupParameter;
use App\Entity\UserSystem\Group;
use App\Form\AdminPages\GroupAdminForm;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    protected $attachment_class = GroupAttachment::class;
    protected $parameter_class = GroupParameter::class;

    /**
     * @Route("/{id}/edit/{timestamp}", requirements={"id"="\d+"}, name="group_edit")
     * @Route("/{id}/", requirements={"id"="\d+"})
     *
     * @return Response
     */
    public function edit(Group $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        return $this->_edit($entity, $request, $em, $timestamp);
    }

    /**
     * @Route("/new", name="group_new")
     * @Route("/{id}/clone", name="group_clone")
     * @Route("/")
     *
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?Group $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    /**
     * @Route("/{id}", name="group_delete", methods={"DELETE"})
     *
     * @return RedirectResponse
     */
    public function delete(Request $request, Group $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/export", name="group_export_all")
     *
     * @return Response
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="group_export")
     *
     * @return Response
     */
    public function exportEntity(Group $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }

    public function deleteCheck(AbstractNamedDBElement $entity): bool
    {
        if ($entity instanceof Group) {
            if ($entity->getUsers()->count() > 0) {
                $this->addFlash('error', 'entity.delete.must_not_contain_users');
                return false;
            }
        }

        return true;
    }
}
