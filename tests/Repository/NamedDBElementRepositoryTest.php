<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\Repository;

use App\Entity\UserSystem\User;
use App\Helpers\Trees\TreeViewNode;
use App\Repository\StructuralDBElementRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @Group DB
 */
class NamedDBElementRepositoryTest extends WebTestCase
{
    private $entityManager;
    /** @var StructuralDBElementRepository */
    private $repo;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->repo = $this->entityManager->getRepository(User::class);
    }

    public function testGetGenericNodeTree(): void
    {
        $tree = $this->repo->getGenericNodeTree();

        $this->assertIsArray($tree);
        $this->assertContainsOnlyInstancesOf(TreeViewNode::class, $tree);
        $this->assertCount(4, $tree);
        $this->assertSame('admin', $tree[0]->getText());
        $this->assertEmpty($tree[0]->getNodes());
    }
}
