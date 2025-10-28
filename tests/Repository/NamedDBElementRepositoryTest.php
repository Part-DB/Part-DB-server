<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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
    /**
     * @var StructuralDBElementRepository
     */
    private $repo;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->repo = $entityManager->getRepository(User::class);
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
