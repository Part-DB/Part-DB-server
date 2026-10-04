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


namespace App\Services\InfoProviderSystem\DTOs;

/**
 * Represents the text of a document (like a PDF datasheet) uploaded by a user, held temporarily in the application
 * cache, so that an info provider can extract part information from it.
 */
final readonly class UploadedDocument
{
    /**
     * @var string A unique token for this document, derived from its content and the context. Used as provider ID and to retrieve the document from the cache.
     */
    public string $token;

    public function __construct(
        public string $filename,
        /** @var string The text extracted from the file. Empty, if the file itself is sent to the AI model (like for images and scanned documents). */
        public string $textContent,
        /** @var string|null Additional context given by the user, like the exact part number to extract from a datasheet covering multiple parts */
        public ?string $context = null,
        /** @var int|null The size of the original file in bytes, or null if the original file is not stored (see UploadedDocumentStorage) */
        public ?int $fileSize = null,
        /** @var string|null The MIME type of the original file, determined by its content */
        public ?string $fileMimeType = null,
        /** @var string|null A hash of the content of the original file, to tell files without text (like images) apart */
        public ?string $fileHash = null,
        public \DateTimeImmutable $uploadedAt = new \DateTimeImmutable(),
    ) {
        //The context is part of the token, as the same document with a different context leads to a different result
        $this->token = hash('xxh3', $filename . '|' . $textContent . '|' . ($context ?? '') . '|' . ($fileHash ?? ''));
    }

    /**
     * Whether the original file has to be sent to the AI model, as there is no text extracted from it.
     */
    public function isFileSentToModel(): bool
    {
        return $this->textContent === '';
    }
}
