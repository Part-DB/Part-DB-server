<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Entity\Attachments;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * This is a DTO representing a file upload for an attachment and which is used to pass data to the Attachment
 * submit handler service.
 */
class AttachmentUpload
{
    public function __construct(
        /** @var UploadedFile|null The file which was uploaded, or null if the file should not be changed */
        public readonly ?UploadedFile $file,
        /** @var string|null The base64 encoded data of the file which should be uploaded. */
        #[Groups(['attachment:write'])]
        public readonly ?string $data = null,
        /** @vaar string|null The original filename of the file passed in data. */
        #[Groups(['attachment:write'])]
        public readonly ?string $filename = null,
        /** @var bool True, if the URL in the attachment should be downloaded by Part-DB */
        #[Groups(['attachment:write'])]
        public readonly bool $downloadUrl = false,
        /** @var bool If true the file will be moved to private attachment storage,
         * if false it will be moved to public attachment storage. On null file is not moved
         */
        #[Groups(['attachment:write'])]
        public readonly ?bool $private = null,
        /** @var bool If true and no preview image was set yet, the new uploaded file will become the preview image */
        #[Groups(['attachment:write'])]
        public readonly ?bool $becomePreviewIfEmpty = true,
    ) {
    }

    /**
     * Creates an AttachmentUpload object from an Attachment FormInterface
     * @param  FormInterface  $form
     * @return AttachmentUpload
     */
    public static function fromAttachmentForm(FormInterface $form): AttachmentUpload
    {
        if (!$form->has('file')) {
            throw new \InvalidArgumentException('The form does not have a file field. Is it an attachment form?');
        }

        return new self(
            file: $form->get('file')->getData(),
            downloadUrl: $form->get('downloadURL')->getData(),
            private: $form->get('secureFile')->getData()
        );

    }
}