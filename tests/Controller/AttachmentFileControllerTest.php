<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Services\Attachments\AttachmentPathResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group("slow")]
#[Group("DB")]
final class AttachmentFileControllerTest extends WebTestCase
{
    /**
     * A minimal ASCII STL file (a single triangle), so that we do not have to ship a binary fixture.
     */
    private const STL_CONTENT = <<<'STL'
        solid triangle
        facet normal 0 0 1
          outer loop
            vertex 0 0 0
            vertex 1 0 0
            vertex 0 1 0
          endloop
        endfacet
        endsolid triangle
        STL;

    private ?string $created_file = null;
    private ?PartAttachment $created_attachment = null;
    private ?EntityManagerInterface $entity_manager = null;

    protected function tearDown(): void
    {
        //Do not leave the attachment (whose file is removed below) behind for the other tests
        if ($this->created_attachment !== null && $this->entity_manager !== null) {
            $this->entity_manager->remove($this->created_attachment);
            $this->entity_manager->flush();
        }
        $this->created_attachment = null;
        $this->entity_manager = null;

        if ($this->created_file !== null && file_exists($this->created_file)) {
            unlink($this->created_file);
        }
        $this->created_file = null;

        parent::tearDown();
    }

    public function testModelViewerIsShownForA3DModel(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $attachment = $this->createAttachmentWithFile($client, 'stl', self::STL_CONTENT);

        $crawler = $client->request('GET', '/en/attachment/'.$attachment->getID().'/3d');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        //The viewer is set up by the model_viewer stimulus controller, which needs to know where to get the file from
        $viewer = $crawler->filter('[data-controller="elements--model-viewer"]');
        $this->assertCount(1, $viewer);
        $this->assertSame('/en/attachment/'.$attachment->getID().'/view',
            $viewer->attr('data-elements--model-viewer-url-value'));
        $this->assertSame('stl', $viewer->attr('data-elements--model-viewer-extension-value'));
    }

    public function testModelViewerIsNotShownForOtherFiles(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $attachment = $this->createAttachmentWithFile($client, 'txt', 'Not a 3D model');

        $client->request('GET', '/en/attachment/'.$attachment->getID().'/3d');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Creates a part attachment (including the file it points to) with the given file extension and content.
     */
    private function createAttachmentWithFile(KernelBrowser $client, string $extension, string $content): PartAttachment
    {
        $container = $client->getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine')->getManager();

        $part = $entityManager->getRepository(Part::class)->find(1);
        $attachment_type = $entityManager->getRepository(AttachmentType::class)->findOneBy([]);
        if (!$part || !$attachment_type) {
            $this->markTestSkipped('Test part or attachment type not found in fixtures');
        }

        $filename = 'phpunit_model_viewer_'.uniqid('', false).'.'.$extension;
        $this->created_file = $container->get(AttachmentPathResolver::class)->placeholderToRealPath('%MEDIA%/'.$filename);
        file_put_contents($this->created_file, $content);

        $attachment = new PartAttachment();
        $attachment->setName('Test model');
        $attachment->setAttachmentType($attachment_type);
        $attachment->setInternalPath('%MEDIA%/'.$filename);
        $attachment->setElement($part);

        $entityManager->persist($attachment);
        $entityManager->flush();

        $this->entity_manager = $entityManager;
        $this->created_attachment = $attachment;

        return $attachment;
    }

    private function loginAsUser(KernelBrowser $client, string $username): void
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => $username]);

        if (!$user) {
            $this->markTestSkipped("User {$username} not found");
        }

        $client->loginUser($user);
    }
}
