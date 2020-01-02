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

namespace App\Helpers\Trees;

use App\Helpers\Trees\TreeViewNode;

class TreeViewNodeIterator extends \ArrayIterator implements \RecursiveIterator
{

    /**
     * @param $nodes TreeViewNode[]
     */
    public function __construct($nodes)
    {
        parent::__construct($nodes);
    }

    /**
     * @inheritDoc
     */
    public function hasChildren()
    {
        /** @var TreeViewNode $element */
        $element = $this->current();
        return !empty($element->getNodes());
    }

    /**
     * @inheritDoc
     */
    public function getChildren()
    {
        /** @var TreeViewNode $element */
        $element = $this->current();
        return new TreeViewNodeIterator($element->getNodes());
    }
}