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


namespace App\Tests\Services\Attachments;

use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\PartAttachment;
use App\Exceptions\AttachmentDownloadException;
use App\Services\Attachments\AttachmentSubmitHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests the handling of files referenced via AttachmentUpload::$uploadedDocumentToken.
 * The successful case is covered end to end by the InfoProviderFromFileTest.
 */
final class AttachmentSubmitHandlerUploadedDocumentTest extends KernelTestCase
{
    public function testUnavailableUploadedDocumentThrows(): void
    {
        self::bootKernel();
        $handler = static::getContainer()->get(AttachmentSubmitHandler::class);

        $this->expectException(AttachmentDownloadException::class);
        $this->expectExceptionMessage('The uploaded file is not available anymore');
        $handler->handleUpload(new PartAttachment(), new AttachmentUpload(file: null, filename: 'datasheet.pdf',
            uploadedDocumentToken: '0123456789abcdef'));
    }

    public function testTokenIsNotWritableViaTheApi(): void
    {
        //Only the server may reference uploaded documents, otherwise API clients could attach files of other users
        $reflection = new \ReflectionProperty(AttachmentUpload::class, 'uploadedDocumentToken');
        self::assertSame([], $reflection->getAttributes(\Symfony\Component\Serializer\Attribute\Groups::class));
    }
}
