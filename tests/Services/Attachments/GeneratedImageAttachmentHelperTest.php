<?php

declare(strict_types=1);

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

namespace App\Tests\Services\Attachments;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use App\Repository\StructuralDBElementRepository;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Attachments\GeneratedImageAttachmentHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GeneratedImageAttachmentHelperTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private AttachmentSubmitHandler&MockObject $submitHandler;
    private StructuralDBElementRepository&MockObject $repository;
    private GeneratedImageAttachmentHelper $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->submitHandler = $this->createMock(AttachmentSubmitHandler::class);
        $this->repository = $this->createMock(StructuralDBElementRepository::class);

        $this->em->method('getRepository')
            ->with(AttachmentType::class)
            ->willReturn($this->repository);

        $this->service = new GeneratedImageAttachmentHelper($this->em, $this->submitHandler);
    }

    /**
     * Configures the submit handler mock to behave like a successful upload of a picture file.
     * Returns an ArrayObject that gets filled with the AttachmentUpload instances the handler
     * was called with (in call order) as the test runs.
     *
     * @return \ArrayObject<int, AttachmentUpload>
     */
    private function mockSuccessfulPictureUpload(): \ArrayObject
    {
        $seenUploads = new \ArrayObject();
        $this->submitHandler->method('handleUpload')
            ->willReturnCallback(function (PartAttachment $attachment, ?AttachmentUpload $upload) use ($seenUploads) {
                $seenUploads[] = $upload;
                $attachment->setInternalPath('attachments/1/generated.svg');

                return $attachment;
            });

        return $seenUploads;
    }

    private function setId(object $entity, ?int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $reflection->getProperty('id')->setValue($entity, $id);
    }

    /**
     * Builds a fresh AttachmentType named "Generated image" and makes it the result of
     * getGeneratedImageType(). $persisted controls whether it already has an ID (i.e. already exists in DB).
     */
    private function mockGeneratedImageType(bool $persisted): AttachmentType
    {
        $type = new AttachmentType();
        $type->setName('Generated image');
        if ($persisted) {
            $this->setId($type, 5);
        }

        $this->repository->method('findOrCreateForInfoProvider')
            ->with('Generated image')
            ->willReturn($type);

        return $type;
    }

    public function testAttachSvgToPartCreatesAttachmentWithGivenNameAndType(): void
    {
        $this->mockGeneratedImageType(persisted: true);
        $uploads = $this->mockSuccessfulPictureUpload();

        $part = new Part();
        $attachment = $this->service->attachSvgToPart($part, '<svg></svg>', 'My resistor');

        $this->assertSame('My resistor', $attachment->getName());
        $this->assertSame('Generated image', $attachment->getAttachmentType()?->getName());
        $this->assertTrue($part->getAttachments()->contains($attachment));

        //The upload pipeline must receive the base64 encoded SVG data as a generated.svg file
        $this->assertCount(1, $uploads);
        $this->assertSame(base64_encode('<svg></svg>'), $uploads[0]->data);
        $this->assertSame('generated.svg', $uploads[0]->filename);
        $this->assertNull($uploads[0]->file);
    }

    public function testAttachSvgToPartFallsBackToDefaultNameWhenNameIsEmpty(): void
    {
        $this->mockGeneratedImageType(persisted: true);
        $this->mockSuccessfulPictureUpload();

        $part = new Part();
        $attachment = $this->service->attachSvgToPart($part, '<svg></svg>', '');

        $this->assertSame('Generated image', $attachment->getName());
    }

    public function testAttachSvgToPartSetsAsPreviewByDefault(): void
    {
        $this->mockGeneratedImageType(persisted: true);
        $uploads = $this->mockSuccessfulPictureUpload();

        $part = new Part();
        $attachment = $this->service->attachSvgToPart($part, '<svg></svg>', 'Preview me');

        $this->assertTrue($uploads[0]->becomePreviewIfEmpty);
        $this->assertSame($attachment, $part->getMasterPictureAttachment());
    }

    public function testAttachSvgToPartDoesNotSetPreviewWhenNotRequested(): void
    {
        $this->mockGeneratedImageType(persisted: true);
        $uploads = $this->mockSuccessfulPictureUpload();

        $part = new Part();
        $this->service->attachSvgToPart($part, '<svg></svg>', 'No preview', setAsPreview: false);

        $this->assertFalse($uploads[0]->becomePreviewIfEmpty);
        $this->assertNull($part->getMasterPictureAttachment());
    }

    public function testAttachSvgToPartPersistsTheNewAttachment(): void
    {
        $this->mockGeneratedImageType(persisted: true);
        $this->mockSuccessfulPictureUpload();

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $part = new Part();
        $attachment = $this->service->attachSvgToPart($part, '<svg></svg>', 'Test');

        $this->assertContains($attachment, $persisted);
    }

    public function testAttachSvgToPartDeduplicatesNameOnCollision(): void
    {
        $type = $this->mockGeneratedImageType(persisted: true);
        $this->mockSuccessfulPictureUpload();

        $part = new Part();

        $existing = new PartAttachment();
        $existing->setName('Resistor');
        $existing->setAttachmentType($type);
        $part->addAttachment($existing);

        $attachment = $this->service->attachSvgToPart($part, '<svg></svg>', 'Resistor');

        $this->assertSame('Resistor (2)', $attachment->getName());
    }

    public function testAttachSvgToPartDeduplicatesNameSkippingAlreadyTakenNumbers(): void
    {
        $type = $this->mockGeneratedImageType(persisted: true);
        $this->mockSuccessfulPictureUpload();

        $part = new Part();

        foreach (['Resistor', 'Resistor (2)'] as $name) {
            $existing = new PartAttachment();
            $existing->setName($name);
            $existing->setAttachmentType($type);
            $part->addAttachment($existing);
        }

        $attachment = $this->service->attachSvgToPart($part, '<svg></svg>', 'Resistor');

        $this->assertSame('Resistor (3)', $attachment->getName());
    }

    public function testAttachSvgToPartDoesNotDeduplicateAgainstOtherAttachmentTypes(): void
    {
        $this->mockGeneratedImageType(persisted: true);
        $this->mockSuccessfulPictureUpload();

        $part = new Part();

        $otherType = new AttachmentType();
        $otherType->setName('Datasheet');

        $existing = new PartAttachment();
        $existing->setName('Resistor');
        $existing->setAttachmentType($otherType);
        $part->addAttachment($existing);

        $attachment = $this->service->attachSvgToPart($part, '<svg></svg>', 'Resistor');

        $this->assertSame('Resistor', $attachment->getName());
    }

    public function testAttachSvgToPartWithoutOverwriteKeepsPreviousGeneratedImages(): void
    {
        $type = $this->mockGeneratedImageType(persisted: true);
        $this->mockSuccessfulPictureUpload();

        $part = new Part();
        $previous = new PartAttachment();
        $previous->setName('Generated image');
        $previous->setAttachmentType($type);
        $part->addAttachment($previous);

        $this->em->expects($this->never())->method('remove');

        $this->service->attachSvgToPart($part, '<svg></svg>', 'Generated image', overwrite: false);

        $this->assertTrue($part->getAttachments()->contains($previous));
    }

    public function testAttachSvgToPartWithOverwriteRemovesPreviousGeneratedImagesOnly(): void
    {
        $type = $this->mockGeneratedImageType(persisted: true);
        $this->mockSuccessfulPictureUpload();

        $part = new Part();

        $previousGenerated = new PartAttachment();
        $previousGenerated->setName('Generated image');
        $previousGenerated->setAttachmentType($type);
        $part->addAttachment($previousGenerated);
        $part->setMasterPictureAttachment($previousGenerated);

        $otherType = new AttachmentType();
        $otherType->setName('Datasheet');
        $unrelated = new PartAttachment();
        $unrelated->setName('Datasheet');
        $unrelated->setAttachmentType($otherType);
        $part->addAttachment($unrelated);

        $removed = [];
        $this->em->method('remove')->willReturnCallback(function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });

        $newAttachment = $this->service->attachSvgToPart($part, '<svg></svg>', 'Generated image', overwrite: true);

        $this->assertSame([$previousGenerated], $removed);
        $this->assertFalse($part->getAttachments()->contains($previousGenerated));
        $this->assertTrue($part->getAttachments()->contains($unrelated));
        $this->assertTrue($part->getAttachments()->contains($newAttachment));
        //The new attachment should have become the master picture, replacing the removed one
        $this->assertSame($newAttachment, $part->getMasterPictureAttachment());
        //Since the old master picture was removed, the name should not need de-duplication
        $this->assertSame('Generated image', $newAttachment->getName());
    }

    public function testGetGeneratedImageTypeConfiguresAndPersistsNewlyCreatedType(): void
    {
        $type = $this->mockGeneratedImageType(persisted: false);
        $this->mockSuccessfulPictureUpload();

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $part = new Part();
        $this->service->attachSvgToPart($part, '<svg></svg>', 'Test');

        $this->assertSame('image/*', $type->getFiletypeFilter());
        $this->assertSame('Generated image', $type->getAlternativeNames());
        $this->assertContains($type, $persisted);
    }

    public function testGetGeneratedImageTypeDoesNotReconfigureOrPersistExistingType(): void
    {
        $type = $this->mockGeneratedImageType(persisted: true);
        $type->setFiletypeFilter('');
        $type->setAlternativeNames(null);
        $this->mockSuccessfulPictureUpload();

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $part = new Part();
        $this->service->attachSvgToPart($part, '<svg></svg>', 'Test');

        //Existing types must not be modified...
        $this->assertSame('', $type->getFiletypeFilter());
        $this->assertNull($type->getAlternativeNames());
        //...nor persisted again (only the new attachment should be persisted)
        $this->assertNotContains($type, $persisted);
    }
}
