<?php
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

use App\Entity\Base\NamedDBElement;
use App\Entity\Base\StructuralDBElement;
use App\Entity\UserSystem\User;
use App\Exceptions\AttachmentDownloadException;
use App\Form\AdminPages\ImportType;
use App\Form\AdminPages\MassCreationForm;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseAdminController extends AbstractController
{
    protected $entity_class = '';
    protected $form_class = '';
    protected $twig_template = '';
    protected $route_base = '';
    protected $attachment_class = '';

    protected $passwordEncoder;
    protected $translator;
    protected $attachmentHelper;
    protected $attachmentSubmitHandler;

    public function __construct(TranslatorInterface $translator, UserPasswordEncoderInterface $passwordEncoder,
                                AttachmentManager $attachmentHelper, AttachmentSubmitHandler $attachmentSubmitHandler)
    {
        if ('' === $this->entity_class || '' === $this->form_class || '' === $this->twig_template || '' === $this->route_base) {
            throw new \InvalidArgumentException('You have to override the $entity_class, $form_class, $route_base and $twig_template value in your subclasss!');
        }

        if ('' === $this->attachment_class) {
            throw new \InvalidArgumentException('You have to override the $attachment_class value in your subclass!');
        }

        $this->translator = $translator;
        $this->passwordEncoder = $passwordEncoder;
        $this->attachmentHelper = $attachmentHelper;
        $this->attachmentSubmitHandler = $attachmentSubmitHandler;
    }

    protected function _edit(NamedDBElement $entity, Request $request, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('read', $entity);

        $form = $this->createForm($this->form_class, $entity, ['attachment_class' => $this->attachment_class]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //Check if we editing a user and if we need to change the password of it
            if ($entity instanceof User && !empty($form['new_password']->getData())) {
                $password = $this->passwordEncoder->encodePassword($entity, $form['new_password']->getData());
                $entity->setPassword($password);
                //By default the user must change the password afterwards
                $entity->setNeedPwChange(true);
            }

            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var $attachment FormInterface */
                $options = [
                    'secure_attachment' => $attachment['secureFile']->getData(),
                    'download_url' => $attachment['downloadURL']->getData(),
                ];
                try {
                    $this->attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $ex) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('attachment.download_failed').' '.$ex->getMessage()
                    );
                }
            }

            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', $this->translator->trans('entity.edit_flash'));

            //Rebuild form, so it is based on the updated data. Important for the parent field!
            //We can not use dynamic form events here, because the parent entity list is build from database!
            $form = $this->createForm($this->form_class, $entity, ['attachment_class' => $this->attachment_class]);
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', $this->translator->trans('entity.edit_flash.invalid'));
        }

        return $this->render($this->twig_template, [
            'entity' => $entity,
            'form' => $form->createView(),
            'attachment_helper' => $this->attachmentHelper,
        ]);
    }

    protected function _new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        /** @var StructuralDBElement $new_entity */
        $new_entity = new $this->entity_class();

        $this->denyAccessUnlessGranted('read', $new_entity);

        //Basic edit form
        $form = $this->createForm($this->form_class, $new_entity, ['attachment_class' => $this->attachment_class]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($new_entity instanceof User && !empty($form['new_password']->getData())) {
                $password = $this->passwordEncoder->encodePassword($new_entity, $form['new_password']->getData());
                $new_entity->setPassword($password);
                //By default the user must change the password afterwards
                $new_entity->setNeedPwChange(true);
            }

            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var $attachment FormInterface */
                $options = [
                    'secure_attachment' => $attachment['secureFile']->getData(),
                    'download_url' => $attachment['downloadURL']->getData(),
                ];
                try {
                    $this->attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $ex) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('attachment.download_failed').' '.$ex->getMessage()
                    );
                }
            }

            $em->persist($new_entity);
            $em->flush();
            $this->addFlash('success', $this->translator->trans('entity.created_flash'));

            return $this->redirectToRoute($this->route_base.'_edit', ['id' => $new_entity->getID()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', $this->translator->trans('entity.created_flash.invalid'));
        }

        //Import form
        $import_form = $this->createForm(ImportType::class, ['entity_class' => $this->entity_class]);
        $import_form->handleRequest($request);

        if ($import_form->isSubmitted() && $import_form->isValid()) {
            /** @var UploadedFile $file */
            $file = $import_form['file']->getData();
            $data = $import_form->getData();

            $options = ['parent' => $data['parent'], 'preserve_children' => $data['preserve_children'],
                'format' => $data['format'], 'csv_separator' => $data['csv_separator'], ];

            $errors = $importer->fileToDBEntities($file, $this->entity_class, $options);

            foreach ($errors as $name => $error) {
                /* @var $error ConstraintViolationList */
                $this->addFlash('error', $name.':'.$error);
            }
        }

        //Mass creation form
        $mass_creation_form = $this->createForm(MassCreationForm::class, ['entity_class' => $this->entity_class]);
        $mass_creation_form->handleRequest($request);

        if ($mass_creation_form->isSubmitted() && $mass_creation_form->isValid()) {
            $data = $mass_creation_form->getData();

            //Create entries based on input
            $errors = [];
            $results = $importer->massCreation($data['lines'], $this->entity_class, $data['parent'], $errors);

            //Show errors to user:
            foreach ($errors as $error) {
                $this->addFlash('error', $error['entity']->getFullPath().':'.$error['violations']);
            }

            //Persist valid entities to DB
            foreach ($results as $result) {
                $em->persist($result);
            }
            $em->flush();
        }

        return $this->render($this->twig_template, [
            'entity' => $new_entity,
            'form' => $form->createView(),
            'import_form' => $import_form->createView(),
            'mass_creation_form' => $mass_creation_form->createView(),
            'attachment_helper' => $this->attachmentHelper,
        ]);
    }

    protected function _delete(Request $request, NamedDBElement $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        $this->denyAccessUnlessGranted('delete', $entity);

        if ($this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();

            //Check if we need to remove recursively
            if ($entity instanceof StructuralDBElement && $request->get('delete_recursive', false)) {
                $recursionHelper->delete($entity, false);
            } else {
                if ($entity instanceof StructuralDBElement) {
                    $parent = $entity->getParent();

                    //Move all sub entities to the current parent
                    foreach ($entity->getSubelements() as $subelement) {
                        $subelement->setParent($parent);
                        $entityManager->persist($subelement);
                    }
                }

                //Remove current element
                $entityManager->remove($entity);
            }

            //Flush changes
            $entityManager->flush();

            $this->addFlash('success', 'attachment_type.deleted');
        } else {
            $this->addFlash('error', 'csfr_invalid');
        }

        return $this->redirectToRoute($this->route_base.'_new');
    }

    protected function _exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request)
    {
        $entity = new $this->entity_class();

        $this->denyAccessUnlessGranted('read', $entity);

        $entities = $em->getRepository($this->entity_class)->findAll();

        return $exporter->exportEntityFromRequest($entities, $request);
    }

    protected function _exportEntity(NamedDBElement $entity, EntityExporter $exporter, Request $request)
    {
        $this->denyAccessUnlessGranted('read', $entity);

        return $exporter->exportEntityFromRequest($entity, $request);
    }
}
