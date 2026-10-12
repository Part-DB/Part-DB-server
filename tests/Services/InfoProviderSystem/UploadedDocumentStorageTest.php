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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

final class UploadedDocumentStorageTest extends TestCase
{
    private string $tempDir;
    private string $fileDirectory;
    private UploadedDocumentStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/partdb_document_storage_test_'.bin2hex(random_bytes(8));
        mkdir($this->tempDir);
        //Not created yet: the storage has to create it on the first upload
        $this->fileDirectory = $this->tempDir.'/uploaded_documents';
        $this->storage = new UploadedDocumentStorage(new ArrayAdapter(), $this->fileDirectory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    private function createFile(string $content): File
    {
        $path = tempnam($this->tempDir, 'upload');
        file_put_contents($path, $content);

        return new File($path);
    }

    /**
     * Stores a document with the given original file content.
     */
    private function storeWithFile(string $content, string $text = 'Some text'): string
    {
        return $this->storage->store(new UploadedDocument('datasheet.pdf', $text, null, strlen($content)), $this->createFile($content));
    }

    public function testStoreAndRetrieve(): void
    {
        $document = new UploadedDocument('datasheet.pdf', 'Some text');

        $token = $this->storage->store($document);

        self::assertSame($document->token, $token);
        $retrieved = $this->storage->retrieve($token);
        self::assertNotNull($retrieved);
        self::assertSame('datasheet.pdf', $retrieved->filename);
        self::assertSame('Some text', $retrieved->textContent);
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
        self::assertNull($this->storage->retrieve('0123456789abcdef'));
        //Not a token at all, which must not reach the cache as a key
        self::assertNull($this->storage->retrieve('https://example.com/{foo}'));
    }

    public function testStoreWithFile(): void
    {
        $content = "%PDF-1.7 binary \0\xff content";
        $file = $this->createFile($content);

        $token = $this->storage->store(new UploadedDocument('datasheet.pdf', 'Some text', null, strlen($content)), $file);

        $path = $this->storage->retrieveFilePath($token);
        self::assertNotNull($path);
        self::assertStringStartsWith($this->fileDirectory, $path);
        self::assertSame($content, file_get_contents($path));
        self::assertSame(strlen($content), $this->storage->retrieve($token)?->fileSize);
        //The file is moved, not copied (and never loaded into memory)
        self::assertFileDoesNotExist($file->getPathname());
    }

    public function testFileIsOptional(): void
    {
        $token = $this->storage->store(new UploadedDocument('datasheet.pdf', 'Some text'));

        self::assertNotNull($this->storage->retrieve($token));
        self::assertNull($this->storage->retrieveFilePath($token));
    }

    public function testFileSizeHasToMatchTheFile(): void
    {
        //The size is checked against the attachment size limit, without touching the file
        $this->expectException(\InvalidArgumentException::class);
        $this->storage->store(new UploadedDocument('datasheet.pdf', 'Some text', null, 3), $this->createFile('content'));
    }

    public function testStoringTheSameFileAgain(): void
    {
        //Uploading the same file twice results in the same token, which must not fail
        $first = $this->storeWithFile('content');
        $second = $this->storeWithFile('content');

        self::assertSame($first, $second);
        self::assertSame('content', file_get_contents($this->storage->retrieveFilePath($second)));
    }

    public function testRetrieveFilePathOfUnknownOrInvalidToken(): void
    {
        $this->storeWithFile('content');
        //A file next to the storage directory, which must not be reachable via a token
        file_put_contents($this->tempDir.'/secret', 'secret');

        self::assertNull($this->storage->retrieveFilePath('0123456789abcdef'));
        self::assertNull($this->storage->retrieveFilePath('../secret'));
        self::assertNull($this->storage->retrieveFilePath(''));
    }

    public function testExpiredFileIsNotReturned(): void
    {
        $token = $this->storeWithFile('content');
        touch($this->storage->retrieveFilePath($token), time() - 7201);

        self::assertNull($this->storage->retrieveFilePath($token));
    }

    public function testExpiredFilesAreDeletedOnStore(): void
    {
        //Files of uploads, for which no part was created, are never read again, so they are cleaned up on the next upload
        $abandoned = $this->storeWithFile('abandoned', 'abandoned');
        $abandonedPath = $this->storage->retrieveFilePath($abandoned);
        touch($abandonedPath, time() - 7201);

        $recent = $this->storeWithFile('recent', 'recent');
        $recentPath = $this->storage->retrieveFilePath($recent);
        touch($recentPath, time() - 3600);

        $this->storeWithFile('new', 'new');

        self::assertFileDoesNotExist($abandonedPath);
        self::assertFileExists($recentPath);
        self::assertSame('recent', file_get_contents($this->storage->retrieveFilePath($recent)));
    }
}
