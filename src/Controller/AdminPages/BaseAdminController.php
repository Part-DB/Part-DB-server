<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Controller\AdminPages;

use App\DataTables\LogDataTable;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Base\PartsContainingRepositoryInterface;
use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parameters\AbstractParameter;
use App\Exceptions\AttachmentDownloadException;
use App\Exceptions\TwigModeException;
use App\Form\AdminPages\ImportType;
use App\Form\AdminPages\MassCreationForm;
use App\Repository\AbstractPartsContainingRepository;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\LabelSystem\LabelExampleElementsGenerator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\LogSystem\HistoryHelper;
use App\Services\LogSystem\TimeTravel;
use App\Services\Trees\StructuralElementRecursionHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

abstract class BaseAdminController extends AbstractController
{
    protected string $entity_class = '';
    protected string $form_class = '';
    protected string $twig_template = '';
    protected string $route_base = '';
    protected string $attachment_class = '';
    protected ?string $parameter_class = '';

    public function __construct(protected TranslatorInterface $translator, protected UserPasswordHasherInterface $passwordEncoder,
        protected AttachmentSubmitHandler $attachmentSubmitHandler,
        protected EventCommentHelper $commentHelper, protected HistoryHelper $historyHelper, protected TimeTravel $timeTravel,
        protected DataTableFactory $dataTableFactory, protected EventDispatcherInterface $eventDispatcher, protected LabelExampleElementsGenerator $barcodeExampleGenerator,
        protected LabelGenerator $labelGenerator, protected EntityManagerInterface $entityManager)
    {
        if ('' === $this->entity_class || '' === $this->form_class || '' === $this->twig_template || '' === $this->route_base) {
            throw new InvalidArgumentException('You have to override the $entity_class, $form_class, $route_base and $twig_template value in your subclasss!');
        }

        if ('' === $this->attachment_class || !is_a($this->attachment_class, Attachment::class, true)) {
            throw new InvalidArgumentException('You have to override the $attachment_class value with a valid Attachment class in your subclass!');
        }

        if ('' === $this->parameter_class || ($this->parameter_class && !is_a($this->parameter_class, AbstractParameter::class, true))) {
            throw new InvalidArgumentException('You have to override the $parameter_class value with a valid Parameter class in your subclass!');
        }
    }

    protected function revertElementIfNeeded(AbstractDBElement $entity, ?string $timestamp): ?DateTime
    {
        if (null !== $timestamp) {
            $this->denyAccessUnlessGranted('show_history', $entity);
            //If the timestamp only contains numbers interpret it as unix timestamp
            if (ctype_digit($timestamp)) {
                $timeTravel_timestamp = new DateTime();
                $timeTravel_timestamp->setTimestamp((int) $timestamp);
            } else { //Try to parse it via DateTime
                $timeTravel_timestamp = new DateTime($timestamp);
            }
            $this->timeTravel->revertEntityToTimestamp($entity, $timeTravel_timestamp);

            return $timeTravel_timestamp;
        }

        return null;
    }

    /**
     * Perform some additional actions, when the form was valid, but before the entity is saved.
     *
     * @return bool return true, to save entity normally, return false, to abort saving
     */
    protected function additionalActionEdit(FormInterface $form, AbstractNamedDBElement $entity): bool
    {
        return true;
    }

    protected function _edit(AbstractNamedDBElement $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        $this->denyAccessUnlessGranted('read', $entity);

        $timeTravel_timestamp = $this->revertElementIfNeeded($entity, $timestamp);

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
            'disabled' => $timeTravel_timestamp instanceof \DateTime,
        ];

        //Disable editing of options, if user is not allowed to use twig...
        if (
            $entity instanceof LabelProfile
            && LabelProcessMode::TWIG === $entity->getOptions()->getProcessMode()
            && !$this->isGranted('@labels.use_twig')
        ) {
            $form_options['disable_options'] = true;
        }

        $form = $this->createForm($this->form_class, $entity, $form_options);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->additionalActionEdit($form, $entity)) {
                //Upload passed files
                $attachments = $form['attachments'];
                foreach ($attachments as $attachment) {
                    /** @var FormInterface $attachment */
                    try {
                        $this->attachmentSubmitHandler->handleUpload(
                            $attachment->getData(),
                            AttachmentUpload::fromAttachmentForm($attachment)
                        );
                    } catch (AttachmentDownloadException $attachmentDownloadException) {
                        $this->addFlash(
                            'error',
                            $this->translator->trans(
                                'attachment.download_failed'
                            ).' '.$attachmentDownloadException->getMessage()
                        );
                    }
                }

                //Ensure that the master picture is still part of the attachments
                if ($entity instanceof AttachmentContainingDBElement && ($entity->getMasterPictureAttachment() !== null && !$entity->getAttachments()->contains($entity->getMasterPictureAttachment()))) {
                    $entity->setMasterPictureAttachment(null);
                }

                $this->commentHelper->setMessage($form['log_comment']->getData());

                $em->persist($entity);
                $em->flush();
                $this->addFlash('success', 'entity.edit_flash');
            }

            //Rebuild form, so it is based on the updated data. Important for the parent field!
            //We can not use dynamic form events here, because the parent entity list is build from database!
            $form = $this->createForm($this->form_class, $entity, [
                'attachment_class' => $this->attachment_class,
                'parameter_class' => $this->parameter_class,
            ]);
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'entity.edit_flash.invalid');
        }

        //Show preview for LabelProfile if needed.
        if ($entity instanceof LabelProfile) {
            $example = $this->barcodeExampleGenerator->getElement($entity->getOptions()->getSupportedElement());
            $pdf_data = null;
            try {
                $pdf_data = $this->labelGenerator->generateLabel($entity->getOptions(), $example);
            } catch (TwigModeException $exception) {
                $form->get('options')->get('lines')->addError(new FormError($exception->getSafeMessage()));
            }
        }

        $repo = $this->entityManager->getRepository($this->entity_class);

        return $this->render($this->twig_template, [
            'entity' => $entity,
            'form' => $form,
            'route_base' => $this->route_base,
            'datatable' => $table,
            'pdf_data' => $pdf_data ?? null,
            'timeTravel' => $timeTravel_timestamp,
            'repo' => $repo,
            'partsContainingElement' => $repo instanceof PartsContainingRepositoryInterface,
        ]);
    }

    /**
     * Perform some additional actions, when the form was valid, but before the entity is saved.
     *
     * @return bool return true, to save entity normally, return false, to abort saving
     */
    protected function additionalActionNew(FormInterface $form, AbstractNamedDBElement $entity): bool
    {
        return true;
    }

    protected function _new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?AbstractNamedDBElement $entity = null): Response
    {
        $new_entity = $entity instanceof AbstractNamedDBElement ? clone $entity : new $this->entity_class();

        $this->denyAccessUnlessGranted('read', $new_entity);

        //Basic edit form
        $form = $this->createForm($this->form_class, $new_entity, [
            'attachment_class' => $this->attachment_class,
            'parameter_class' => $this->parameter_class,
        ]);

        $form->handleRequest($request);

        //Perform additional actions
        if ($form->isSubmitted() && $form->isValid() && $this->additionalActionNew($form, $new_entity)) {
            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var FormInterface $attachment */

                try {
                    $this->attachmentSubmitHandler->handleUpload(
                        $attachment->getData(),
                        AttachmentUpload::fromAttachmentForm($attachment)
                    );
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'attachment.download_failed'
                        ).' '.$attachmentDownloadException->getMessage()
                    );
                }
            }

            //Ensure that the master picture is still part of the attachments
            if ($new_entity instanceof AttachmentContainingDBElement && ($new_entity->getMasterPictureAttachment() !== null && !$new_entity->getAttachments()->contains($new_entity->getMasterPictureAttachment()))) {
                $new_entity->setMasterPictureAttachment(null);
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());
            $em->persist($new_entity);
            $em->flush();
            $this->addFlash('success', 'entity.created_flash');
            return $this->redirectToRoute($this->route_base.'_edit', ['id' => $new_entity->getID()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'entity.created_flash.invalid');
        }

        //Import form
        $import_form = $this->createForm(ImportType::class, ['entity_class' => $this->entity_class]);
        $import_form->handleRequest($request);

        if ($import_form->isSubmitted() && $import_form->isValid()) {
            /** @var UploadedFile $file */
            $file = $import_form['file']->getData();
            $data = $import_form->getData();

            if ($data['format'] === 'auto') {
                $format = $importer->determineFormat($file->getClientOriginalExtension());
                if (null === $format) {
                    $this->addFlash('error', 'parts.import.flash.error.unknown_format');
                    goto ret;
                }
            } else {
                $format = $data['format'];
            }

            $options = [
                'parent' => $data['parent'] ?? null,
                'preserve_children' => $data['preserve_children'] ?? false,
                'format' => $format,
                'class' => $this->entity_class,
                'csv_delimiter' => $data['csv_delimiter'],
                'abort_on_validation_error' => $data['abort_on_validation_error'],
            ];

            $this->commentHelper->setMessage('Import '.$file->getClientOriginalName());

            try {
                $errors = $importer->importFileAndPersistToDB($file, $options);

                foreach ($errors as $name => ['violations' => $violations]) {
                    foreach ($violations as $violation) {
                        $this->addFlash('error', $name.': '.$violation->getMessage());
                    }
                }
            }
            catch (UnexpectedValueException) {
                $this->addFlash('error', 'parts.import.flash.error.invalid_file');
            }
        }

        ret:
        //Mass creation form
        $mass_creation_form = $this->createForm(MassCreationForm::class, ['entity_class' => $this->entity_class]);
        $mass_creation_form->handleRequest($request);

        if ($mass_creation_form->isSubmitted() && $mass_creation_form->isValid()) {
            $data = $mass_creation_form->getData();

            //Create entries based on input
            $errors = [];
            $results = $importer->massCreation($data['lines'], $this->entity_class, $data['parent'] ?? null, $errors);

            //Show errors to user:
            foreach ($errors as ['entity' => $new_entity, 'violations' => $violations]) {
                /** @var ConstraintViolationInterface $violation */
                foreach ($violations as $violation) {
                    if ($new_entity instanceof AbstractStructuralDBElement) {
                        $this->addFlash('error', $new_entity->getFullPath().':'.$violation->getMessage());
                    } else { //When we don't have a structural element, we can only show the name
                        $this->addFlash('error', $new_entity->getName().':'.$violation->getMessage());
                    }
                }
            }

            //Persist valid entities to DB
            foreach ($results as $result) {
                $em->persist($result);
            }
            $em->flush();

            if (count($results) > 0) {
                $this->addFlash('success', t('entity.mass_creation_flash', ['%COUNT%' => count($results)]));
            }
        }

        return $this->render($this->twig_template, [
            'entity' => $new_entity,
            'form' => $form,
            'import_form' => $import_form,
            'mass_creation_form' => $mass_creation_form,
            'route_base' => $this->route_base,
        ]);
    }

    /**
     * Performs checks if the element can be deleted safely. Otherwise, a flash message is added.
     *
     * @param AbstractNamedDBElement $entity the element that should be checked
     *
     * @return bool True if the element can be deleted, false if not
     */
    protected function deleteCheck(AbstractNamedDBElement $entity): bool
    {
        if ($entity instanceof AbstractPartsContainingDBElement) {
            /** @var AbstractPartsContainingRepository $repo */
            $repo = $this->entityManager->getRepository($this->entity_class); //@phpstan-ignore-line
            if ($repo->getPartsCount($entity) > 0) {
                $this->addFlash('error', t('entity.delete.must_not_contain_parts', ['%PATH%' => $entity->getFullPath()]));

                return false;
            }
        }

        return true;
    }

    protected function _delete(Request $request, AbstractNamedDBElement $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        $this->denyAccessUnlessGranted('delete', $entity);

        if ($this->isCsrfTokenValid('delete'.$entity->getID(), $request->request->get('_token'))) {

            $entityManager = $this->entityManager;

            if (!$this->deleteCheck($entity)) {
                return $this->redirectToRoute($this->route_base.'_edit', ['id' => $entity->getID()]);
            }

            //Check if we need to remove recursively
            if ($entity instanceof AbstractStructuralDBElement && $request->get('delete_recursive', false)) {
                $can_delete = true;
                //Check if any of the children can not be deleted, cause it contains parts
                $recursionHelper->execute($entity, function (AbstractStructuralDBElement $element) use (&$can_delete) {
                    if(!$this->deleteCheck($element)) {
                        $can_delete = false;
                    }
                });
                if($can_delete) {
                    $recursionHelper->delete($entity, false);
                } else {
                    return $this->redirectToRoute($this->route_base.'_edit', ['id' => $entity->getID()]);
                }
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

    protected function _exportEntity(AbstractNamedDBElement $entity, EntityExporter $exporter, Request $request): Response
    {
        $this->denyAccessUnlessGranted('read', $entity);

        return $exporter->exportEntityFromRequest($entity, $request);
    }
}
