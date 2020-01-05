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

declare(strict_types=1);

namespace App\Entity\Base;

use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Validator\Constraints\NoneOfItsChildren;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * All elements with the fields "id", "name" and "parent_id" (at least).
 *
 * This class is for managing all database objects with a structural design.
 * All these sub-objects must have the table columns 'id', 'name' and 'parent_id' (at least)!
 * The root node has always the ID '0'.
 * It's allowed to have instances of root elements, but if you try to change
 * an attribute of a root element, you will get an exception!
 *
 * @ORM\MappedSuperclass(repositoryClass="App\Repository\StructuralDBElementRepository")
 *
 * @ORM\EntityListeners({"App\Security\EntityListeners\ElementPermissionListener", "App\EntityListeners\TreeCacheInvalidationListener"})
 *
 * @UniqueEntity(fields={"name", "parent"}, ignoreNull=false, message="structural.entity.unique_name")
 */
abstract class StructuralDBElement extends AttachmentContainingDBElement
{
    public const ID_ROOT_ELEMENT = 0;

    //This is a not standard character, so build a const, so a dev can easily use it
    public const PATH_DELIMITER_ARROW = ' → ';

    // We can not define the mapping here or we will get an exception. Unfortunately we have to do the mapping in the
    // subclasses
    /**
     * @var StructuralDBElement[]
     * @Groups({"include_children"})
     */
    protected $children;
    /**
     * @var StructuralDBElement
     * @NoneOfItsChildren()
     * @Groups({"include_parents"})
     */
    protected $parent;

    /**
     * @var string The comment info for this element
     * @ORM\Column(type="text")
     * @Groups({"simple", "extended", "full"})
     */
    protected $comment = '';

    /**
     * @var bool If this property is set, this element can not be selected for part properties.
     *           Useful if this element should be used only for grouping, sorting.
     * @ORM\Column(type="boolean")
     */
    protected $not_selectable = false;

    /**
     * @var int
     */
    protected $level = 0;

    /** @var string[] all names of all parent elements as a array of strings,
     *  the last array element is the name of the element itself
     */
    private $full_path_strings;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
    }

    /******************************************************************************
     * StructuralDBElement constructor.
     *****************************************************************************/

    /**
     * Check if this element is a child of another element (recursive).
     *
     * @param StructuralDBElement $another_element the object to compare
     *                                             IMPORTANT: both objects to compare must be from the same class (for example two "Device" objects)!
     *
     * @return bool True, if this element is child of $another_element.
     *
     * @throws \InvalidArgumentException if there was an error
     */
    public function isChildOf(self $another_element): bool
    {
        $class_name = static::class;

        //Check if both elements compared, are from the same type
        // (we have to check inheritance, or we get exceptions when using doctrine entities (they have a proxy type):
        if (! is_a($another_element, $class_name) && ! is_a($this, \get_class($another_element))) {
            throw new \InvalidArgumentException('isChildOf() only works for objects of the same type!');
        }

        if (null === $this->getParent()) { // this is the root node
            return false;
        }

        //If this' parents element, is $another_element, then we are finished
        return ($this->parent->getID() === $another_element->getID())
            || $this->parent->isChildOf($another_element); //Otherwise, check recursively
    }

    /**
     * Checks if this element is an root element (has no parent).
     *
     * @return bool True if the this element is an root element.
     */
    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    /******************************************************************************
     *
     * Getters
     *
     ******************************************************************************/

    /**
     * Get the parent of this element.
     *
     * @return StructuralDBElement|null The parent element. Null if this element, does not have a parent.
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     *  Get the comment of the element.

     *
     * @return string the comment
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Get the level.
     *
     * The level of the root node is -1.
     *
     * @return int the level of this element (zero means a most top element
     *             [a sub element of the root node])
     */
    public function getLevel(): int
    {
        /*
         * Only check for nodes that have a parent. In the other cases zero is correct.
         */
        if (0 === $this->level && null !== $this->parent) {
            $element = $this->parent;
            while (null !== $element) {
                /** @var StructuralDBElement $element */
                $element = $element->parent;
                ++$this->level;
            }
        }

        return $this->level;
    }

    /**
     * Get the full path.
     *
     * @param string $delimiter the delimiter of the returned string
     *
     * @return string the full path (incl. the name of this element), delimited by $delimiter
     */
    public function getFullPath(string $delimiter = self::PATH_DELIMITER_ARROW): string
    {
        if (! \is_array($this->full_path_strings)) {
            $this->full_path_strings = [];
            $this->full_path_strings[] = $this->getName();
            $element = $this;

            $overflow = 20; //We only allow 20 levels depth

            while (null !== $element->parent && $overflow >= 0) {
                $element = $element->parent;
                $this->full_path_strings[] = $element->getName();
                //Decrement to prevent mem overflow.
                --$overflow;
            }

            $this->full_path_strings = array_reverse($this->full_path_strings);
        }

        return implode($delimiter, $this->full_path_strings);
    }

    /**
     * Gets the path to this element (including the element itself).
     *
     * @return self[] An array with all (recursively) parent elements (including this one),
     *                ordered from the lowest levels (root node) first to the highest level (the element itself)
     */
    public function getPathArray(): array
    {
        $tmp = [];
        $tmp[] = $this;

        //We only allow 20 levels depth
        while (! end($tmp)->isRoot() && \count($tmp) < 20) {
            $tmp[] = end($tmp)->parent;
        }

        return array_reverse($tmp);
    }

    /**
     * Get all sub elements of this element.
     *
     * @return Collection<static> all subelements as an array of objects (sorted by their full path)
     */
    public function getSubelements(): iterable
    {
        return $this->children;
    }

    /**
     * @return Collection<static>
     */
    public function getChildren(): iterable
    {
        return $this->children;
    }

    /**
     * @return bool
     */
    public function isNotSelectable(): bool
    {
        return $this->not_selectable;
    }

    /******************************************************************************
     *
     * Setters
     *
     ******************************************************************************/

    /**
     * Sets the new parent object.
     *
     * @param self $new_parent The new parent object
     *
     * @return StructuralDBElement
     */
    public function setParent(?self $new_parent): self
    {
        /*
        if ($new_parent->isChildOf($this)) {
            throw new \InvalidArgumentException('You can not use one of the element childs as parent!');
        } */

        $this->parent = $new_parent;

        return $this;
    }

    /**
     *  Set the comment.
     *
     * @param string $new_comment the new comment
     *
     * @return StructuralDBElement
     */
    public function setComment(?string $new_comment): self
    {
        $this->comment = $new_comment;

        return $this;
    }

    public function setChildren(array $element): self
    {
        $this->children = $element;

        return $this;
    }

    /**
     * @return StructuralDBElement
     */
    public function setNotSelectable(bool $not_selectable): self
    {
        $this->not_selectable = $not_selectable;

        return $this;
    }

    public function clearChildren(): self
    {
        $this->children = new ArrayCollection();

        return $this;
    }
}
