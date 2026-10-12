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
readonly class AttachmentUpload
{
    public function __construct(
        /** @var UploadedFile|null The file which was uploaded, or null if the file should not be changed */
        public ?UploadedFile $file,
        /** @var string|null The base64 encoded data of the file which should be uploaded. */
        #[Groups(['attachment:write'])]
        public ?string $data = null,
        /** @vaar string|null The original filename of the file passed in data. */
        #[Groups(['attachment:write'])]
        public ?string $filename = null,
        /** @var bool True, if the URL in the attachment should be downloaded by Part-DB */
        #[Groups(['attachment:write'])]
        public bool $downloadUrl = false,
        /** @var bool If true the file will be moved to private attachment storage,
         * if false it will be moved to public attachment storage. On null file is not moved
         */
        #[Groups(['attachment:write'])]
        public ?bool $private = null,
        /** @var bool If true and no preview image was set yet, the new uploaded file will become the preview image */
        #[Groups(['attachment:write'])]
        public ?bool $becomePreviewIfEmpty = true,
        /** @var string|null The token of a file, which a user uploaded to create a part from (see UploadedDocumentStorage).
         * The file is only loaded when the upload is handled, as it can be big. Its original filename is given in $filename.
         * This is deliberately not writable via the API, it is only set by the server. */
        public ?string $uploadedDocumentToken = null,
    ) {
    }

    /**
     * Creates an AttachmentUpload object from an Attachment FormInterface
     * @param  FormInterface  $form
     * @return AttachmentUpload
     */
    public static function fromAttachmentForm(FormInterface $form): self
    {
        if (!$form->has('file')) {
            throw new \InvalidArgumentException('The form does not have a file field. Is it an attachment form?');
        }

        $file = $form->get('file')->getData();

        //The server can prepare an attachment with a file (like the file a part was created from), which is used,
        //unless the user uploaded another file or entered a URL in the form
        $attachment = $form->getData();
        $prepared = $attachment instanceof Attachment ? $attachment->getUpload() : null;
        $usePrepared = $file === null && $prepared?->uploadedDocumentToken !== null && !$attachment->hasExternal();

        return new self(
            file: $file,
            filename: $usePrepared ? $prepared->filename : null,
            downloadUrl: $form->get('downloadURL')->getData(),
            private: $form->get('secureFile')->getData(),
            uploadedDocumentToken: $usePrepared ? $prepared->uploadedDocumentToken : null,
        );

    }
}
