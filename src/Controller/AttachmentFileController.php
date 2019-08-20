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


use App\Entity\Attachments\Attachment;
use App\Services\AttachmentHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class AttachmentFileController extends AbstractController
{

    /**
     * Download the selected attachment
     *
     * @Route("/attachment/{id}/download", name="attachment_download")
     * @param Attachment $attachment
     * @param AttachmentHelper $helper
     * @return BinaryFileResponse
     * @throws \Exception
     */
    public function download(Attachment $attachment, AttachmentHelper $helper)
    {
        $this->denyAccessUnlessGranted('read', $attachment);

        if ($attachment->isExternal()) {
            throw new \RuntimeException('You can not download external attachments!');
        }

        if (!$helper->isFileExisting($attachment)) {
            throw new \RuntimeException('The file associated with the attachment is not existing!');
        }


        $file_path = $helper->toAbsoluteFilePath($attachment);
        $response = new BinaryFileResponse($file_path);

        //Set header content disposition, so that the file will be downloaded
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }

    /**
     * View the attachment
     *
     * @Route("/attachment/{id}/view", name="attachment_view")
     * @param Attachment $attachment
     * @param AttachmentHelper $helper
     * @return BinaryFileResponse
     * @throws \Exception
     */
    public function view(Attachment $attachment, AttachmentHelper $helper)
    {
        $this->denyAccessUnlessGranted('read', $attachment);

        if ($attachment->isExternal()) {
            throw new \RuntimeException('You can not download external attachments!');
        }

        if (!$helper->isFileExisting($attachment)) {
            throw new \RuntimeException('The file associated with the attachment is not existing!');
        }


        $file_path = $helper->toAbsoluteFilePath($attachment);
        $response = new BinaryFileResponse($file_path);

        //Set header content disposition, so that the file will be downloaded
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        return $response;
    }

}