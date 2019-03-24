<?php
/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

namespace App\Helpers;

/**
 * This class represents a node for the bootstrap treeview node.
 * When you serialize an array of these objects to JSON, you can use the serialized data in data for the treeview.
 * @package App\Helpers
 */
class TreeViewNode
{
    protected $text;
    protected $href;
    protected $nodes;

    /**
     * Creates a new TreeView node with the given parameters.
     * @param string $text The text that is shown in the node. (e.g. the name of the node)
     * @param string|null $href A link for the node. You have to activate "enableLinks" option in init of the treeview.
     *                          Set this to null, if no href should be used.
     * @param array|null $nodes An array containing other TreeViewNodes. They will be used as children nodes of the
     *                          newly created nodes. Set to null, if it should not have children.
     */
    public function __construct(string $text, ?string $href = null, ?array $nodes = null)
    {
        $this->text = $text;
        $this->href = $href;
        $this->nodes = $nodes;
    }

    /**
     * Returns the node text.
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Sets the node text
     * @param string $text The new node text.
     * @return TreeViewNode
     */
    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Returns the href link.
     * @return string|null
     */
    public function getHref(): ?string
    {
        return $this->href;
    }

    /**
     * Sets the href link.
     * @param string|null $href The new href link.
     * @return TreeViewNode
     */
    public function setHref(?string $href): self
    {
        $this->href = $href;
        return $this;
    }

    /**
     * Returns the children nodes of this node.
     * @return array|null
     */
    public function getNodes(): ?array
    {
        return $this->nodes;
    }

    /**
     * Sets the children nodes.
     * @param array|null $nodes The new children nodes
     * @return TreeViewNode
     */
    public function setNodes(?array $nodes): self
    {
        $this->nodes = $nodes;
        return $this;
    }

}
