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

namespace App\Controller;

use App\DataTables\LogDataTable;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\ProjectSystem\Project;
use App\Exceptions\AttachmentDownloadException;
use App\Form\Part\PartBaseType;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Attachments\PartPreviewGenerator;
use App\Services\EntityMergers\Mergers\PartMerger;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\LogSystem\HistoryHelper;
use App\Services\LogSystem\TimeTravel;
use App\Services\Parameters\ParameterExtractor;
use App\Services\Parts\PartLotWithdrawAddHelper;
use App\Services\Parts\PricedetailHelper;
use App\Services\ProjectSystem\ProjectBuildPartHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

#[Route(path: '/part')]
class PartController extends AbstractController
{
    public function __construct(
        protected PricedetailHelper $pricedetailHelper,
        protected PartPreviewGenerator $partPreviewGenerator,
        private readonly TranslatorInterface $translator,
        private readonly AttachmentSubmitHandler $attachmentSubmitHandler,
        private readonly EntityManagerInterface $em,
        protected EventCommentHelper $commentHelper
    ) {
    }

    /**
     *
     * @throws Exception
     */
    #[Route(path: '/{id}/info/{timestamp}', name: 'part_info')]
    #[Route(path: '/{id}', requirements: ['id' => '\d+'])]
    public function show(
        Part $part,
        Request $request,
        TimeTravel $timeTravel,
        HistoryHelper $historyHelper,
        DataTableFactory $dataTable,
        ParameterExtractor $parameterExtractor,
        PartLotWithdrawAddHelper $withdrawAddHelper,
        ?string $timestamp = null
    ): Response {
        $this->denyAccessUnlessGranted('read', $part);

        $timeTravel_timestamp = null;
        if (null !== $timestamp) {
            $this->denyAccessUnlessGranted('show_history', $part);
            //If the timestamp only contains numbers interpret it as unix timestamp
            if (ctype_digit($timestamp)) {
                $timeTravel_timestamp = new DateTime();
                $timeTravel_timestamp->setTimestamp((int) $timestamp);
            } else { //Try to parse it via DateTime
                $timeTravel_timestamp = new DateTime($timestamp);
            }
            $timeTravel->revertEntityToTimestamp($part, $timeTravel_timestamp);
        }

        if ($this->isGranted('show_history', $part)) {
            $table = $dataTable->createFromType(LogDataTable::class, [
                'filter_elements' => $historyHelper->getAssociatedElements($part),
                'mode' => 'element_history',
            ], ['pageLength' => 10])
                ->handleRequest($request);

            if ($table->isCallback()) {
                return $table->getResponse();
            }
        } else {
            $table = null;
        }

        return $this->render(
            'parts/info/show_part_info.html.twig',
            [
                'part' => $part,
                'datatable' => $table,
                'pricedetail_helper' => $this->pricedetailHelper,
                'pictures' => $this->partPreviewGenerator->getPreviewAttachments($part),
                'timeTravel' => $timeTravel_timestamp,
                'description_params' => $parameterExtractor->extractParameters($part->getDescription()),
                'comment_params' => $parameterExtractor->extractParameters($part->getComment()),
                'withdraw_add_helper' => $withdrawAddHelper,
            ]
        );
    }

    #[Route(path: '/{id}/edit', name: 'part_edit')]
    public function edit(Part $part, Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit', $part);

        // Check if this is part of a bulk import job
        $jobId = $request->query->get('jobId');
        $bulkJob = null;
        if ($jobId) {
            $bulkJob = $this->em->getRepository(\App\Entity\InfoProviderSystem\BulkInfoProviderImportJob::class)->find($jobId);
            // Verify user owns this job
            if ($bulkJob && $bulkJob->getCreatedBy() !== $this->getUser()) {
                $bulkJob = null;
            }
        }

        return $this->renderPartForm('edit', $request, $part, [], [
            'bulk_job' => $bulkJob
        ]);
    }

    #[Route(path: '/{id}/bulk-import-complete/{jobId}', name: 'part_bulk_import_complete', methods: ['POST'])]
    public function markBulkImportComplete(Part $part, int $jobId, Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit', $part);

        if (!$this->isCsrfTokenValid('bulk_complete_' . $part->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $bulkJob = $this->em->getRepository(\App\Entity\InfoProviderSystem\BulkInfoProviderImportJob::class)->find($jobId);
        if (!$bulkJob || $bulkJob->getCreatedBy() !== $this->getUser()) {
            throw $this->createNotFoundException('Bulk import job not found');
        }

        $bulkJob->markPartAsCompleted($part->getId());
        $this->em->persist($bulkJob);
        $this->em->flush();

        $this->addFlash('success', 'Part marked as completed in bulk import');

        return $this->redirectToRoute('bulk_info_provider_step2', ['jobId' => $jobId]);
    }

    #[Route(path: '/{id}/delete', name: 'part_delete', methods: ['DELETE'])]
    public function delete(Request $request, Part $part): RedirectResponse
    {
        $this->denyAccessUnlessGranted('delete', $part);

        if ($this->isCsrfTokenValid('delete' . $part->getID(), $request->request->get('_token'))) {

            $this->commentHelper->setMessage($request->request->get('log_comment', null));

            //Remove part
            $this->em->remove($part);

            //Flush changes
            $this->em->flush();

            $this->addFlash('success', 'part.deleted');
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route(path: '/new', name: 'part_new')]
    #[Route(path: '/{id}/clone', name: 'part_clone')]
    #[Route(path: '/new_build_part/{project_id}', name: 'part_new_build_part')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        AttachmentSubmitHandler $attachmentSubmitHandler,
        ProjectBuildPartHelper $projectBuildPartHelper,
        #[MapEntity(mapping: ['id' => 'id'])] ?Part $part = null,
        #[MapEntity(mapping: ['project_id' => 'id'])] ?Project $project = null
    ): Response {

        if ($part instanceof Part) {
            //Clone part
            $new_part = clone $part;
        } elseif ($project instanceof Project) {
            //Initialize a new part for a build part from the given project
            //Ensure that the project has not already a build part
            if ($project->getBuildPart() instanceof Part) {
                $this->addFlash('error', 'part.new_build_part.error.build_part_already_exists');
                return $this->redirectToRoute('part_edit', ['id' => $project->getBuildPart()->getID()]);
            }
            $new_part = $projectBuildPartHelper->getPartInitialization($project);
        } else { //Create an empty part from scratch
            $new_part = new Part();
        }

        $this->denyAccessUnlessGranted('create', $new_part);

        $cid = $request->get('category', null);
        $category = $cid ? $em->find(Category::class, $cid) : null;
        if ($category instanceof Category && !$new_part->getCategory() instanceof Category) {
            $new_part->setCategory($category);
            $new_part->setDescription($category->getDefaultDescription());
            $new_part->setComment($category->getDefaultComment());
        }

        $fid = $request->get('footprint', null);
        $footprint = $fid ? $em->find(Footprint::class, $fid) : null;
        if ($footprint instanceof Footprint && !$new_part->getFootprint() instanceof Footprint) {
            $new_part->setFootprint($footprint);
        }

        $mid = $request->get('manufacturer', null);
        $manufacturer = $mid ? $em->find(Manufacturer::class, $mid) : null;
        if ($manufacturer instanceof Manufacturer && !$new_part->getManufacturer() instanceof Manufacturer) {
            $new_part->setManufacturer($manufacturer);
        }

        $store_id = $request->get('storelocation', null);
        $storelocation = $store_id ? $em->find(StorageLocation::class, $store_id) : null;
        if ($storelocation instanceof StorageLocation && $new_part->getPartLots()->isEmpty()) {
            $partLot = new PartLot();
            $partLot->setStorageLocation($storelocation);
            $partLot->setInstockUnknown(true);
            $new_part->addPartLot($partLot);
        }

        $supplier_id = $request->get('supplier', null);
        $supplier = $supplier_id ? $em->find(Supplier::class, $supplier_id) : null;
        if ($supplier instanceof Supplier && $new_part->getOrderdetails()->isEmpty()) {
            $orderdetail = new Orderdetail();
            $orderdetail->setSupplier($supplier);
            $new_part->addOrderdetail($orderdetail);
        }

        return $this->renderPartForm('new', $request, $new_part);
    }

    #[Route('/from_info_provider/{providerKey}/{providerId}/create', name: 'info_providers_create_part', requirements: ['providerId' => '.+'])]
    public function createFromInfoProvider(Request $request, string $providerKey, string $providerId, PartInfoRetriever $infoRetriever): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        $dto = $infoRetriever->getDetails($providerKey, $providerId);
        $new_part = $infoRetriever->dtoToPart($dto);

        if ($new_part->getCategory() === null || $new_part->getCategory()->getID() === null) {
            $this->addFlash('warning', t("part.create_from_info_provider.no_category_yet"));
        }

        return $this->renderPartForm('new', $request, $new_part, [
            'info_provider_dto' => $dto,
        ]);
    }

    #[Route('/{target}/merge/{other}', name: 'part_merge')]
    public function merge(Request $request, Part $target, Part $other, PartMerger $partMerger): Response
    {
        $this->denyAccessUnlessGranted('edit', $target);
        $this->denyAccessUnlessGranted('delete', $other);

        //Save the old name of the target part for the template
        $target_name = $target->getName();

        $this->addFlash('notice', t('part.merge.flash.please_review'));

        $merged = $partMerger->merge($target, $other);
        return $this->renderPartForm('merge', $request, $merged, [], [
            'tname_before' => $target_name,
            'other_part' => $other,
        ]);
    }

    #[Route(path: '/{id}/from_info_provider/{providerKey}/{providerId}/update', name: 'info_providers_update_part', requirements: ['providerId' => '.+'])]
    public function updateFromInfoProvider(
        Part $part,
        Request $request,
        string $providerKey,
        string $providerId,
        PartInfoRetriever $infoRetriever,
        PartMerger $partMerger
    ): Response {
        $this->denyAccessUnlessGranted('edit', $part);
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        //Save the old name of the target part for the template
        $old_name = $part->getName();

        $dto = $infoRetriever->getDetails($providerKey, $providerId);
        $provider_part = $infoRetriever->dtoToPart($dto);

        $part = $partMerger->merge($part, $provider_part);

        $this->addFlash('notice', t('part.merge.flash.please_review'));

        // Check if this is part of a bulk import job
        $jobId = $request->query->get('jobId');
        $bulkJob = null;
        if ($jobId) {
            $bulkJob = $this->em->getRepository(\App\Entity\InfoProviderSystem\BulkInfoProviderImportJob::class)->find($jobId);
            // Verify user owns this job
            if ($bulkJob && $bulkJob->getCreatedBy() !== $this->getUser()) {
                $bulkJob = null;
            }
        }

        return $this->renderPartForm('update_from_ip', $request, $part, [
            'info_provider_dto' => $dto,
        ], [
            'tname_before' => $old_name,
            'bulk_job' => $bulkJob
        ]);
    }

    /**
     * This function provides a common implementation for methods, which use the part form.
     * @param  Request  $request
     * @param  Part  $data
     * @param  array  $form_options
     * @return Response
     */
    private function renderPartForm(string $mode, Request $request, Part $data, array $form_options = [], array $merge_infos = []): Response
    {
        //Ensure that mode is either 'new' or 'edit
        if (!in_array($mode, ['new', 'edit', 'merge', 'update_from_ip'], true)) {
            throw new \InvalidArgumentException('Invalid mode given');
        }

        $new_part = $data;

        $form = $this->createForm(PartBaseType::class, $new_part, $form_options);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var FormInterface $attachment */

                try {
                    $this->attachmentSubmitHandler->handleUpload($attachment->getData(), AttachmentUpload::fromAttachmentForm($attachment));
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('attachment.download_failed') . ' ' . $attachmentDownloadException->getMessage()
                    );
                }
            }

            //Ensure that the master picture is still part of the attachments
            if ($new_part->getMasterPictureAttachment() !== null && !$new_part->getAttachments()->contains($new_part->getMasterPictureAttachment())) {
                $new_part->setMasterPictureAttachment(null);
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());

            $this->em->persist($new_part);

            //When we are in merge mode, we have to remove the other part
            if ($mode === 'merge') {
                $this->em->remove($merge_infos['other_part']);
            }

            $this->em->flush();
            if ($mode === 'new') {
                $this->addFlash('success', 'part.created_flash');
            } elseif ($mode === 'edit') {
                $this->addFlash('success', 'part.edited_flash');
            }

            //If a redirect URL was given, redirect there
            if ($request->query->get('_redirect')) {
                return $this->redirect($request->query->get('_redirect'));
            }

            //Redirect to clone page if user wished that...
            //@phpstan-ignore-next-line
            if ('save_and_clone' === $form->getClickedButton()->getName()) {
                return $this->redirectToRoute('part_clone', ['id' => $new_part->getID()]);
            }
            //@phpstan-ignore-next-line
            if ('save_and_new' === $form->getClickedButton()->getName()) {
                return $this->redirectToRoute('part_new');
            }

            // Check if we're in bulk import mode and preserve jobId
            $jobId = $request->query->get('jobId');
            if ($jobId && isset($merge_infos['bulk_job'])) {
                return $this->redirectToRoute('part_edit', ['id' => $new_part->getID(), 'jobId' => $jobId]);
            }

            return $this->redirectToRoute('part_edit', ['id' => $new_part->getID()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'part.created_flash.invalid');
        }

        $template = '';
        if ($mode === 'new') {
            $template = 'parts/edit/new_part.html.twig';
        } elseif ($mode === 'edit') {
            $template = 'parts/edit/edit_part_info.html.twig';
        } elseif ($mode === 'merge') {
            $template = 'parts/edit/merge_parts.html.twig';
        } elseif ($mode === 'update_from_ip') {
            $template = 'parts/edit/update_from_ip.html.twig';
        }

        return $this->render(
            $template,
            [
                'part' => $new_part,
                'form' => $form,
                'merge_old_name' => $merge_infos['tname_before'] ?? null,
                'merge_other' => $merge_infos['other_part'] ?? null,
                'bulk_job' => $merge_infos['bulk_job'] ?? null,
                'jobId' => $request->query->get('jobId')
            ]
        );
    }


    #[Route(path: '/{id}/add_withdraw', name: 'part_add_withdraw', methods: ['POST'])]
    public function withdrawAddHandler(Part $part, Request $request, EntityManagerInterface $em, PartLotWithdrawAddHelper $withdrawAddHelper): Response
    {
        if ($this->isCsrfTokenValid('part_withraw' . $part->getID(), $request->request->get('_csfr'))) {
            //Retrieve partlot from the request
            $partLot = $em->find(PartLot::class, $request->request->get('lot_id'));
            if (!$partLot instanceof PartLot) {
                throw new \RuntimeException('Part lot not found!');
            }
            //Ensure that the partlot belongs to the part
            if ($partLot->getPart() !== $part) {
                throw new \RuntimeException("The origin partlot does not belong to the part!");
            }

            //Try to determine the target lot (used for move actions), if the parameter is existing
            $targetId = $request->request->get('target_id', null);
            $targetLot = $targetId ? $em->find(PartLot::class, $targetId) : null;
            if ($targetLot && $targetLot->getPart() !== $part) {
                throw new \RuntimeException("The target partlot does not belong to the part!");
            }

            //Extract the amount and comment from the request
            $amount = (float) $request->request->get('amount');
            $comment = $request->request->get('comment');
            $action = $request->request->get('action');
            $delete_lot_if_empty = $request->request->getBoolean('delete_lot_if_empty', false);

            $timestamp = null;
            $timestamp_str = $request->request->getString('timestamp', '');
            //Try to parse the timestamp
            if ($timestamp_str !== '') {
                $timestamp = new DateTime($timestamp_str);
            }

            //Ensure that the timestamp is not in the future
            if ($timestamp !== null && $timestamp > new DateTime("+20min")) {
                throw new \LogicException("The timestamp must not be in the future!");
            }

            //Ensure that the amount is not null or negative
            if ($amount <= 0) {
                $this->addFlash('warning', 'part.withdraw.zero_amount');
                goto err;
            }

            try {
                switch ($action) {
                    case "withdraw":
                    case "remove":
                        $this->denyAccessUnlessGranted('withdraw', $partLot);
                        $withdrawAddHelper->withdraw($partLot, $amount, $comment, $timestamp, $delete_lot_if_empty);
                        break;
                    case "add":
                        $this->denyAccessUnlessGranted('add', $partLot);
                        $withdrawAddHelper->add($partLot, $amount, $comment, $timestamp);
                        break;
                    case "move":
                        $this->denyAccessUnlessGranted('move', $partLot);
                        $this->denyAccessUnlessGranted('move', $targetLot);
                        $withdrawAddHelper->move($partLot, $targetLot, $amount, $comment, $timestamp, $delete_lot_if_empty);
                        break;
                    default:
                        throw new \RuntimeException("Unknown action!");
                }
            } catch (AccessDeniedException) {
                $this->addFlash('error', t('part.withdraw.access_denied'));
                goto err;
            }

            //Save the changes to the DB
            $em->flush();
            $this->addFlash('success', 'part.withdraw.success');

        } else {
            $this->addFlash('error', 'CSRF Token invalid!');
        }

        err:
        //If a redirect was passed, then redirect there
        if ($request->request->get('_redirect')) {
            return $this->redirect($request->request->get('_redirect'));
        }
        //Otherwise just redirect to the part page
        return $this->redirectToRoute('part_info', ['id' => $part->getID()]);
    }
}
