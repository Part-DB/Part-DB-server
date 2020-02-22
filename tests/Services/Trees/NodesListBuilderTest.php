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

namespace App\Tests\Services\Trees;

use App\Entity\Attachments\AttachmentType;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @Group DB
 */
class NodesListBuilderTest extends WebTestCase
{
    protected $em;
    /**
     * @var NodesListBuilder
     */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::$container->get(NodesListBuilder::class);
        $this->em = self::$container->get(EntityManagerInterface::class);
    }

    /**
     * Test $repo->toNodesList() for null as parameter.
     */
    public function testTypeToNodesListtRoot(): void
    {
        //List all root nodes and their children
        $nodes = $this->service->typeToNodesList(AttachmentType::class);

        $this->assertCount(7, $nodes);
        $this->assertContainsOnlyInstancesOf(AttachmentType::class, $nodes);
        $this->assertSame('Node 1', $nodes[0]->getName());
        $this->assertSame('Node 1.1', $nodes[1]->getName());
        $this->assertSame('Node 1.1.1', $nodes[2]->getName());
        $this->assertSame('Node 1.2', $nodes[3]->getName());
        $this->assertSame('Node 2', $nodes[4]->getName());
        $this->assertSame('Node 2.1', $nodes[5]->getName());
        $this->assertSame('Node 3', $nodes[6]->getName());
    }

    public function testTypeToNodesListElement(): void
    {
        //List all nodes that are children to Node 1
        $node1 = $this->em->find(AttachmentType::class, 1);
        $nodes = $this->service->typeToNodesList(AttachmentType::class, $node1);

        $this->assertCount(3, $nodes);
        $this->assertContainsOnlyInstancesOf(AttachmentType::class, $nodes);
        $this->assertSame('Node 1.1', $nodes[0]->getName());
        $this->assertSame('Node 1.1.1', $nodes[1]->getName());
        $this->assertSame('Node 1.2', $nodes[2]->getName());
    }
}
