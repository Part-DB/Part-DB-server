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
use App\Entity\NamedDBElement;
use App\Entity\StructuralDBElement;
use App\Form\BaseEntityAdminForm;
use App\Form\ExportType;
use App\Form\ImportType;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationList;

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

        $this->denyAccessUnlessGranted('read', $entity);

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
     * @Route("/")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        $new_entity = new AttachmentType();

        $this->denyAccessUnlessGranted('read', $new_entity);

        //Basic edit form
        $form = $this->createForm(BaseEntityAdminForm::class, $new_entity);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($new_entity);
            $em->flush();
            //$this->addFlash('success', $translator->trans('part.created_flash'));

            return $this->redirectToRoute('attachment_type_edit', ['id' => $new_entity->getID()]);
        }

        //Import form
        $import_form = $this->createForm(ImportType::class, ['entity_class' => AttachmentType::class]);
        $import_form->handleRequest($request);

        if ($import_form->isSubmitted() && $import_form->isValid()) {
            /** @var UploadedFile $file */
            $file = $import_form['file']->getData();
            $data = $import_form->getData();

            $options = array('parent' => $data['parent'], 'preserve_children' => $data['preserve_children'],
                'format' => $data['format'], 'csv_separator' => $data['csv_separator']);

            $errors = $importer->fileToDBEntities($file, AttachmentType::class, $options);

            foreach ($errors as $name => $error) {
                /** @var $error ConstraintViolationList */
                $this->addFlash('error', $name . ":" . $error);
            }
        }

        return $this->render('AdminPages/AttachmentTypeAdmin.html.twig', [
            'entity' => $new_entity,
            'form' => $form->createView(),
            'import_form' => $import_form->createView()
        ]);
    }

    /**
     * @Route("/{id}", name="attachment_type_delete", methods={"DELETE"})
     */
    public function delete(Request $request, AttachmentType $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        $this->denyAccessUnlessGranted('delete', $entity);

        if ($this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();

            //Check if we need to remove recursively
            if ($request->get('delete_recursive', false)) {
                $recursionHelper->delete($entity, false);
            } else {
                $parent = $entity->getParent();

                //Move all sub entities to the current parent
                foreach ($entity->getSubelements() as $subelement) {
                    $subelement->setParent($parent);
                    $entityManager->persist($subelement);
                }

                //Remove current element
                $entityManager->remove($entity);
            }

            //Flush changes
            $entityManager->flush();

            $this->addFlash('success', 'attachment_type.deleted');
        }

        return $this->redirectToRoute('attachment_type_new');
    }

    /**
     * @Route("/export", name="attachment_type_export_all")
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request)
    {
        $this->denyAccessUnlessGranted('read', $entity);

        $entities = $em->getRepository(AttachmentType::class)->findAll();

        return $exporter->exportEntityFromRequest($entities,$request);
    }

    /**
     * @Route("/{id}/export", name="attachment_type_export")
     * @param Request $request
     * @param AttachmentType $entity
     */
    public function exportEntity(AttachmentType $entity, EntityExporter $exporter, Request $request)
    {
        $this->denyAccessUnlessGranted('read', $entity);

        return $exporter->exportEntityFromRequest($entity, $request);
    }

}