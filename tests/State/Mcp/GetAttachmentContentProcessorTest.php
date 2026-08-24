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
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\ElementByIdInput;
use App\Services\Attachments\AttachmentManager;
use App\State\Mcp\GetAttachmentContentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\BlobResourceContents;
use Mcp\Schema\Content\EmbeddedResource;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GetAttachmentContentProcessorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private GetAttachmentContentProcessor $processor;
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
        $this->processor = self::getContainer()->get(GetAttachmentContentProcessor::class);
        $this->filesystem = new Filesystem();

        $this->part = $this->em->getRepository(Part::class)->findOneBy([]);
        self::assertNotNull($this->part, 'Fixtures must contain at least one part');

        $this->attachmentType = new AttachmentType();
        $this->attachmentType->setName('MCP test attachment type');
        $this->em->persist($this->attachmentType);
        $this->em->flush();

        $this->mediaDir = self::getContainer()->getParameter('kernel.project_dir').'/public/media/mcp_test';
        $this->filesystem->mkdir($this->mediaDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->mediaDir);
        parent::tearDown();
    }

    private function createInternalAttachment(string $filename, string $content): PartAttachment
    {
        $this->filesystem->dumpFile($this->mediaDir.'/'.$filename, $content);

        $attachment = new PartAttachment();
        $attachment->setName($filename);
        $attachment->setAttachmentType($this->attachmentType);
        $attachment->setElement($this->part);
        $attachment->setInternalPath('%MEDIA%/mcp_test/'.$filename);

        $this->em->persist($attachment);
        $this->em->flush();

        return $attachment;
    }

    private function getOperation(): Get
    {
        return new Get();
    }

    public function testTextFileIsReturnedAsEmbeddedTextResource(): void
    {
        $attachment = $this->createInternalAttachment('notes.txt', 'Hello world');

        $result = $this->processor->process(new ElementByIdInput(id: $attachment->getID()), $this->getOperation());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertCount(2, $result->content);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertInstanceOf(EmbeddedResource::class, $result->content[1]);

        $resource = $result->content[1]->resource;
        self::assertInstanceOf(TextResourceContents::class, $resource);
        self::assertSame('Hello world', $resource->text);
        self::assertSame('text/plain', $resource->mimeType);
        self::assertStringContainsString((string) $attachment->getID(), $resource->uri);
    }

    public function testPictureIsReturnedAsImageContent(): void
    {
        //Smallest possible valid PNG file (1x1 transparent pixel)
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $attachment = $this->createInternalAttachment('pixel.png', $png);

        $result = $this->processor->process(new ElementByIdInput(id: $attachment->getID()), $this->getOperation());

        self::assertCount(2, $result->content);
        self::assertInstanceOf(ImageContent::class, $result->content[1]);
        self::assertSame('image/png', $result->content[1]->mimeType);
        self::assertSame(base64_encode($png), $result->content[1]->data);
    }

    public function testBinaryNonPictureFileIsReturnedAsBlobResource(): void
    {
        $attachment = $this->createInternalAttachment('data.bin', "\x00\x01\x02\xFF");

        $result = $this->processor->process(new ElementByIdInput(id: $attachment->getID()), $this->getOperation());

        $resource = $result->content[1]->resource;
        self::assertInstanceOf(BlobResourceContents::class, $resource);
        self::assertSame(base64_encode("\x00\x01\x02\xFF"), $resource->blob);
    }

    public function testThrowsNotFoundForUnknownId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->processor->process(new ElementByIdInput(id: 2_000_000_000), $this->getOperation());
    }

    public function testThrowsBadRequestForExternalOnlyAttachment(): void
    {
        $attachment = new PartAttachment();
        $attachment->setName('External');
        $attachment->setAttachmentType($this->attachmentType);
        $attachment->setElement($this->part);
        $attachment->setExternalPath('https://example.com/datasheet.pdf');
        $this->em->persist($attachment);
        $this->em->flush();

        $this->expectException(BadRequestHttpException::class);
        $this->processor->process(new ElementByIdInput(id: $attachment->getID()), $this->getOperation());
    }

    public function testThrowsBadRequestWhenFileExceedsSizeLimit(): void
    {
        $attachment = $this->createInternalAttachment('too_big.txt', str_repeat('a', 128));

        $oversizedProcessor = new class(
            $this->em,
            self::getContainer()->get(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class),
            self::getContainer()->get(AttachmentManager::class),
        ) extends GetAttachmentContentProcessor {
            protected const MAX_FILE_SIZE = 10;
        };

        $this->expectException(BadRequestHttpException::class);
        $oversizedProcessor->process(new ElementByIdInput(id: $attachment->getID()), $this->getOperation());
    }

    public function testThrowsAccessDeniedForUserWithoutReadPermission(): void
    {
        $attachment = $this->createInternalAttachment('secret.txt', 'top secret');

        $noreadUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'noread']);
        self::assertNotNull($noreadUser, 'Fixtures must contain the "noread" user');
        $this->client->loginUser($noreadUser);

        $this->expectException(AccessDeniedException::class);
        $this->processor->process(new ElementByIdInput(id: $attachment->getID()), $this->getOperation());
    }
}
