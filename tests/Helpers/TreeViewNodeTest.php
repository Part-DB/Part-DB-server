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

namespace App\Tests\Helpers;

use App\Helpers\Trees\TreeViewNode;
use PHPUnit\Framework\TestCase;

class TreeViewNodeTest extends TestCase
{
    /**
     * @var TreeViewNode
     */
    protected $node1;
    /**
     * @var TreeViewNode
     */
    protected $node2;

    protected function setUp(): void
    {
        $sub_nodes = [];
        $sub_nodes[] = new TreeViewNode('Subnode 1');
        $sub_sub_nodes[] = [];
        $sub_sub_nodes[] = new TreeViewNode('Sub Subnode 1');
        $sub_sub_nodes[] = new TreeViewNode('Sub Subnode 2');
        $sub_nodes[] = new TreeViewNode('Subnode 2');

        //Init node1 with default arguments;
        $this->node1 = new TreeViewNode('Name');
        //Node 2 gets values for all arguments
        $this->node2 = new TreeViewNode('Name', 'www.foo.bar', $sub_nodes);
    }

    public function testConstructor(): void
    {
        //A node without things should have null values on its properties:
        $this->assertNull($this->node1->getHref());
        $this->assertNull($this->node1->getNodes());
        $this->assertSame('Name', $this->node1->getText());

        //The second node must have the given things as properties.
        $this->assertSame('Name', $this->node2->getText());
        $this->assertSame('www.foo.bar', $this->node2->getHref());
        $this->assertNotEmpty($this->node2->getNodes());
    }
}
