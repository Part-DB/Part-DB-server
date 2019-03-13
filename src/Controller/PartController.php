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


use App\Entity\Category;
use App\Entity\Part;
use App\Form\PartType;
use App\Services\AttachmentFilenameService;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PartController extends AbstractController
{

    /**
     * @Route("/part/{id}/info", name="part_info")
     * @Route("/part/{id}")
     */
    public function show(Part $part, AttachmentFilenameService $attachmentFilenameService)
    {
        $filename = $part->getMasterPictureFilename(true);

        return $this->render('show_part_info.html.twig',
            [
                "part" => $part,
                "main_image" => $attachmentFilenameService->attachmentPathToAbsolutePath($filename)
            ]
            );
    }

    /**
     * @Route("/part/{id}/edit", name="part_edit", requirements={"id"="\d+"})
     *
     * @param Part $part
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function edit(Part $part, Request $request, EntityManagerInterface $em)
    {
        $form = $this->createForm(PartType::class, $part);


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($part);
            $em->flush();
            $this->addFlash('info', 'part.edited_flash');
        }

        return $this->render('edit_part_info.html.twig',
            [
                "part" => $part,
                "form" => $form->createView(),
            ]);
    }

    /**
     * @Route("/parts/new", name="part_new")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function new(Request $request, EntityManagerInterface $em, TranslatorInterface $translator)
    {
        $new_part = new Part();
        $category = $em->find(Category::class, 1);
        $new_part->setCategory($category);

        $form = $this->createForm(PartType::class, $new_part);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($new_part);
            $em->flush();
            $this->addFlash('success', $translator->trans('part.created_flash'));
            return $this->redirectToRoute('part_edit',['id' => $new_part->getID()]);
        }


        return $this->render('new_part.html.twig',
            [
                "part" => $new_part,
                "form" => $form->createView()
            ]);
    }

}