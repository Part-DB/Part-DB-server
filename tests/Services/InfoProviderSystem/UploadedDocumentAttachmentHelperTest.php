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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use App\Repository\StructuralDBElementRepository;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Attachments\FileTypeFilterTools;
use App\Services\InfoProviderSystem\DTOs\UploadedDocument;
use App\Services\InfoProviderSystem\DTOtoEntityConverter;
use App\Services\InfoProviderSystem\UploadedDocumentAttachmentHelper;
use App\Services\InfoProviderSystem\UploadedDocumentStorage;
use App\Settings\SystemSettings\LocalizationSettings;
use App\Tests\SettingsTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\MimeTypes;

final class UploadedDocumentAttachmentHelperTest extends TestCase
{
    private string $tempDir;
    private UploadedDocumentStorage $storage;
    private UploadedDocumentAttachmentHelper $helper;
    private AttachmentType $datasheetType;
    private AttachmentType $documentType;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/partdb_attachment_helper_test_'.bin2hex(random_bytes(8));
        mkdir($this->tempDir);
        $this->storage = new UploadedDocumentStorage(new ArrayAdapter(), $this->tempDir.'/documents');

        //The datasheet type of the info providers only allows PDFs
        $this->datasheetType = (new AttachmentType())->setName('Datasheet')->setFiletypeFilter('application/pdf');
        $this->documentType = (new AttachmentType())->setName('Document');

        $repository = $this->createMock(StructuralDBElementRepository::class);
        $repository->method('findOrCreateForInfoProvider')->willReturnCallback(fn(string $name) => match ($name) {
            'Datasheet' => $this->datasheetType,
            'Document' => $this->documentType,
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(AttachmentType::class)->willReturn($repository);

        $submitHandler = $this->createMock(AttachmentSubmitHandler::class);
        $submitHandler->method('getMaximumUserConfiguredUploadSize')->willReturn(1000);

        $this->helper = new UploadedDocumentAttachmentHelper(
            $this->storage,
            $submitHandler,
            new FileTypeFilterTools(new MimeTypes(), new ArrayAdapter()),
            new DTOtoEntityConverter($em, SettingsTestHelper::createSettingsDummy(LocalizationSettings::class)),
            $em,
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    private function storeDocument(string $filename, string $content = 'file content'): string
    {
        $path = tempnam($this->tempDir, 'upload');
        file_put_contents($path, $content);

        return $this->storage->store(new UploadedDocument($filename, 'extracted text '.$filename, null, strlen($content)), new File($path));
    }

    public function testAttachPdf(): void
    {
        $part = new Part();
        $token = $this->storeDocument('bc547.pdf');

        $attachment = $this->helper->attachToPart($part, $token);

        self::assertInstanceOf(PartAttachment::class, $attachment);
        self::assertTrue($part->getAttachments()->contains($attachment));
        self::assertSame('bc547.pdf', $attachment->getName());
        self::assertSame($this->datasheetType, $attachment->getAttachmentType());

        //Only a reference to the file, which is loaded and stored by the submit handler when the part is saved
        self::assertFalse($attachment->hasInternal());
        self::assertFalse($attachment->hasExternal());
        self::assertSame($token, $attachment->getUpload()?->uploadedDocumentToken);
        self::assertSame('bc547.pdf', $attachment->getUpload()?->filename);
        self::assertNull($attachment->getUpload()?->data);
    }

    public function testFilesNotAllowedForDatasheetsGetTheDocumentType(): void
    {
        //Filing a Markdown file as datasheet would violate the file type filter of the datasheet type
        $attachment = $this->helper->attachToPart(new Part(), $this->storeDocument('bc547.md'));

        self::assertSame($this->documentType, $attachment?->getAttachmentType());
    }

    public function testDatasheetTypeWithoutFilterIsUsedForEveryFile(): void
    {
        //An existing type, whose filter the user removed (a newly created one always gets the PDF filter)
        (new \ReflectionProperty(AttachmentType::class, 'id'))->setValue($this->datasheetType, 1);
        $this->datasheetType->setFiletypeFilter('');

        $attachment = $this->helper->attachToPart(new Part(), $this->storeDocument('bc547.txt'));

        self::assertSame($this->datasheetType, $attachment?->getAttachmentType());
    }

    public function testNameIsMadeUnique(): void
    {
        //A datasheet found by the info provider could have the same name, which would violate the unique constraint
        $part = new Part();
        foreach (['bc547.pdf', 'bc547.pdf (2)'] as $name) {
            $part->addAttachment((new PartAttachment())->setName($name)->setAttachmentType($this->datasheetType));
        }
        //Same name, but another type: no conflict
        $part->addAttachment((new PartAttachment())->setName('bc547.pdf (3)')->setAttachmentType($this->documentType));

        $attachment = $this->helper->attachToPart($part, $this->storeDocument('bc547.pdf'));

        self::assertSame('bc547.pdf (3)', $attachment?->getName());
    }

    public function testUnknownDocumentIsNotAttached(): void
    {
        $part = new Part();

        self::assertNull($this->helper->attachToPart($part, '0123456789abcdef'));
        self::assertCount(0, $part->getAttachments());
    }

    public function testDocumentWithoutFileIsNotAttached(): void
    {
        $part = new Part();
        $token = $this->storage->store(new UploadedDocument('bc547.pdf', 'extracted text'));

        self::assertNull($this->helper->attachToPart($part, $token));
        self::assertCount(0, $part->getAttachments());
    }

    public function testTooBigFileIsNotAttached(): void
    {
        //Saving the part would fail otherwise, as the submit handler rejects files above the maximum size
        $part = new Part();

        self::assertNull($this->helper->attachToPart($part, $this->storeDocument('bc547.pdf', str_repeat('x', 1001))));
        self::assertCount(0, $part->getAttachments());

        self::assertNotNull($this->helper->attachToPart($part, $this->storeDocument('other.pdf', str_repeat('x', 1000))));
    }
}
