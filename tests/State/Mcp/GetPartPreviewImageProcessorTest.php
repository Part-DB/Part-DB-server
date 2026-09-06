<?php

declare(strict_types=1);

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

namespace App\Tests\State\Mcp;

use ApiPlatform\Metadata\Get;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\ElementByIdInput;
use App\State\Mcp\GetPartPreviewImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GetPartPreviewImageProcessorTest extends WebTestCase
{
    //Smallest possible valid PNG file (1x1 transparent pixel)
    private const PNG_BYTES = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\x0dIDATx\x9cc\xfa\xcf\xc0\xf0\x1f\x00\x05\x05\x02\x01\xa4\xa0\x86\xf1\x00\x00\x00\x00IEND\xaeB`\x82";

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private GetPartPreviewImageProcessor $processor;
    private AttachmentType $attachmentType;
    private Part $part;
    private string $mediaDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->processor = self::getContainer()->get(GetPartPreviewImageProcessor::class);
        $this->filesystem = new Filesystem();

        $this->part = $this->em->getRepository(Part::class)->findOneBy([]);
        self::assertNotNull($this->part, 'Fixtures must contain at least one part');
        //Start every test from a clean slate: no own master picture, no footprint
        $this->part->setMasterPictureAttachment(null);
        $this->part->setFootprint(null);

        $this->attachmentType = new AttachmentType();
        $this->attachmentType->setName('MCP preview test attachment type');
        $this->em->persist($this->attachmentType);
        $this->em->flush();

        $this->mediaDir = self::getContainer()->getParameter('kernel.project_dir').'/public/media/mcp_preview_test';
        $this->filesystem->mkdir($this->mediaDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->mediaDir);
        parent::tearDown();
    }

    private function createInternalPicture(string $filename): string
    {
        $this->filesystem->dumpFile($this->mediaDir.'/'.$filename, self::PNG_BYTES);

        return '%MEDIA%/mcp_preview_test/'.$filename;
    }

    private function getOperation(): Get
    {
        return new Get();
    }

    public function testReturnsPartsOwnMasterPicture(): void
    {
        $attachment = new PartAttachment();
        $attachment->setName('own.png');
        $attachment->setAttachmentType($this->attachmentType);
        $attachment->setElement($this->part);
        $attachment->setInternalPath($this->createInternalPicture('own.png'));
        $this->em->persist($attachment);
        $this->part->setMasterPictureAttachment($attachment);
        $this->em->flush();

        $result = $this->processor->process(new ElementByIdInput(id: $this->part->getID()), $this->getOperation());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertCount(1, $result->content);
        self::assertInstanceOf(ImageContent::class, $result->content[0]);
        self::assertSame('image/png', $result->content[0]->mimeType);
        self::assertSame(base64_encode(self::PNG_BYTES), $result->content[0]->data);
    }

    public function testFallsBackToFootprintPictureWhenPartHasNone(): void
    {
        $footprint = $this->em->getRepository(Footprint::class)->findOneBy([]);
        self::assertNotNull($footprint, 'Fixtures must contain at least one footprint');

        $fpAttachment = new FootprintAttachment();
        $fpAttachment->setName('footprint.png');
        $fpAttachment->setAttachmentType($this->attachmentType);
        $fpAttachment->setElement($footprint);
        $fpAttachment->setInternalPath($this->createInternalPicture('footprint.png'));
        $this->em->persist($fpAttachment);
        $footprint->setMasterPictureAttachment($fpAttachment);

        $this->part->setFootprint($footprint);
        $this->em->flush();

        $result = $this->processor->process(new ElementByIdInput(id: $this->part->getID()), $this->getOperation());

        self::assertInstanceOf(ImageContent::class, $result->content[0]);
        self::assertSame(base64_encode(self::PNG_BYTES), $result->content[0]->data);
    }

    public function testReturnsTextMessageWhenNoPreviewIsAvailable(): void
    {
        $result = $this->processor->process(new ElementByIdInput(id: $this->part->getID()), $this->getOperation());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertCount(1, $result->content);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString('no preview picture', $result->content[0]->text);
    }

    public function testReturnsUrlAsTextForExternallyHostedPreview(): void
    {
        $attachment = new PartAttachment();
        $attachment->setName('external.png');
        $attachment->setAttachmentType($this->attachmentType);
        $attachment->setElement($this->part);
        $attachment->setExternalPath('https://example.com/preview.png');
        $this->em->persist($attachment);
        $this->part->setMasterPictureAttachment($attachment);
        $this->em->flush();

        $result = $this->processor->process(new ElementByIdInput(id: $this->part->getID()), $this->getOperation());

        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString('https://example.com/preview.png', $result->content[0]->text);
    }

    public function testThrowsNotFoundForUnknownId(): void
    {
        $this->em->flush();

        $this->expectException(NotFoundHttpException::class);
        $this->processor->process(new ElementByIdInput(id: 2_000_000_000), $this->getOperation());
    }

    public function testThrowsAccessDeniedForUserWithoutReadPermission(): void
    {
        $this->em->flush();

        $noreadUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'noread']);
        self::assertNotNull($noreadUser, 'Fixtures must contain the "noread" user');
        $this->client->loginUser($noreadUser);

        $this->expectException(AccessDeniedException::class);
        $this->processor->process(new ElementByIdInput(id: $this->part->getID()), $this->getOperation());
    }
}
