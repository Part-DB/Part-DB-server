<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\AdminPages;

use App\DataTables\LogDataTable;
use App\Entity\AlternativeNames\AbstractAlternativeName;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Base\PartsContainingRepositoryInterface;
use App\Services\Cache\ElementCacheTagGenerator;
use App\Services\Trees\StructuralElementPathCache;
use App\Exceptions\AttachmentDownloadException;
use App\Form\AdminPages\ImportType;
use App\Form\AdminPages\MassCreationForm;
use App\Repository\AbstractPartsContainingRepository;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\LogSystem\HistoryHelper;
use App\Services\LogSystem\TimeTravel;
use App\Services\Trees\StructuralElementRecursionHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function Symfony\Component\Translation\t;

/**
 * Handles all CRUD logic for the generic admin page system.
 * Controllers call into this service; entity-specific behaviour is provided by AdminPageExtensionInterface.
 */
final readonly class AdminPageHandler
{
    public function __construct(
        private AdminCrudRegistry $registry,
        private EntityManagerInterface $em,
        private FormFactoryInterface $formFactory,
        private Environment $twig,
        private AuthorizationCheckerInterface $authChecker,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private AttachmentSubmitHandler $attachmentSubmitHandler,
        private EventCommentHelper $commentHelper,
        private HistoryHelper $historyHelper,
        private TimeTravel $timeTravel,
        private DataTableFactory $dataTableFactory,
        private EntityImporter $importer,
        private EntityExporter $exporter,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private TagAwareCacheInterface $cache,
        private ElementCacheTagGenerator $tagGenerator,
        private StructuralElementPathCache $pathCache,
    ) {
    }

    // ------------------------------------------------------------------
    // Public entry points
    // ------------------------------------------------------------------

    /** Exposed for the before-hooks in AdminPageController. */
    public function fetchEntityForHook(string $entityClass, int $id): AbstractNamedDBElement
    {
        return $this->fetchEntity($entityClass, $id);
    }

    public function handleEdit(
        string $entityClass,
        int $id,
        Request $request,
        ?AdminPageExtensionInterface $extension,
        ?string $timestamp = null,
    ): Response {
        $entity = $this->fetchEntity($entityClass, $id);

        if ($extension !== null) {
            $early = $extension->beforeEdit($entity, $request);
            if ($early !== null) {
                return $early;
            }
        }

        $this->denyAccess('read', $entity);
        $config = $this->registry->getConfig($entityClass);

        $timeTravel_timestamp = $this->revertIfNeeded($entity, $timestamp);

        if ($this->authChecker->isGranted('show_history', $entity)) {
            $table = $this->dataTableFactory->createFromType(
                LogDataTable::class,
                ['filter_elements' => $this->historyHelper->getAssociatedElements($entity), 'mode' => 'element_history'],
                ['pageLength' => 10]
            )->handleRequest($request);

            if ($table->isCallback()) {
                return $table->getResponse();
            }
        } else {
            $table = null;
        }

        $isStructural = is_a($entityClass, AbstractStructuralDBElement::class, true);
        $baseOptions = [
            'attachment_class' => $config->getAttachmentClass(),
            'parameter_class' => $config->getParameterClass(),
            'disabled' => $timeTravel_timestamp instanceof DateTime,
        ];
        if ($isStructural) {
            $baseOptions['show_alternative_names'] = $config->showAlternativeNamesField();
        }
        $formOptions = array_merge($baseOptions, $extension !== null ? $extension->formOptions($entity) : []);

        $form = $this->formFactory->create($config->getFormClass(), $entity, $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $proceed = $extension === null || $extension->onEditFormValid($form, $entity);
            if ($proceed) {
                $this->handleAttachmentUploads($config, $entity, $form);
                $this->commentHelper->setMessage($form['log_comment']->getData());
                $this->denyAccess('edit', $entity);
                $this->em->persist($entity);
                $this->em->flush();
                $this->flash('success', 'entity.edit_flash');
            }

            $reCreateOptions = [
                'attachment_class' => $config->getAttachmentClass(),
                'parameter_class' => $config->getParameterClass(),
            ];
            if ($isStructural) {
                $reCreateOptions['show_alternative_names'] = $config->showAlternativeNamesField();
            }
            $form = $this->formFactory->create($config->getFormClass(), $entity, $reCreateOptions);
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->flash('error', 'entity.edit_flash.invalid');
        }

        $repo = $this->em->getRepository($entityClass);

        return $this->renderResponse($config->getTemplatePath(), array_merge(
            [
                'entity' => $entity,
                'form' => $form->createView(),
                'route_base' => $config->getRouteBase(),
                'datatable' => $table,
                'timeTravel' => $timeTravel_timestamp,
                'repo' => $repo,
                'partsContainingElement' => $repo instanceof PartsContainingRepositoryInterface,
                'showParameters' => $config->showParametersTab(),
                'showAttachments' => $config->hasAttachments(),
            ],
            $extension !== null ? $extension->additionalTemplateVars($entity, $form) : []
        ));
    }

    public function handleNew(
        string $entityClass,
        ?int $cloneId,
        Request $request,
        ?AdminPageExtensionInterface $extension,
    ): Response {
        if ($extension !== null) {
            $early = $extension->beforeNew($request);
            if ($early !== null) {
                return $early;
            }
        }

        $cloneSource = $cloneId !== null ? $this->fetchEntity($entityClass, $cloneId) : null;
        /** @var AbstractNamedDBElement $newEntity */
        $newEntity = $cloneSource !== null ? clone $cloneSource : new $entityClass();
        $config = $this->registry->getConfig($entityClass);

        $this->denyAccess('read', $newEntity);

        $isStructural = is_a($entityClass, AbstractStructuralDBElement::class, true);
        $newOptions = [
            'attachment_class' => $config->getAttachmentClass(),
            'parameter_class' => $config->getParameterClass(),
        ];
        if ($isStructural) {
            $newOptions['show_alternative_names'] = $config->showAlternativeNamesField();
        }
        $formOptions = array_merge($newOptions, $extension !== null ? $extension->formOptions($newEntity) : []);

        $form = $this->formFactory->create($config->getFormClass(), $newEntity, $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccess('create', $newEntity);
        }

        $proceed = $extension === null || $extension->onNewFormValid($form, $newEntity);
        if ($form->isSubmitted() && $form->isValid() && $proceed) {
            $this->handleAttachmentUploads($config, $newEntity, $form);
            $this->commentHelper->setMessage($form['log_comment']->getData());
            $this->em->persist($newEntity);
            $this->em->flush();
            $this->flash('success', 'entity.created_flash');

            return new RedirectResponse(
                $this->urlGenerator->generate($config->getRouteBase().'_edit', ['id' => $newEntity->getID()])
            );
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->flash('error', 'entity.created_flash.invalid');
        }

        // Import form
        $importForm = $this->formFactory->create(ImportType::class, ['entity_class' => $entityClass]);
        $importForm->handleRequest($request);

        if ($importForm->isSubmitted() && $importForm->isValid()) {
            $this->denyAccess('import', $newEntity);
            /** @var UploadedFile $file */
            $file = $importForm['file']->getData();
            $data = $importForm->getData();

            if ($data['format'] === 'auto') {
                $format = $this->importer->determineFormat($file->getClientOriginalExtension());
                if ($format === null) {
                    $this->flash('error', 'parts.import.flash.error.unknown_format');
                    goto ret;
                }
            } else {
                $format = $data['format'];
            }

            $options = [
                'parent' => $data['parent'] ?? null,
                'preserve_children' => $data['preserve_children'] ?? false,
                'format' => $format,
                'class' => $entityClass,
                'csv_delimiter' => $data['csv_delimiter'],
                'abort_on_validation_error' => $data['abort_on_validation_error'],
            ];

            $this->commentHelper->setMessage('Import '.$file->getClientOriginalName());

            try {
                $errors = $this->importer->importFileAndPersistToDB($file, $options);
                foreach ($errors as ['violations' => $violations]) {
                    foreach ($violations as $violation) {
                        $this->flash('error', $violation->getMessage());
                    }
                }
            } catch (UnexpectedValueException) {
                $this->flash('error', 'parts.import.flash.error.invalid_file');
            }
        }

        ret:
        $massCreationForm = $this->formFactory->create(MassCreationForm::class, ['entity_class' => $entityClass]);
        $massCreationForm->handleRequest($request);

        if ($massCreationForm->isSubmitted() && $massCreationForm->isValid()) {
            $this->denyAccess('create', $newEntity);
            $data = $massCreationForm->getData();

            $errors = [];
            $results = $this->importer->massCreation($data['lines'], $entityClass, $data['parent'] ?? null, $errors);

            foreach ($errors as ['entity' => $errEntity, 'violations' => $violations]) {
                /** @var ConstraintViolationInterface $violation */
                foreach ($violations as $violation) {
                    if ($errEntity instanceof AbstractStructuralDBElement) {
                        $this->flash('error', $errEntity->getFullPath().':'.$violation->getMessage());
                    } else {
                        $this->flash('error', $errEntity->getName().':'.$violation->getMessage());
                    }
                }
            }

            $created = count(array_filter($results, static fn($r) => $r->getID() === null));
            foreach ($results as $result) {
                $this->em->persist($result);
            }
            $this->em->flush();

            if (count($results) > 0) {
                $this->flash('success', t('entity.mass_creation_flash', ['%COUNT%' => $created]));
            }
            if (count($errors) > 0) {
                $massCreationForm = $this->formFactory->create(MassCreationForm::class, ['entity_class' => $entityClass]);
            }
        }

        $repo = $this->em->getRepository($entityClass);

        return $this->renderResponse($config->getTemplatePath(), array_merge(
            [
                'entity' => $newEntity,
                'form' => $form->createView(),
                'import_form' => $importForm->createView(),
                'mass_creation_form' => $massCreationForm->createView(),
                'route_base' => $config->getRouteBase(),
                'showParameters' => $config->showParametersTab(),
                'showAttachments' => $config->hasAttachments(),
                'altNamesOverview' => $this->getAlternativeNamesOverview($entityClass, $config),
                'entityCount' => $repo->count([]),
                'rootEntityCount' => $repo instanceof \App\Repository\StructuralDBElementRepository ? $repo->countRootNodes() : null,
            ],
            $extension !== null ? $extension->additionalTemplateVars($newEntity, $form) : []
        ));
    }

    public function handleDelete(
        string $entityClass,
        int $id,
        Request $request,
        ?AdminPageExtensionInterface $extension,
        StructuralElementRecursionHelper $recursionHelper,
    ): Response {
        $entity = $this->fetchEntity($entityClass, $id);

        if ($extension !== null) {
            $early = $extension->beforeDelete($entity, $request);
            if ($early !== null) {
                return $early;
            }
        }

        $this->denyAccess('delete', $entity);
        $config = $this->registry->getConfig($entityClass);

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('delete'.$entity->getID(), (string) $request->request->get('_token')))) {
            $this->flash('error', 'csfr_invalid');

            return new RedirectResponse($this->urlGenerator->generate($config->getRouteBase().'_new'));
        }

        if (!$this->baseDeleteCheck($entityClass, $entity) || ($extension !== null && !$extension->checkDelete($entity))) {
            return new RedirectResponse($this->urlGenerator->generate($config->getRouteBase().'_edit', ['id' => $entity->getID()]));
        }

        if ($entity instanceof AbstractStructuralDBElement && $request->get('delete_recursive', false)) {
            $canDelete = true;
            $recursionHelper->execute($entity, function (AbstractStructuralDBElement $el) use (&$canDelete, $entityClass) {
                if (!$this->baseDeleteCheck($entityClass, $el)) {
                    $canDelete = false;
                }
            });
            if ($canDelete) {
                $recursionHelper->delete($entity, false);
            } else {
                return new RedirectResponse($this->urlGenerator->generate($config->getRouteBase().'_edit', ['id' => $entity->getID()]));
            }
        } else {
            if ($entity instanceof AbstractStructuralDBElement) {
                $parent = $entity->getParent();
                foreach ($entity->getSubelements() as $subelement) {
                    $subelement->setParent($parent);
                    $this->em->persist($subelement);
                }
            }
            $this->em->remove($entity);
        }

        $this->commentHelper->setMessage($request->request->get('log_comment', null));
        $this->em->flush();
        $this->flash('success', 'attachment_type.deleted');

        return new RedirectResponse($this->urlGenerator->generate($config->getRouteBase().'_new'));
    }

    public function handleExportAll(string $entityClass, Request $request): Response
    {
        $entity = new $entityClass();
        $this->denyAccess('read', $entity);
        $config = $this->registry->getConfig($entityClass);

        $entities = $this->em->getRepository($entityClass)->findAll();
        if (count($entities) === 0) {
            $this->flash('error', 'entity.export.flash.error.no_entities');

            return new RedirectResponse($this->urlGenerator->generate($config->getRouteBase().'_new'));
        }

        return $this->exporter->exportEntityFromRequest($entities, $request);
    }

    public function handleExportEntity(string $entityClass, int $id, Request $request): Response
    {
        $entity = $this->fetchEntity($entityClass, $id);
        $this->denyAccess('read', $entity);

        return $this->exporter->exportEntityFromRequest($entity, $request);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function fetchEntity(string $entityClass, int $id): AbstractNamedDBElement
    {
        $entity = $this->em->find($entityClass, $id);
        if (!$entity instanceof AbstractNamedDBElement) {
            throw new NotFoundHttpException(sprintf('Entity %s#%d not found.', $entityClass, $id));
        }

        return $entity;
    }

    private function revertIfNeeded(AbstractNamedDBElement $entity, ?string $timestamp): ?DateTime
    {
        if ($timestamp === null) {
            return null;
        }
        $this->denyAccess('show_history', $entity);
        $dt = ctype_digit($timestamp) ? (new DateTime())->setTimestamp((int) $timestamp) : new DateTime($timestamp);
        $this->timeTravel->revertEntityToTimestamp($entity, $dt);

        return $dt;
    }

    private function handleAttachmentUploads(AdminCrudConfig $config, AbstractNamedDBElement $entity, FormInterface $form): void
    {
        if (!$config->hasAttachments()) {
            return;
        }
        foreach ($form['attachments'] as $attachment) {
            /** @var FormInterface $attachment */
            try {
                $this->attachmentSubmitHandler->handleUpload(
                    $attachment->getData(),
                    AttachmentUpload::fromAttachmentForm($attachment)
                );
            } catch (AttachmentDownloadException $e) {
                $this->flash('error', $this->translator->trans('attachment.download_failed').' '.$e->getMessage());
            }
        }
        if ($entity instanceof AttachmentContainingDBElement
            && $entity->getMasterPictureAttachment() !== null
            && !$entity->getAttachments()->contains($entity->getMasterPictureAttachment())) {
            $entity->setMasterPictureAttachment(null);
        }
    }

    private function baseDeleteCheck(string $entityClass, AbstractNamedDBElement $entity): bool
    {
        if ($entity instanceof AbstractPartsContainingDBElement) {
            /** @var AbstractPartsContainingRepository $repo */
            $repo = $this->em->getRepository($entityClass); //@phpstan-ignore-line
            if ($repo->getPartsCount($entity) > 0) {
                $this->flash('error', t('entity.delete.must_not_contain_parts', ['%PATH%' => $entity->getFullPath()]));

                return false;
            }
        }

        return true;
    }

    private function denyAccess(mixed $attribute, mixed $subject = null): void
    {
        if ($this->authChecker->isGranted($attribute, $subject)) {
            return;
        }
        $ex = new AccessDeniedException('Access Denied.');
        $ex->setAttributes(is_array($attribute) ? $attribute : [$attribute]);
        $ex->setSubject($subject);
        throw $ex;
    }

    private function flash(string $type, string|\Stringable $message): void
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add($type, $message);
    }

    private function renderResponse(string $template, array $vars): Response
    {
        return new Response($this->twig->render($template, $vars));
    }

    /**
     * Returns the alternative names overview for the given entity class, grouped by name, with full paths and
     * duplicate detection. The result is cached and tagged with the entity type's cache tag so it is invalidated
     * whenever any element of that type changes.
     *
     * Returns null when alternative names are disabled for this entity type or no matching subclass is found.
     *
     * Each entry in the returned array has the shape:
     *   ['name' => string, 'is_duplicate' => bool, 'elements' => list<array{id: int, full_path: string}>]
     *
     * @return list<array{name: string, is_duplicate: bool, elements: list<array{id: int, full_path: string}>}>|null
     */
    private function getAlternativeNamesOverview(string $entityClass, AdminCrudConfig $config): ?array
    {
        if (!$config->showAlternativeNamesField()) {
            return null;
        }

        $altMeta = $this->em->getClassMetadata(AbstractAlternativeName::class);
        $altNameClass = null;
        foreach ($altMeta->discriminatorMap as $altClass) {
            $altClassMeta = $this->em->getClassMetadata($altClass);
            if ($altClassMeta->hasAssociation('element')
                && $altClassMeta->getAssociationTargetClass('element') === $entityClass) {
                $altNameClass = $altClass;
                break;
            }
        }

        if ($altNameClass === null) {
            return null;
        }

        $cacheTag = $this->tagGenerator->getElementTypeCacheTag($entityClass);
        $cacheKey = 'alt_names_overview_' . $cacheTag;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($altNameClass, $entityClass, $cacheTag): array {
            $item->tag([$cacheTag]);

            $rows = $this->em->createQueryBuilder()
                ->select('an.name AS alt_name', 'e.id AS element_id')
                ->from($altNameClass, 'an')
                ->join('an.element', 'e')
                ->orderBy('an.name', 'ASC')
                ->addOrderBy('e.name', 'ASC')
                ->getQuery()
                ->getArrayResult();

            $isStructural = is_a($entityClass, AbstractStructuralDBElement::class, true);

            $groups = [];
            foreach ($rows as $row) {
                $key = mb_strtolower((string) $row['alt_name']);
                if (!isset($groups[$key])) {
                    $groups[$key] = ['name' => (string) $row['alt_name'], 'elements' => []];
                }
                $elementId = (int) $row['element_id'];
                if ($isStructural) {
                    /** @var class-string<AbstractStructuralDBElement> $entityClass */
                    $pathNames = $this->pathCache->getPathNames($entityClass, $elementId);
                    $fullPath = $pathNames !== null
                        ? implode(AbstractStructuralDBElement::PATH_DELIMITER_ARROW, $pathNames)
                        : (string) $row['alt_name'];
                } else {
                    $fullPath = (string) $row['alt_name'];
                }
                $groups[$key]['elements'][] = ['id' => $elementId, 'full_path' => $fullPath];
            }

            $result = [];
            foreach ($groups as $group) {
                $result[] = [
                    'name' => $group['name'],
                    'is_duplicate' => count($group['elements']) > 1,
                    'elements' => $group['elements'],
                ];
            }

            return $result;
        });
    }
}
