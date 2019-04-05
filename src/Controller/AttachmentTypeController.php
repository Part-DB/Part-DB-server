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
use App\Form\BaseEntityAdminForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/attachment_type")
 * @package App\Controller
 */
class AttachmentTypeController extends AbstractController
{
    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="attachment_type_edit")
     * @Route("/{id}/", requirements={"id"="\d+"})
     */
    public function edit(AttachmentType $entity, Request $request, EntityManagerInterface $em)
    {

        $form = $this->createForm(BaseEntityAdminForm::class, $entity);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
        }

        return $this->render('AdminPages/AttachmentTypeAdmin.html.twig', [
            'entity' => $entity,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/new", name="attachment_type_new")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function new(Request $request, EntityManagerInterface $em)
    {
        $new_entity = new AttachmentType();

        $this->denyAccessUnlessGranted('create', $new_entity);

        $form = $this->createForm(BaseEntityAdminForm::class, $new_entity);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($new_entity);
            $em->flush();
            //$this->addFlash('success', $translator->trans('part.created_flash'));

            return $this->redirectToRoute('attachment_type_edit', ['id' => $new_entity->getID()]);
        }

        return $this->render('AdminPages/AttachmentTypeAdmin.html.twig', [
            'entity' => $new_entity,
            'form' => $form->createView()
        ]);
    }
}