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

namespace App\Controller\AdminPages;

use App\DataTables\LogDataTable;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Base\PartsContainingRepositoryInterface;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Events\SecurityEvent;
use App\Events\SecurityEvents;
use App\Exceptions\AttachmentDownloadException;
use App\Form\AdminPages\ImportType;
use App\Form\AdminPages\MassCreationForm;
use App\Repository\AbstractPartsContainingRepository;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\LabelSystem\Barcodes\BarcodeExampleElementsGenerator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\LogSystem\HistoryHelper;
use App\Services\LogSystem\TimeTravel;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    protected $parameter_class = '';

    protected $passwordEncoder;
    protected $translator;
    protected $attachmentSubmitHandler;
    protected $commentHelper;

    protected $historyHelper;
    protected $timeTravel;
    protected $dataTableFactory;
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;
    protected $labelGenerator;
    protected $barcodeExampleGenerator;

    protected $entityManager;

    public function __construct(TranslatorInterface $translator, UserPasswordEncoderInterface $passwordEncoder,
        AttachmentSubmitHandler $attachmentSubmitHandler,
        EventCommentHelper $commentHelper, HistoryHelper $historyHelper, TimeTravel $timeTravel,
        DataTableFactory $dataTableFactory, EventDispatcherInterface $eventDispatcher, BarcodeExampleElementsGenerator $barcodeExampleGenerator,
        LabelGenerator $labelGenerator, EntityManagerInterface $entityManager)
    {
        if ('' === $this->entity_class || '' === $this->form_class || '' === $this->twig_template || '' === $this->route_base) {
            throw new InvalidArgumentException('You have to override the $entity_class, $form_class, $route_base and $twig_template value in your subclasss!');
        }

        if ('' === $this->attachment_class) {
            throw new InvalidArgumentException('You have to override the $attachment_class value in your subclass!');
        }

        if ('' === $this->parameter_class) {
            throw new InvalidArgumentException('You have to override the $parameter_class value in your subclass!');
        }

        $this->translator = $translator;
        $this->passwordEncoder = $passwordEncoder;
        $this->attachmentSubmitHandler = $attachmentSubmitHandler;
        $this->commentHelper = $commentHelper;
        $this->historyHelper = $historyHelper;
        $this->timeTravel = $timeTravel;
        $this->dataTableFactory = $dataTableFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->barcodeExampleGenerator = $barcodeExampleGenerator;
        $this->labelGenerator = $labelGenerator;
        $this->entityManager = $entityManager;
    }

    protected function _edit(AbstractNamedDBElement $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        $this->denyAccessUnlessGranted('read', $entity);

        $timeTravel_timestamp = null;
        if (null !== $timestamp) {
            $this->denyAccessUnlessGranted('@tools.timetravel');
            $this->denyAccessUnlessGranted('show_history', $entity);
            //If the timestamp only contains numbers interpret it as unix timestamp
            if (ctype_digit($timestamp)) {
                $timeTravel_timestamp = new \DateTime();
                $timeTravel_timestamp->setTimestamp((int) $timestamp);
            } else { //Try to parse it via DateTime
                $timeTravel_timestamp = new \DateTime($timestamp);
            }
            $this->timeTravel->revertEntityToTimestamp($entity, $timeTravel_timestamp);
        }

        if ($this->isGranted('show_history', $entity)) {
            $table = $this->dataTableFactory->createFromType(
                LogDataTable::class,
                [
                    'filter_elements' => $this->historyHelper->getAssociatedElements($entity),
                    'mode' => 'element_history',
                ],
                ['pageLength' => 10]
            )
                ->handleRequest($request);

            if ($table->isCallback()) {
                return $table->getResponse();
            }
        } else {
            $table = null;
        }

        $form_options = [
            'attachment_class' => $this->attachment_class,
            'parameter_class' => $this->parameter_class,
            'disabled' => null !== $timeTravel_timestamp ? true : false,
        ];

        //Disable editing of options, if user is not allowed to use twig...
        if (
            $entity instanceof LabelProfile
            && 'twig' === $entity->getOptions()->getLinesMode()
            && ! $this->isGranted('@labels.use_twig')
        ) {
            $form_options['disable_options'] = true;
        }

        $form = $this->createForm($this->form_class, $entity, $form_options);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //Check if we editing a user and if we need to change the password of it
            if ($entity instanceof User && ! empty($form['new_password']->getData())) {
                $password = $this->passwordEncoder->encodePassword($entity, $form['new_password']->getData());
                $entity->setPassword($password);
                //By default the user must change the password afterwards
                $entity->setNeedPwChange(true);

                $event = new SecurityEvent($entity);
                $this->eventDispatcher->dispatch($event, SecurityEvents::PASSWORD_CHANGED);
            }

            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var FormInterface $attachment */
                $options = [
                    'secure_attachment' => $attachment['secureFile']->getData(),
                    'download_url' => $attachment['downloadURL']->getData(),
                ];

                try {
                    $this->attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('attachment.download_failed').' '.$attachmentDownloadException->getMessage()
                    );
                }
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());

            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'entity.edit_flash');

            //Rebuild form, so it is based on the updated data. Important for the parent field!
            //We can not use dynamic form events here, because the parent entity list is build from database!
            $form = $this->createForm($this->form_class, $entity, [
                'attachment_class' => $this->attachment_class,
                'parameter_class' => $this->parameter_class,
            ]);
        } elseif ($form->isSubmitted() && ! $form->isValid()) {
            $this->addFlash('error', 'entity.edit_flash.invalid');
        }

        //Show preview for LabelProfile if needed.
        if ($entity instanceof LabelProfile) {
            $example = $this->barcodeExampleGenerator->getElement($entity->getOptions()->getSupportedElement());
            $pdf_data = $this->labelGenerator->generateLabel($entity->getOptions(), $example);
        }

        /** @var AbstractPartsContainingRepository $repo */
        $repo = $this->entityManager->getRepository($this->entity_class);


        return $this->render($this->twig_template, [
            'entity' => $entity,
            'form' => $form->createView(),
            'route_base' => $this->route_base,
            'datatable' => $table,
            'pdf_data' => $pdf_data ?? null,
            'timeTravel' => $timeTravel_timestamp,
            'repo' => $repo,
            'partsContainingElement' => $repo instanceof PartsContainingRepositoryInterface,
        ]);
    }

    protected function _new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?AbstractNamedDBElement $entity = null)
    {
        $master_picture_backup = null;
        if (null === $entity) {
            /** @var AbstractStructuralDBElement|User $new_entity */
            $new_entity = new $this->entity_class();
        } else {
            /** @var AbstractStructuralDBElement|User $new_entity */
            $new_entity = clone $entity;
        }

        $this->denyAccessUnlessGranted('read', $new_entity);

        //Basic edit form
        $form = $this->createForm($this->form_class, $new_entity, [
            'attachment_class' => $this->attachment_class,
            'parameter_class' => $this->parameter_class,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($new_entity instanceof User && ! empty($form['new_password']->getData())) {
                $password = $this->passwordEncoder->encodePassword($new_entity, $form['new_password']->getData());
                $new_entity->setPassword($password);
                //By default the user must change the password afterwards
                $new_entity->setNeedPwChange(true);
            }

            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var FormInterface $attachment */
                $options = [
                    'secure_attachment' => $attachment['secureFile']->getData(),
                    'download_url' => $attachment['downloadURL']->getData(),
                ];

                try {
                    $this->attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('attachment.download_failed').' '.$attachmentDownloadException->getMessage()
                    );
                }
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());

            $em->persist($new_entity);
            $em->flush();
            $this->addFlash('success', 'entity.created_flash');

            return $this->redirectToRoute($this->route_base.'_edit', ['id' => $new_entity->getID()]);
        }

        if ($form->isSubmitted() && ! $form->isValid()) {
            $this->addFlash('error', 'entity.created_flash.invalid');
        }

        //Import form
        $import_form = $this->createForm(ImportType::class, ['entity_class' => $this->entity_class]);
        $import_form->handleRequest($request);

        if ($import_form->isSubmitted() && $import_form->isValid()) {
            /** @var UploadedFile $file */
            $file = $import_form['file']->getData();
            $data = $import_form->getData();

            $options = [
                'parent' => $data['parent'],
                'preserve_children' => $data['preserve_children'],
                'format' => $data['format'],
                'csv_separator' => $data['csv_separator'],
            ];

            $this->commentHelper->setMessage('Import '.$file->getClientOriginalName());

            $errors = $importer->fileToDBEntities($file, $this->entity_class, $options);

            foreach ($errors as $name => $error) {
                /** @var ConstraintViolationList $error */
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
            'route_base' => $this->route_base,
        ]);
    }

    protected function _delete(Request $request, AbstractNamedDBElement $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        $this->denyAccessUnlessGranted('delete', $entity);

        if ($this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();

            //Check if we can delete the part (it must not contain Parts)
            if ($entity instanceof AbstractPartsContainingDBElement) {
                /** @var AbstractPartsContainingRepository $repo */
                $repo = $this->entityManager->getRepository($this->entity_class);
                if ($repo->getPartsCount($entity) > 0) {
                    $this->addFlash('error', 'entity.delete.must_not_contain_parts');
                    return $this->redirectToRoute($this->route_base.'_new');
                }
            } elseif ($entity instanceof AttachmentType) {
                if ($entity->getAttachmentsForType()->count() > 0) {
                    $this->addFlash('error', 'entity.delete.must_not_contain_attachments');
                    return $this->redirectToRoute($this->route_base.'_new');
                }
            } elseif ($entity instanceof Currency) {
                if ($entity->getPricedetails()->count() > 0) {
                    $this->addFlash('error', 'entity.delete.must_not_contain_prices');
                    return $this->redirectToRoute($this->route_base.'_new');
                }
            } elseif ($entity instanceof Group) {
                if ($entity->getUsers()->count() > 0) {
                    $this->addFlash('error', 'entity.delete.must_not_contain_users');
                    return $this->redirectToRoute($this->route_base.'_new');
                }
            } elseif ($entity instanceof User) {
                //TODO: Find a better solution
                $this->addFlash('error', 'Currently it is not possible to delete a user, as this would break the log... This will be implemented later...');
                return $this->redirectToRoute($this->route_base.'_new');
            }

            //Check if we need to remove recursively
            if ($entity instanceof AbstractStructuralDBElement && $request->get('delete_recursive', false)) {
                $recursionHelper->delete($entity, false);
            } else {
                if ($entity instanceof AbstractStructuralDBElement) {
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

            $this->commentHelper->setMessage($request->request->get('log_comment', null));

            //Flush changes
            $entityManager->flush();

            $this->addFlash('success', 'attachment_type.deleted');
        } else {
            $this->addFlash('error', 'csfr_invalid');
        }

        return $this->redirectToRoute($this->route_base.'_new');
    }

    protected function _exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        $entity = new $this->entity_class();

        $this->denyAccessUnlessGranted('read', $entity);

        $entities = $em->getRepository($this->entity_class)->findAll();

        return $exporter->exportEntityFromRequest($entities, $request);
    }

    protected function _exportEntity(AbstractNamedDBElement $entity, EntityExporter $exporter, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted('read', $entity);

        return $exporter->exportEntityFromRequest($entity, $request);
    }
}
