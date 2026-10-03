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


namespace App\Tests\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\DTOs\UploadedDocument;
use App\Services\InfoProviderSystem\UploadedDocumentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class UploadedDocumentStorageTest extends TestCase
{
    public function testStoreAndRetrieve(): void
    {
        $storage = new UploadedDocumentStorage(new ArrayAdapter());
        $document = new UploadedDocument('datasheet.pdf', 'Some text');

        $token = $storage->store($document);

        self::assertSame($document->token, $token);
        $retrieved = $storage->retrieve($token);
        self::assertNotNull($retrieved);
        self::assertSame('datasheet.pdf', $retrieved->filename);
        self::assertSame('Some text', $retrieved->text);
    }

    public function testTokenDependsOnContent(): void
    {
        self::assertSame((new UploadedDocument('a.pdf', 'text'))->token, (new UploadedDocument('a.pdf', 'text'))->token);
        self::assertNotSame((new UploadedDocument('a.pdf', 'text'))->token, (new UploadedDocument('a.pdf', 'other'))->token);
        //The same document with another context must not reuse the result of the first one
        self::assertNotSame((new UploadedDocument('a.pdf', 'text'))->token, (new UploadedDocument('a.pdf', 'text', 'BC547C'))->token);
    }

    public function testRetrieveUnknownOrInvalidToken(): void
    {
        $storage = new UploadedDocumentStorage(new ArrayAdapter());

        self::assertNull($storage->retrieve('0123456789abcdef'));
        //Not a token at all, which must not reach the cache as a key
        self::assertNull($storage->retrieve('https://example.com/{foo}'));
    }
}
