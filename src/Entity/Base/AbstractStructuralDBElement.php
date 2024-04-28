<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\Base;

use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\AbstractParameter;
use App\Repository\StructuralDBElementRepository;
use App\EntityListeners\TreeCacheInvalidationListener;
use App\Validator\Constraints\UniqueObjectCollection;
use Doctrine\DBAL\Types\Types;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Parameters\ParametersTrait;
use App\Validator\Constraints\NoneOfItsChildren;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use function count;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
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
 *
 * @see \App\Tests\Entity\Base\AbstractStructuralDBElementTest
 *
 * @template-covariant AT of Attachment
 * @template-covariant PT of AbstractParameter
 * @template-use ParametersTrait<PT>
 * @extends AttachmentContainingDBElement<AT>
 * @uses ParametersTrait<PT>
 */
#[UniqueEntity(fields: ['name', 'parent'], message: 'structural.entity.unique_name', ignoreNull: false)]
#[ORM\MappedSuperclass(repositoryClass: StructuralDBElementRepository::class)]
#[ORM\EntityListeners([TreeCacheInvalidationListener::class])]
abstract class AbstractStructuralDBElement extends AttachmentContainingDBElement
{
    use ParametersTrait;

    /**
     * This is a not standard character, so build a const, so a dev can easily use it.
     */
    final public const PATH_DELIMITER_ARROW = ' → ';

    /**
     * @var string The comment info for this element as markdown
     */
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $comment = '';

    /**
     * @var bool If this property is set, this element can not be selected for part properties.
     *           Useful if this element should be used only for grouping, sorting.
     */
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $not_selectable = false;

    /**
     * @var int
     */
    protected int $level = 0;

    /**
     * We can not define the mapping here, or we will get an exception. Unfortunately we have to do the mapping in the
     * subclasses.
     *
     * @var Collection<int, AbstractStructuralDBElement>
     * @phpstan-var Collection<int, static>
     */
    #[Groups(['include_children'])]
    protected Collection $children;

    /**
     * @var AbstractStructuralDBElement|null
     * @phpstan-var static|null
     */
    #[Groups(['include_parents', 'import'])]
    #[NoneOfItsChildren]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * Mapping done in subclasses.
     *
     * @var Collection<int, AbstractParameter>
     * @phpstan-var Collection<int, PT>
     */
    #[Assert\Valid]
    #[UniqueObjectCollection(fields: ['name', 'group', 'element'])]
    protected Collection $parameters;

    /** @var string[] all names of all parent elements as an array of strings,
     *  the last array element is the name of the element itself
     */
    private array $full_path_strings = [];

    /**
     * Alternative names (semicolon-separated) for this element, which can be used for searching (especially for info provider system)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['default' => null])]
    private ?string $alternative_names = "";

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->parameters = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->id) {
            //Deep clone parameters
            $parameters = $this->parameters;
            $this->parameters = new ArrayCollection();
            foreach ($parameters as $parameter) {
                $this->addParameter(clone $parameter);
            }
        }
        parent::__clone();
    }

    /******************************************************************************
     * StructuralDBElement constructor.
     *****************************************************************************/

    /**
     * Check if this element is a child of another element (recursive).
     *
     * @param AbstractStructuralDBElement $another_element the object to compare
     *                                                     IMPORTANT: both objects to compare must be from the same class (for example two "Device" objects)!
     *
     * @return bool true, if this element is child of $another_element
     *
     * @throws InvalidArgumentException if there was an error
     */
    public function isChildOf(self $another_element): bool
    {
        $class_name = static::class;

        //Check if both elements compared, are from the same type
        // (we have to check inheritance, or we get exceptions when using doctrine entities (they have a proxy type):
        if (!$another_element instanceof $class_name && !is_a($this, $another_element::class)) {
            throw new InvalidArgumentException('isChildOf() only works for objects of the same type!');
        }

        if (!$this->getParent() instanceof self) { // this is the root node
            return false;
        }

        //If the parent element is equal to the element we want to compare, return true
        if ($this->getParent()->getID() === null) {
            //If the IDs are not yet defined, we have to compare the objects itself
            if ($this->getParent() === $another_element) {
                return true;
            }
        } elseif ($this->getParent()->getID() === $another_element->getID()) {
            return true;
        }

        //Otherwise, check recursively
        return $this->parent->isChildOf($another_element);
    }

    /**
     * Checks if this element is an root element (has no parent).
     *
     * @return bool true if this element is a root element
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /******************************************************************************
     *
     * Getters
     *
     ******************************************************************************/

    /**
     * Get the parent of this element.
     *
     * @return static|null The parent element. Null if this element, does not have a parent.
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     *  Get the comment of the element as markdown encoded string.

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
        if (0 === $this->level && $this->parent instanceof self) {
            $element = $this->parent;
            while ($element instanceof self) {
                /** @var AbstractStructuralDBElement $element */
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
    #[Groups(['api:basic:read'])]
    #[SerializedName('full_path')]
    public function getFullPath(string $delimiter = self::PATH_DELIMITER_ARROW): string
    {
        if ($this->full_path_strings === []) {
            $this->full_path_strings = [];
            $this->full_path_strings[] = $this->getName();
            $element = $this;

            $overflow = 20; //We only allow 20 levels depth

            while ($element->parent instanceof self && $overflow >= 0) {
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
        while (!end($tmp)->isRoot() && count($tmp) < 20) {
            $tmp[] = end($tmp)->parent;
        }

        return array_reverse($tmp);
    }

    /**
     * Get all sub elements of this element.
     *
     * @return Collection<static>|iterable all subelements as an array of objects (sorted by their full path)
     * @psalm-return Collection<int, static>
     */
    public function getSubelements(): iterable
    {
        //If the parent is equal to this object, we would get an endless loop, so just return an empty array
        //This is just a workaround, as validator should prevent this behaviour, before it gets written to the database
        if ($this->parent === $this) {
            return new ArrayCollection();
        }

        return $this->children ?? new ArrayCollection();
    }

    /**
     * @see getSubelements()
     * @return Collection<static>|iterable
     * @psalm-return Collection<int, static>
     */
    public function getChildren(): iterable
    {
        return $this->getSubelements();
    }

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
     * @param  static|null  $new_parent  The new parent object
     * @return $this
     */
    public function setParent(?self $new_parent): self
    {
        /*
        if ($new_parent->isChildOf($this)) {
            throw new \InvalidArgumentException('You can not use one of the element childs as parent!');
        } */

        $this->parent = $new_parent;

        //Add this element as child to the new parent
        if ($new_parent instanceof self) {
            $new_parent->getChildren()->add($this);
        }

        return $this;
    }

    /**
     *  Set the comment.
     *
     * @param  string  $new_comment  the new comment
     *
     * @return $this
     */
    public function setComment(string $new_comment): self
    {
        $this->comment = $new_comment;

        return $this;
    }

    /**
     * Adds the given element as child to this element.
     * @param  static  $child
     * @return $this
     */
    public function addChild(self $child): self
    {
        $this->children->add($child);
        //Children get this element as parent
        $child->setParent($this);
        return $this;
    }

    /**
     * Removes the given element as child from this element.
     * @param  static  $child
     * @return $this
     */
    public function removeChild(self $child): self
    {
        $this->children->removeElement($child);
        //Children has no parent anymore
        $child->setParent(null);
        return $this;
    }

    /**
     * @return AbstractStructuralDBElement
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

    /**
     * Returns a comma separated list of alternative names.
     * @return string|null
     */
    public function getAlternativeNames(): ?string
    {
        if ($this->alternative_names === null) {
            return null;
        }

        //Remove trailing comma
        return rtrim($this->alternative_names, ',');
    }

    /**
     * Sets a comma separated list of alternative names.
     * @return $this
     */
    public function setAlternativeNames(?string $new_value): self
    {
        //Add a trailing comma, if not already there (makes it easier to find in the database)
        if (is_string($new_value) && !str_ends_with($new_value, ',')) {
            $new_value .= ',';
        }

        $this->alternative_names = $new_value;

        return $this;
    }
}
