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

use App\DataTables\AttachmentDataTable;
use App\DataTables\Filters\AttachmentFilter;
use App\Entity\Attachments\Attachment;
use App\Form\Filters\AttachmentFilterType;
use App\Services\Attachments\AttachmentManager;
use App\Services\Trees\NodesListBuilder;
use Omines\DataTablesBundle\DataTableFactory;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class AttachmentFileController extends AbstractController
{
    /**
     * Download the selected attachment.
     *
     * @Route("/attachment/{id}/download", name="attachment_download")
     */
    public function download(Attachment $attachment, AttachmentManager $helper): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('read', $attachment);

        if ($attachment->isSecure()) {
            $this->denyAccessUnlessGranted('show_private', $attachment);
        }

        if ($attachment->isExternal()) {
            throw new RuntimeException('You can not download external attachments!');
        }

        if (!$helper->isFileExisting($attachment)) {
            throw new RuntimeException('The file associated with the attachment is not existing!');
        }

        $file_path = $helper->toAbsoluteFilePath($attachment);
        $response = new BinaryFileResponse($file_path);

        //Set header content disposition, so that the file will be downloaded
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }

    /**
     * View the attachment.
     *
     * @Route("/attachment/{id}/view", name="attachment_view")
     */
    public function view(Attachment $attachment, AttachmentManager $helper): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('read', $attachment);

        if ($attachment->isSecure()) {
            $this->denyAccessUnlessGranted('show_private', $attachment);
        }

        if ($attachment->isExternal()) {
            throw new RuntimeException('You can not download external attachments!');
        }

        if (!$helper->isFileExisting($attachment)) {
            throw new RuntimeException('The file associated with the attachment is not existing!');
        }

        $file_path = $helper->toAbsoluteFilePath($attachment);
        $response = new BinaryFileResponse($file_path);

        //Set header content disposition, so that the file will be downloaded
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        return $response;
    }

    /**
     * @Route("/attachment/list", name="attachment_list")
     */
    public function attachmentsTable(Request $request, DataTableFactory $dataTableFactory, NodesListBuilder $nodesListBuilder): Response
    {
        $this->denyAccessUnlessGranted('@attachments.list_attachments');

        $formRequest = clone $request;
        $formRequest->setMethod('GET');
        $filter = new AttachmentFilter($nodesListBuilder);

        $filterForm = $this->createForm(AttachmentFilterType::class, $filter, ['method' => 'GET']);

        $filterForm->handleRequest($formRequest);

        $table = $dataTableFactory->createFromType(AttachmentDataTable::class, ['filter' => $filter])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('attachment_list.html.twig', [
            'datatable' => $table,
            'filterForm' => $filterForm->createView(),
        ]);
    }
}
