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


namespace App\Services\InfoProviderSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Attachments\FileTypeFilterTools;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adds the file a user uploaded to create a part from (see UploadedDocumentStorage) as attachment to that part.
 */
final class UploadedDocumentAttachmentHelper
{
    /** @var string The attachment type used for files, which the datasheet type does not allow (like text files) */
    private const DOCUMENT_TYPE_NAME = 'Document';

    public function __construct(
        private readonly UploadedDocumentStorage $documentStorage,
        private readonly AttachmentSubmitHandler $submitHandler,
        private readonly FileTypeFilterTools $filterTools,
        private readonly DTOtoEntityConverter $dtoConverter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Adds an attachment for the file of the uploaded document with the given token to the given part.
     * The attachment only references the file: it is stored when the part is saved (by the AttachmentSubmitHandler),
     * so nothing is stored if the user does not create the part.
     * @return PartAttachment|null The added attachment, or null if the file is not available (anymore) or bigger than
     * the maximum attachment size
     */
    public function attachToPart(Part $part, string $token): ?PartAttachment
    {
        $document = $this->documentStorage->retrieve($token);
        if ($document?->fileSize === null || $document->fileSize > $this->submitHandler->getMaximumUserConfiguredUploadSize()) {
            return null;
        }

        $type = $this->getAttachmentType($document->filename);

        $attachment = new PartAttachment();
        $attachment->setAttachmentType($type);
        $attachment->setName($this->uniqueName($part, $document->filename, $type));
        $attachment->setUpload(new AttachmentUpload(
            file: null,
            filename: $document->filename,
            uploadedDocumentToken: $document->token,
        ));
        $part->addAttachment($attachment);

        return $attachment;
    }

    /**
     * Returns the datasheet type, if it allows the file, otherwise a generic document type without restrictions.
     */
    private function getAttachmentType(string $filename): AttachmentType
    {
        $datasheetType = $this->dtoConverter->getDatasheetType();
        $filter = $datasheetType->getFiletypeFilter();
        if ($filter === '' || $this->filterTools->isExtensionAllowed($filter, pathinfo($filename, PATHINFO_EXTENSION))) {
            return $datasheetType;
        }

        /** @var AttachmentType $type */
        $type = $this->em->getRepository(AttachmentType::class)->findOrCreateForInfoProvider(self::DOCUMENT_TYPE_NAME);
        return $type;
    }

    /**
     * Makes the name unique among the attachments of the same type, as the datasheets found by the info provider
     * could have the same name, which would violate the unique constraint of the attachments.
     */
    private function uniqueName(Part $part, string $name, AttachmentType $type): string
    {
        $existing = [];
        foreach ($part->getAttachments() as $attachment) {
            if ($attachment->getAttachmentType() === $type) {
                $existing[] = $attachment->getName();
            }
        }

        $candidate = $name;
        for ($i = 2; in_array($candidate, $existing, true); $i++) {
            $candidate = $name.' ('.$i.')';
        }

        return $candidate;
    }
}
