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

namespace App\Tests\Services\Trees;

use App\Entity\Attachments\AttachmentType;
use App\Services\Cache\ElementCacheTagGenerator;
use App\Services\Trees\StructuralElementPathCache;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @Group DB
 */
final class StructuralElementPathCacheTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StructuralElementPathCache $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(StructuralElementPathCache::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        //The database changes are rolled back after each test, but the cache is not, so clear it to not leak paths
        $tag = self::getContainer()->get(ElementCacheTagGenerator::class)->getElementTypeCacheTag(AttachmentType::class);
        self::getContainer()->get(TagAwareCacheInterface::class)->invalidateTags([$tag]);

        parent::tearDown();
    }

    private function getNode(string $name): AttachmentType
    {
        return $this->em->getRepository(AttachmentType::class)->findOneBy(['name' => $name]);
    }

    public function testGetFullPath(): void
    {
        $this->assertSame('Node 1', $this->service->getFullPath($this->getNode('Node 1')));
        $this->assertSame('Node 1 → Node 1.1 → Node 1.1.1', $this->service->getFullPath($this->getNode('Node 1.1.1')));
        $this->assertSame('Node 2/Node 2.1', $this->service->getFullPath($this->getNode('Node 2.1'), '/'));
    }

    public function testGetFullPathMatchesEntity(): void
    {
        foreach ($this->em->getRepository(AttachmentType::class)->findAll() as $element) {
            $this->assertSame($element->getFullPath(), $this->service->getFullPath($element));
        }
    }

    public function testGetFullPathForNewElement(): void
    {
        $element = new AttachmentType();
        $element->setName('New');
        $element->setParent($this->getNode('Node 1'));

        $this->assertSame('Node 1 → New', $this->service->getFullPath($element));
    }

    public function testChangeInvalidatesPaths(): void
    {
        //Load the paths into the cache first
        $this->assertSame('Node 1 → Node 1.1 → Node 1.1.1', $this->service->getFullPath($this->getNode('Node 1.1.1')));

        $this->getNode('Node 1.1')->setName('Renamed');
        $this->em->flush();

        $this->assertSame('Node 1 → Renamed → Node 1.1.1', $this->service->getFullPath($this->getNode('Node 1.1.1')));
    }
}
