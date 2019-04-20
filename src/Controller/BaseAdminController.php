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
use App\Entity\StructuralDBElement;
use App\Form\BaseEntityAdminForm;
use App\Form\ImportType;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;

abstract class BaseAdminController extends AbstractController
{

    protected $entity_class = "";
    protected $form_class = "";
    protected $twig_template = "";
    protected $route_base = "";

    public function __construct()
    {
        if ($this->entity_class === "" || $this->form_class === "" || $this->twig_template === "" || $this->route_base === "") {
            throw new \InvalidArgumentException('You have to override the $entity_class, $form_class, $route_base and $twig_template value in your subclasss!');
        }
    }

    protected function _edit(StructuralDBElement $entity, Request $request, EntityManagerInterface $em)
    {

        $this->denyAccessUnlessGranted('read', $entity);

        $form = $this->createForm($this->form_class, $entity);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
        }

        return $this->render($this->twig_template, [
            'entity' => $entity,
            'form' => $form->createView()
        ]);
    }

    protected function _new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        /** @var StructuralDBElement $new_entity */
        $new_entity = new $this->entity_class();

        $this->denyAccessUnlessGranted('read', $new_entity);

        //Basic edit form
        $form = $this->createForm($this->form_class, $new_entity);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($new_entity);
            $em->flush();
            //$this->addFlash('success', $translator->trans('part.created_flash'));

            return $this->redirectToRoute($this->route_base . '_edit', ['id' => $new_entity->getID()]);
        }

        //Import form
        $import_form = $this->createForm(ImportType::class, ['entity_class' => $this->entity_class]);
        $import_form->handleRequest($request);

        if ($import_form->isSubmitted() && $import_form->isValid()) {
            /** @var UploadedFile $file */
            $file = $import_form['file']->getData();
            $data = $import_form->getData();

            $options = array('parent' => $data['parent'], 'preserve_children' => $data['preserve_children'],
                'format' => $data['format'], 'csv_separator' => $data['csv_separator']);

            $errors = $importer->fileToDBEntities($file, $this->entity_class, $options);

            foreach ($errors as $name => $error) {
                /** @var $error ConstraintViolationList */
                $this->addFlash('error', $name . ":" . $error);
            }
        }

        return $this->render($this->twig_template, [
            'entity' => $new_entity,
            'form' => $form->createView(),
            'import_form' => $import_form->createView()
        ]);
    }

    protected function _delete(Request $request, StructuralDBElement $entity, StructuralElementRecursionHelper $recursionHelper)
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

        return $this->redirectToRoute($this->route_base .  '_new');
    }

    protected function _exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request)
    {
        $entity = new $this->entity_class();

        $this->denyAccessUnlessGranted('read', $entity);

        $entities = $em->getRepository($this->entity_class)->findAll();

        return $exporter->exportEntityFromRequest($entities,$request);
    }

    protected function _exportEntity(StructuralDBElement $entity, EntityExporter $exporter, Request $request)
    {
        $this->denyAccessUnlessGranted('read', $entity);

        return $exporter->exportEntityFromRequest($entity, $request);
    }
}