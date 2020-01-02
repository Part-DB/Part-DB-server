<?php
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

use App\Entity\Attachments\AttachmentType;
use App\Helpers\TreeViewNode;
use App\Repository\StructuralDBElementRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @Group DB
 */
class StructuralDBElementRepositoryTest extends WebTestCase
{

    private $entityManager;
    /** @var StructuralDBElementRepository */
    private $repo;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->repo = $this->entityManager->getRepository(AttachmentType::class);
    }

    public function testFindRootNodes() : void
    {
        $root_nodes = $this->repo->findRootNodes();
        $this->assertCount(3, $root_nodes);
        $this->assertContainsOnlyInstancesOf(AttachmentType::class, $root_nodes);

        //Asc sorting
        $this->assertEquals('Node 1', $root_nodes[0]->getName());
        $this->assertEquals('Node 2', $root_nodes[1]->getName());
        $this->assertEquals('Node 3', $root_nodes[2]->getName());
    }

    public function testGetGenericTree() : void
    {
        $tree = $this->repo->getGenericNodeTree();
        $this->assertIsArray($tree);
        $this->assertContainsOnlyInstancesOf(TreeViewNode::class, $tree);

        $this->assertCount(3, $tree);
        $this->assertCount(2, $tree[0]->getNodes());
        $this->assertCount(1, $tree[0]->getNodes()[0]->getNodes());
        $this->assertEmpty($tree[2]->getNodes());
        $this->assertEmpty($tree[1]->getNodes()[0]->getNodes());

        //Check text
        $this->assertEquals('Node 1', $tree[0]->getText());
        $this->assertEquals('Node 2', $tree[1]->getText());
        $this->assertEquals('Node 3', $tree[2]->getText());
        $this->assertEquals('Node 1.1', $tree[0]->getNodes()[0]->getText());
        $this->assertEquals('Node 1.1.1', $tree[0]->getNodes()[0]->getNodes()[0]->getText());

        //Check that IDs were set correctly
        $this->assertEquals(1, $tree[0]->getId());
        $this->assertEquals(2, $tree[1]->getId());
        $this->assertEquals(7, $tree[0]->getNodes()[0]->getNodes()[0]->getId());

    }

    /**
     * Test $repo->toNodesList() for null as parameter
     */
    public function testToNodesListRoot() : void
    {
        //List all root nodes and their children
        $nodes = $this->repo->toNodesList();

        $this->assertCount(7, $nodes);
        $this->assertContainsOnlyInstancesOf(AttachmentType::class, $nodes);
        $this->assertEquals('Node 1', $nodes[0]->getName());
        $this->assertEquals('Node 1.1', $nodes[1]->getName());
        $this->assertEquals('Node 1.1.1', $nodes[2]->getName());
        $this->assertEquals('Node 1.2', $nodes[3]->getName());
        $this->assertEquals('Node 2', $nodes[4]->getName());
        $this->assertEquals('Node 2.1', $nodes[5]->getName());
        $this->assertEquals('Node 3', $nodes[6]->getName());
    }

    public function testToNodesListElement() : void
    {
        //List all nodes that are children to Node 1
        $node1 = $this->repo->find(1);
        $nodes = $this->repo->toNodesList($node1);

        $this->assertCount(3, $nodes);
        $this->assertContainsOnlyInstancesOf(AttachmentType::class, $nodes);
        $this->assertEquals('Node 1.1', $nodes[0]->getName());
        $this->assertEquals('Node 1.1.1', $nodes[1]->getName());
        $this->assertEquals('Node 1.2', $nodes[2]->getName());
    }
}
