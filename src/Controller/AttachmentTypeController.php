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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

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
     * @Route("/")
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

    /**
     * @Route("/{id}", name="attachment_type_delete", methods={"DELETE"})
     */
    public function delete(Request $request, AttachmentType $entity)
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();

            $parent = $entity->getParent();

            //Move all sub entities to the current parent
            foreach($entity->getSubelements() as $subelement) {
                $subelement->setParent($parent);
                $entityManager->persist($subelement);
            }

            //Remove current element
            $entityManager->remove($entity);
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
    public function exportAll(Request $request, SerializerInterface $serializer, EntityManagerInterface $em)
    {
        $entities = $em->getRepository(AttachmentType::class)->findAll();

        return $this->exportHelper($entities, $request, $serializer);
    }

    /**
     * @Route("/{id}/export", name="attachment_type_export")
     * @param Request $request
     * @param AttachmentType $entity
     */
    public function exportEntity(Request $request, AttachmentType $entity, SerializerInterface $serializer)
    {
        return $this->exportHelper($entity, $request, $serializer);
    }

    protected function exportHelper($entity, Request $request, SerializerInterface $serializer) : Response
    {
        $format = $request->get('format') ?? "json";

        //Check if we have one of the supported formats
        if (!in_array($format, ['json', 'csv', 'yaml', 'xml'])) {
            throw new \InvalidArgumentException("Given format is not supported!");
        }

        //Check export verbosity level
        $level = $request->get('level') ?? 'extended';
        if (!in_array($level, ['simple', 'extended', 'full'])) {
            throw new \InvalidArgumentException('Given level is not supported!');
        }

        //Check for include children option
        $include_children = $request->get('include_children') ?? false;

        //Check which groups we need to export, based on level and include_children
        $groups = array($level);
        if ($include_children) {
            $groups[] = 'include_children';
        }

        //Plain text should work for all types
        $content_type = "text/plain";

        //Try to use better content types based on the format
        switch ($format) {
            case 'xml':
                $content_type = "application/xml";
                break;
            case 'json':
                $content_type = "application/json";
                break;
        }

        $response = new Response($serializer->serialize($entity, $format,
            [
                'groups' => $groups,
                'as_collection' => true,
                'csv_delimiter' => ';', //Better for Excel
                'xml_root_node_name' => 'PartDBExport'
            ]));

        $response->headers->set('Content-Type', $content_type);

        //If view option is not specified, then download the file.
        if (!$request->get('view')) {
            if ($entity instanceof NamedDBElement) {
                $entity_name = $entity->getName();
            } elseif (is_array($entity)) {
                if (empty($entity)) {
                    throw new \InvalidArgumentException('$entity must not be empty!');
                }

                //Use the class name of the first element for the filename
                $reflection = new \ReflectionClass($entity[0]);
                $entity_name = $reflection->getShortName();
            } else {
                throw new \InvalidArgumentException('$entity type is not supported!');
            }


            $filename = "export_" . $entity_name . "_" . $level . "." . $format;

            // Create the disposition of the file
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            );
            // Set the content disposition
            $response->headers->set('Content-Disposition', $disposition);
        }

        return $response;
    }
}