<?php

/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
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
 */

namespace App\Controller;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Form\AttachmentFormType;
use App\Form\Part\PartBaseType;
use App\Services\AttachmentHelper;
use App\Services\PricedetailHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PartController extends AbstractController
{
    /**
     * @Route("/part/{id}/info", name="part_info")
     * @Route("/part/{id}", requirements={"id"="\d+"})
     * @param Part $part
     * @param AttachmentHelper $attachmentHelper
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function show(Part $part, AttachmentHelper $attachmentHelper, PricedetailHelper $pricedetailHelper)
    {
        $this->denyAccessUnlessGranted('read', $part);

        return $this->render('Parts/info/show_part_info.html.twig',
            [
                'part' => $part,
                'attachment_helper' => $attachmentHelper,
                'pricedetail_helper' => $pricedetailHelper
            ]
            );
    }

    /**
     * @Route("/part/{id}/edit", name="part_edit")
     *
     * @param Part $part
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function edit(Part $part, Request $request, EntityManagerInterface $em, TranslatorInterface $translator,
            AttachmentHelper $attachmentHelper)
    {
        $this->denyAccessUnlessGranted('edit', $part);

        $form = $this->createForm(PartBaseType::class, $part);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var $attachment FormInterface */
                $attachmentHelper->upload( $attachment->getData(), $attachment['file']->getData());
            }


            $em->persist($part);
            $em->flush();
            $this->addFlash('info', $translator->trans('part.edited_flash'));
            //Reload form, so the SIUnitType entries use the new part unit
            $form = $this->createForm(PartBaseType::class, $part);
        } elseif ($form->isSubmitted() && ! $form->isValid()) {
            $this->addFlash('error', $translator->trans('part.edited_flash.invalid'));
        }

        return $this->render('Parts/edit/edit_part_info.html.twig',
            [
                'part' => $part,
                'form' => $form->createView(),
                'attachment_helper' => $attachmentHelper,
            ]);
    }

    /**
     * @Route("/part/new", name="part_new")
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function new(Request $request, EntityManagerInterface $em, TranslatorInterface $translator,
                        AttachmentHelper $attachmentHelper)
    {
        $new_part = new Part();

        $this->denyAccessUnlessGranted('create', $new_part);

        $cid = $request->get('cid', 1);

        $category = $em->find(Category::class, $cid);
        $new_part->setCategory($category);

        $form = $this->createForm(PartBaseType::class, $new_part);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var $attachment FormInterface */
                $attachmentHelper->upload( $attachment->getData(), $attachment['file']->getData());
            }

            $em->persist($new_part);
            $em->flush();
            $this->addFlash('success', $translator->trans('part.created_flash'));

            return $this->redirectToRoute('part_edit', ['id' => $new_part->getID()]);
        } elseif ($form->isSubmitted() && ! $form->isValid()) {
            $this->addFlash('error', $translator->trans('part.created_flash.invalid'));
        }

        return $this->render('Parts/edit/new_part.html.twig',
            [
                'part' => $new_part,
                'form' => $form->createView(),
                'attachment_helper' => $attachmentHelper
            ]);
    }

    /**
     * @Route("/part/{id}/clone", name="part_clone")
     * @param Part $part
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function clone(Part $part, Request $request, EntityManagerInterface $em, TranslatorInterface $translator)
    {
        /** @var Part $new_part */
        $new_part = clone $part;

        $this->denyAccessUnlessGranted('create', $new_part);

        $form = $this->createForm(PartBaseType::class, $new_part);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($new_part);
            $em->flush();
            $this->addFlash('success', $translator->trans('part.created_flash'));

            return $this->redirectToRoute('part_edit', ['id' => $new_part->getID()]);
        }

        return $this->render('Parts/edit/new_part.html.twig',
            [
                'part' => $new_part,
                'form' => $form->createView(),
            ]);
    }
}
