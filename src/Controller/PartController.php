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
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\ProjectSystem\Project;
use App\Exceptions\AttachmentDownloadException;
use App\Form\Part\PartBaseType;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Attachments\PartPreviewGenerator;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/part")
 */
class PartController extends AbstractController
{
    protected PricedetailHelper $pricedetailHelper;
    protected PartPreviewGenerator $partPreviewGenerator;
    protected EventCommentHelper $commentHelper;

    public function __construct(PricedetailHelper $pricedetailHelper,
        PartPreviewGenerator $partPreviewGenerator, EventCommentHelper $commentHelper)
    {
        $this->pricedetailHelper = $pricedetailHelper;
        $this->partPreviewGenerator = $partPreviewGenerator;
        $this->commentHelper = $commentHelper;
    }

    /**
     * @Route("/{id}/info/{timestamp}", name="part_info")
     * @Route("/{id}", requirements={"id"="\d+"})
     *
     * @throws Exception
     */
    public function show(Part $part, Request $request, TimeTravel $timeTravel, HistoryHelper $historyHelper,
        DataTableFactory $dataTable, ParameterExtractor $parameterExtractor, PartLotWithdrawAddHelper $withdrawAddHelper, ?string $timestamp = null): Response
    {
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

    /**
     * @Route("/{id}/edit", name="part_edit")
     */
    public function edit(Part $part, Request $request, EntityManagerInterface $em, TranslatorInterface $translator,
        AttachmentSubmitHandler $attachmentSubmitHandler): Response
    {
        $this->denyAccessUnlessGranted('edit', $part);

        $form = $this->createForm(PartBaseType::class, $part);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var FormInterface $attachment */
                $options = [
                    'secure_attachment' => $attachment['secureFile']->getData(),
                    'download_url' => $attachment['downloadURL']->getData(),
                ];

                try {
                    $attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $translator->trans('attachment.download_failed').' '.$attachmentDownloadException->getMessage()
                    );
                }
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());

            $em->persist($part);
            $em->flush();
            $this->addFlash('success', 'part.edited_flash');

            //Redirect to clone page if user wished that...
            //@phpstan-ignore-next-line
            if ('save_and_clone' === $form->getClickedButton()->getName()) {
                return $this->redirectToRoute('part_clone', ['id' => $part->getID()]);
            }
            //@phpstan-ignore-next-line
            if ('save_and_new' === $form->getClickedButton()->getName()) {
                return $this->redirectToRoute('part_new');
            }

            //Reload form, so the SIUnitType entries use the new part unit
            $form = $this->createForm(PartBaseType::class, $part);
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'part.edited_flash.invalid');
        }

        return $this->renderForm('parts/edit/edit_part_info.html.twig',
            [
                'part' => $part,
                'form' => $form,
            ]);
    }

    /**
     * @Route("/{id}/delete", name="part_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Part $part, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('delete', $part);

        if ($this->isCsrfTokenValid('delete'.$part->getId(), $request->request->get('_token'))) {

            $this->commentHelper->setMessage($request->request->get('log_comment', null));

            //Remove part
            $entityManager->remove($part);

            //Flush changes
            $entityManager->flush();

            $this->addFlash('success', 'part.deleted');
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * @Route("/new", name="part_new")
     * @Route("/{id}/clone", name="part_clone")
     * @Route("/new_build_part/{project_id}", name="part_new_build_part")
     * @ParamConverter("part", options={"id" = "id"})
     * @ParamConverter("project", options={"id" = "project_id"})
     */
    public function new(Request $request, EntityManagerInterface $em, TranslatorInterface $translator,
        AttachmentSubmitHandler $attachmentSubmitHandler, ProjectBuildPartHelper $projectBuildPartHelper,
        ?Part $part = null, ?Project $project = null): Response
    {

        if ($part) { //Clone part
            $new_part = clone $part;
        } else if ($project) { //Initialize a new part for a build part from the given project
            //Ensure that the project has not already a build part
            if ($project->getBuildPart() !== null) {
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
        if (null !== $category && null === $new_part->getCategory()) {
            $new_part->setCategory($category);
            $new_part->setDescription($category->getDefaultDescription());
            $new_part->setComment($category->getDefaultComment());
        }

        $fid = $request->get('footprint', null);
        $footprint = $fid ? $em->find(Footprint::class, $fid) : null;
        if (null !== $footprint && null === $new_part->getFootprint()) {
            $new_part->setFootprint($footprint);
        }

        $mid = $request->get('manufacturer', null);
        $manufacturer = $mid ? $em->find(Manufacturer::class, $mid) : null;
        if (null !== $manufacturer && null === $new_part->getManufacturer()) {
            $new_part->setManufacturer($manufacturer);
        }

        $store_id = $request->get('storelocation', null);
        $storelocation = $store_id ? $em->find(Storelocation::class, $store_id) : null;
        if (null !== $storelocation && $new_part->getPartLots()->isEmpty()) {
            $partLot = new PartLot();
            $partLot->setStorageLocation($storelocation);
            $partLot->setInstockUnknown(true);
            $new_part->addPartLot($partLot);
        }

        $supplier_id = $request->get('supplier', null);
        $supplier = $supplier_id ? $em->find(Supplier::class, $supplier_id) : null;
        if (null !== $supplier && $new_part->getOrderdetails()->isEmpty()) {
            $orderdetail = new Orderdetail();
            $orderdetail->setSupplier($supplier);
            $new_part->addOrderdetail($orderdetail);
        }

        $form = $this->createForm(PartBaseType::class, $new_part);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var FormInterface $attachment */
                $options = [
                    'secure_attachment' => $attachment['secureFile']->getData(),
                    'download_url' => $attachment['downloadURL']->getData(),
                ];

                try {
                    $attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $translator->trans('attachment.download_failed').' '.$attachmentDownloadException->getMessage()
                    );
                }
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());

            $em->persist($new_part);
            $em->flush();
            $this->addFlash('success', 'part.created_flash');

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

            return $this->redirectToRoute('part_edit', ['id' => $new_part->getID()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'part.created_flash.invalid');
        }

        return $this->renderForm('parts/edit/new_part.html.twig',
            [
                'part' => $new_part,
                'form' => $form,
            ]);
    }

    /**
     * @Route("/{id}/add_withdraw", name="part_add_withdraw", methods={"POST"})
     */
    public function withdrawAddHandler(Part $part, Request $request, EntityManagerInterface $em, PartLotWithdrawAddHelper $withdrawAddHelper): Response
    {
        if ($this->isCsrfTokenValid('part_withraw' . $part->getID(), $request->request->get('_csfr'))) {
            //Retrieve partlot from the request
            $partLot = $em->find(PartLot::class, $request->request->get('lot_id'));
            if($partLot === null) {
                throw new \RuntimeException('Part lot not found!');
            }
            //Ensure that the partlot belongs to the part
            if($partLot->getPart() !== $part) {
                throw new \RuntimeException("The origin partlot does not belong to the part!");
            }
            //Try to determine the target lot (used for move actions)
            $targetLot = $em->find(PartLot::class, $request->request->get('target_id'));
            if ($targetLot && $targetLot->getPart() !== $part) {
                throw new \RuntimeException("The target partlot does not belong to the part!");
            }

            //Extract the amount and comment from the request
            $amount = (float) $request->request->get('amount');
            $comment = $request->request->get('comment');
            $action = $request->request->get('action');



            switch ($action) {
                case "withdraw":
                case "remove":
                    $this->denyAccessUnlessGranted('withdraw', $partLot);
                    $withdrawAddHelper->withdraw($partLot, $amount, $comment);
                    break;
                case "add":
                    $this->denyAccessUnlessGranted('add', $partLot);
                    $withdrawAddHelper->add($partLot, $amount, $comment);
                    break;
                case "move":
                    $this->denyAccessUnlessGranted('move', $partLot);
                    $withdrawAddHelper->move($partLot, $targetLot, $amount, $comment);
                    break;
                default:
                    throw new \RuntimeException("Unknown action!");
            }

            //Save the changes to the DB
            $em->flush();
            $this->addFlash('success', 'part.withdraw.success');

        } else {
            $this->addFlash('error', 'CSRF Token invalid!');
        }

        //If an redirect was passed, then redirect there
        if($request->request->get('_redirect')) {
            return $this->redirect($request->request->get('_redirect'));
        }
        //Otherwise just redirect to the part page
        return $this->redirectToRoute('part_info', ['id' => $part->getID()]);
    }
}
