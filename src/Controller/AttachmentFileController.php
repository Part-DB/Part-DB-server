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
use App\DataTables\PartsDataTable;
use App\Entity\Attachments\Attachment;
use App\Form\Filters\AttachmentFilterType;
use App\Services\Attachments\AttachmentManager;
use App\Services\Trees\NodesListBuilder;
use App\Settings\BehaviorSettings\TableSettings;
use App\Settings\SystemSettings\AttachmentsSettings;
use Omines\DataTablesBundle\DataTableFactory;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class AttachmentFileController extends AbstractController
{

    public function __construct(private readonly AttachmentManager $helper)
    {

    }

    #[Route(path: '/attachment/{id}/sandbox', name: 'attachment_html_sandbox')]
    public function htmlSandbox(Attachment $attachment, AttachmentsSettings $attachmentsSettings): Response
    {
        //Check if the sandbox is enabled in the settings, as it can be a security risk if used without proper precautions, so it should be opt-in
        if (!$attachmentsSettings->showHTMLAttachments) {
            throw $this->createAccessDeniedException('The HTML sandbox for attachments is disabled in the settings, as it can be a security risk if used without proper precautions. Please enable it in the settings if you want to use it.');
        }

        $this->checkPermissions($attachment);

        $file_path = $this->helper->toAbsoluteInternalFilePath($attachment);

        $attachmentContent = file_get_contents($file_path);

        $response = $this->render('attachments/html_sandbox.html.twig', [
            'attachment' => $attachment,
            'content' => $attachmentContent,
        ]);

        //Set an CSP that allows to run inline scripts, styles and images from external ressources, but does not allow any connections or others.
        //Also set the sandbox CSP directive with only "allow-script" to run basic scripts
        $response->headers->set('Content-Security-Policy', "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; sandbox allow-scripts;");

        //Forbid to embed the attachment render page in an iframe to prevent clickjacking, as it is not used anywhere else for now
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }

    /**
     * Download the selected attachment.
     */
    #[Route(path: '/attachment/{id}/download', name: 'attachment_download')]
    public function download(Attachment $attachment): BinaryFileResponse
    {
        $this->checkPermissions($attachment);

        $file_path = $this->helper->toAbsoluteInternalFilePath($attachment);
        $response = new BinaryFileResponse($file_path);

        //Set header content disposition, so that the file will be downloaded
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }

    /**
     * View the attachment.
     */
    #[Route(path: '/attachment/{id}/view', name: 'attachment_view')]
    public function view(Attachment $attachment): BinaryFileResponse
    {
        $this->checkPermissions($attachment);

        $file_path = $this->helper->toAbsoluteInternalFilePath($attachment);
        $response = new BinaryFileResponse($file_path);

        //Set header content disposition, so that the file will be downloaded
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        return $response;
    }

    private function checkPermissions(Attachment $attachment): void
    {
        $this->denyAccessUnlessGranted('read', $attachment);

        if ($attachment->isSecure()) {
            $this->denyAccessUnlessGranted('show_private', $attachment);
        }

        if (!$attachment->hasInternal()) {
            throw $this->createNotFoundException('The file for this attachment is external and not stored locally!');
        }

        if (!$this->helper->isInternalFileExisting($attachment)) {
            throw $this->createNotFoundException('The file associated with the attachment is not existing!');
        }
    }

    #[Route(path: '/attachment/list', name: 'attachment_list')]
    public function attachmentsTable(Request $request, DataTableFactory $dataTableFactory, NodesListBuilder $nodesListBuilder,
        TableSettings $tableSettings): Response
    {
        $this->denyAccessUnlessGranted('@attachments.list_attachments');

        $formRequest = clone $request;
        $formRequest->setMethod('GET');
        $filter = new AttachmentFilter($nodesListBuilder);

        $filterForm = $this->createForm(AttachmentFilterType::class, $filter, ['method' => 'GET']);

        $filterForm->handleRequest($formRequest);

        $table = $dataTableFactory->createFromType(AttachmentDataTable::class, ['filter' => $filter], ['pageLength' => $tableSettings->fullDefaultPageSize, 'lengthMenu' => PartsDataTable::LENGTH_MENU])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('attachment_list.html.twig', [
            'datatable' => $table,
            'filterForm' => $filterForm,
        ]);
    }
}
