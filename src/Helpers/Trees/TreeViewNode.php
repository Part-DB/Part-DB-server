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

namespace App\Helpers\Trees;

use JsonSerializable;

/**
 * This class represents a node for the bootstrap treeview node.
 * When you serialize an array of these objects to JSON, you can use the serialized data in data for the treeview.
 */
final class TreeViewNode implements JsonSerializable
{
    private $text;
    private $href;
    private $nodes;

    private $state = null;

    private $tags;

    private $id;

    /**
     * Creates a new TreeView node with the given parameters.
     *
     * @param string      $text  The text that is shown in the node. (e.g. the name of the node)
     * @param string|null $href  A link for the node. You have to activate "enableLinks" option in init of the treeview.
     *                           Set this to null, if no href should be used.
     * @param array|null  $nodes An array containing other TreeViewNodes. They will be used as children nodes of the
     *                           newly created nodes. Set to null, if it should not have children.
     */
    public function __construct(string $text, ?string $href = null, ?array $nodes = null)
    {
        $this->text = $text;
        $this->href = $href;
        $this->nodes = $nodes;

        //$this->state = new TreeViewNodeState();
    }

    /**
     * Return the ID of the entity associated with this node.
     * Null if this node is not connected with an entity.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the ID of the entity associated with this node.
     * Null if this node is not connected with an entity.
     *
     * @return $this
     */
    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Returns the node text.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Sets the node text.
     *
     * @param string $text the new node text
     *
     * @return TreeViewNode
     */
    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Returns the href link.
     */
    public function getHref(): ?string
    {
        return $this->href;
    }

    /**
     * Sets the href link.
     *
     * @param string|null $href the new href link
     *
     * @return TreeViewNode
     */
    public function setHref(?string $href): self
    {
        $this->href = $href;

        return $this;
    }

    /**
     * Returns the children nodes of this node.
     *
     * @return TreeViewNode[]|null
     */
    public function getNodes(): ?array
    {
        return $this->nodes;
    }

    /**
     * Sets the children nodes.
     *
     * @param array|null $nodes The new children nodes
     *
     * @return TreeViewNode
     */
    public function setNodes(?array $nodes): self
    {
        $this->nodes = $nodes;

        return $this;
    }

    public function getState(): ?TreeViewNodeState
    {
        return $this->state;
    }

    public function setState(TreeViewNodeState $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function setDisabled(?bool $disabled): self
    {
        //Lazy loading of state, so it does not need to get serialized and transfered, when it is empty.
        if (null === $this->state) {
            $this->state = new TreeViewNodeState();
        }

        $this->state->setDisabled($disabled);

        return $this;
    }

    public function setSelected(?bool $selected): self
    {
        //Lazy loading of state, so it does not need to get serialized and transfered, when it is empty.
        if (null === $this->state) {
            $this->state = new TreeViewNodeState();
        }

        $this->state->setSelected($selected);

        return $this;
    }

    public function setExpanded(?bool $selected): self
    {
        //Lazy loading of state, so it does not need to get serialized and transfered, when it is empty.
        if (null === $this->state) {
            $this->state = new TreeViewNodeState();
        }

        $this->state->setExpanded(true);

        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function addTag(string $new_tag): self
    {
        //Lazy loading tags
        if (null === $this->tags) {
            $this->tags = [];
        }

        $this->tags[] = $new_tag;

        return $this;
    }

    public function jsonSerialize(): array
    {
        $ret = [
            'text' => $this->text,
        ];

        if (null !== $this->href) {
            $ret['href'] = $this->href;
        }

        if (null !== $this->tags) {
            $ret['tags'] = $this->tags;
        }

        if (null !== $this->nodes) {
            $ret['nodes'] = $this->nodes;
        }

        if (null !== $this->state) {
            $ret['state'] = $this->state;
        }

        return $ret;
    }
}
