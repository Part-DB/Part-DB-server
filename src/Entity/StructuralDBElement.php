<?php declare(strict_types=1);
/**
 *
 * Part-DB Version 0.4+ "nextgen"
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
 *
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;

/**
 *
 * All elements with the fields "id", "name" and "parent_id" (at least)
 *
 * This class is for managing all database objects with a structural design.
 * All these sub-objects must have the table columns 'id', 'name' and 'parent_id' (at least)!
 * The root node has always the ID '0'.
 * It's allowed to have instances of root elements, but if you try to change
 * an attribute of a root element, you will get an exception!
 *
 * @ORM\MappedSuperclass()
 */
abstract class StructuralDBElement extends AttachmentContainingDBElement
{
    const ID_ROOT_ELEMENT = 0;

    //This is a not standard character, so build a const, so a dev can easily use it
    const PATH_DELIMITER_ARROW = ' → ';


    // We can not define the mapping here or we will get an exception. Unfortunatly we have to do the mapping in the
    // subclasses
    /**
     * @var StructuralDBElement[]
     */
    protected $children;
    /**
     * @var StructuralDBElement
     */
    protected $parent;

    /**
     * @var string The comment info for this element
     * @ORM\Column(type="string", nullable=true)
     */
    protected $comment;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $parent_id;

    /**
     * @var int
     */
    protected $level=0;

    /** @var string[] all names of all parent elements as a array of strings,
     *  the last array element is the name of the element itself */
    private $full_path_strings;

    /******************************************************************************
     * StructuralDBElement constructor.
     *****************************************************************************/

    /**
     * Check if this element is a child of another element (recursive)
     *
     * @param StructuralDBElement $another_element       the object to compare
     *        IMPORTANT: both objects to compare must be from the same class (for example two "Device" objects)!
     *
     * @return bool True, if this element is child of $another_element.
     *
     * @throws \InvalidArgumentException if there was an error
     */
    public function isChildOf(StructuralDBElement $another_element)
    {
        $class_name = \get_class($this);

        //Check if both elements compared, are from the same type:
        if ($class_name != \get_class($another_element)) {
            throw new \InvalidArgumentException('isChildOf() funktioniert nur mit Elementen des gleichen Typs!');
        }

        if ($this->getID() == null) { // this is the root node
            return false;
        }

//If this' parents element, is $another_element, then we are finished
        return (($this->parent->getID() == $another_element->getID())
            || $this->parent->isChildOf($another_element)); //Otherwise, check recursivley
    }


    /******************************************************************************
     *
     * Getters
     *
     ******************************************************************************/

    /**
     * @brief Get the parent-ID
     *
     * @retval integer          * the ID of the parent element
     *                          * NULL means, the parent is the root node
     *                          * the parent ID of the root node is -1
     */
    public function getParentID() : int
    {
        return $this->parent_id ?? self::ID_ROOT_ELEMENT; //Null means root element
    }

    /**
     * Get the parent of this element.
     * @return StructuralDBElement|null The parent element. Null if this element, does not have a parent.
     */
    public function getParent() : ?self
    {
        return $this->parent;
    }

    /**
     *  Get the comment of the element.
     *
     * @param boolean $parse_bbcode Should BBCode converted to HTML, before returning
     * @return string       the comment
     */
    public function getComment(bool $parse_bbcode = true) : string
    {
        $val = htmlspecialchars($this->comment ?? '');

        return $val;
    }

    /**
     * Get the level
     *
     *     The level of the root node is -1.
     *
     * @return integer      the level of this element (zero means a most top element
     *                      [a subelement of the root node])
     *
     */
    public function getLevel() : int
    {
        if ($this->level === 0) {
            $element = $this->parent;
            $parent_id = $element->getParentID();
            while ($parent_id > 0) {
                /** @var StructuralDBElement $element */
                $element = $element->parent;
                $parent_id = $element->getParentID();
                $this->level++;
            }
        }

        return $this->level;
    }

    /**
     * Get the full path
     *
     * @param string $delimeter     the delimeter of the returned string
     *
     * @return string       the full path (incl. the name of this element), delimeted by $delimeter
     *
     * @throws Exception    if there was an error
     */
    public function getFullPath(string $delimeter = self::PATH_DELIMITER_ARROW) : string
    {
        if (! \is_array($this->full_path_strings)) {
            $this->full_path_strings = array();
            $this->full_path_strings[] = $this->getName();
            $element = $this;

            while ($element->parent != null) {
                $element = $element->parent;
                $this->full_path_strings[] = $element->getName();
            }

            $this->full_path_strings = array_reverse($this->full_path_strings);
        }

        return implode($delimeter, $this->full_path_strings);
    }

    /**
     * Get all subelements of this element
     *
     * @param boolean $recursive        if true, the search is recursive
     *
     * @return static[]    all subelements as an array of objects (sorted by their full path)
     */
    public function getSubelements(bool $recursive) : PersistentCollection
    {
        if ($this->children == null) {
            $this->children = new ArrayCollection();
        }

        if (! $recursive) {
            return $this->children;
        } else {
            $all_elements = array();
            foreach ($this->children as $subelement) {
                $all_elements[] = $subelement;
                $all_elements = array_merge($all_elements, $subelement->getSubelements(true));
            }

            return $all_elements;
        }
    }

    /******************************************************************************
     *
     * Setters
     *
     ******************************************************************************/

    /**
     * Change the parent ID of this element
     *
     * @param integer|null $new_parent_id           * the ID of the new parent element
     *                                              * NULL if the parent should be the root node
     */
    public function setParentID($new_parent_id) : self
    {
        $this->parent_id = $new_parent_id;
        return $this;
    }

    /**
     *  Set the comment
     *
     * @param string $new_comment       the new comment
     * @throws Exception if there was an error
     */
    public function setComment(string $new_comment) : self
    {
        $this->comment = $new_comment;
        return $this;
    }

    /********************************************************************************
     *
     *   Tree / Table Builders
     *
     *********************************************************************************/

    /**
     * Build a HTML tree with all subcategories of this element
     *
     * This method prints a <option>-Line for every item.
     * <b>The <select>-tags are not printed here, you have to print them yourself!</b>
     * Deeper levels have more spaces in front.
     *
     * @param integer   $selected_id    the ID of the selected item
     * @param boolean   $recursive      if true, the tree will be recursive
     * @param boolean   $show_root      if true, the root node will be displayed
     * @param string    $root_name      if the root node is the very root element, you can set its name here
     * @param string    $value_prefix   This string is used as a prefix before the id in the value part of the option.
     *
     * @return string       HTML string if success
     *
     * @throws Exception    if there was an error
     */
    public function buildHtmlTree(
        $selected_id = null,
        bool $recursive = true,
        bool $show_root = true,
        string $root_name = '$$',
        string $value_prefix = ''
    ) : string {
        if ($root_name == '$$') {
            $root_name = _('Oberste Ebene');
        }

        $html = array();

        if ($show_root) {
            $root_level = $this->getLevel();
            if ($this->getID() > 0) {
                $root_name = htmlspecialchars($this->getName());
            }

            $html[] = '<option value="'. $value_prefix . $this->getID() . '">' . $root_name . '</option>';
        } else {
            $root_level =  $this->getLevel() + 1;
        }

        // get all subelements
        $subelements = $this->getSubelements($recursive);

        foreach ($subelements as $element) {
            $level = $element->getLevel() - $root_level;
            $selected = ($element->getID() == $selected_id) ? 'selected' : '';

            $html[] = '<option ' . $selected . ' value="' . $value_prefix . $element->getID() . '">';
            for ($i = 0; $i < $level; $i++) {
                $html[] = '&nbsp;&nbsp;&nbsp;';
            }
            $html[] = htmlspecialchars($element->getName()) . '</option>';
        }

        return implode("\n", $html);
    }


    public function buildBootstrapTree(
        $page,
        $parameter,
        $recursive = false,
        $show_root = false,
        $use_db_root_name = true,
        $root_name = '$$'
    ): array
    {
        if ($root_name == '$$') {
            $root_name = _('Oberste Ebene');
        }

        $subelements = $this->getSubelements(false);
        $nodes = array();

        foreach ($subelements as $element) {
            $nodes[] = $element->buildBootstrapTree($page, $parameter);
        }

        // if we are on root level?
        if ($this->getParentID() == -1) {
            if ($show_root) {
                $tree = array(
                    array('text' => $use_db_root_name ? htmlspecialchars($this->getName()) : $root_name ,
                        'href' => $page . '?' . $parameter . '=' . $this->getID(),
                        'nodes' => $nodes)
                );
            } else { //Dont show root node
                $tree = $nodes;
            }
        } elseif (!empty($nodes)) {
            $tree = array('text' => htmlspecialchars($this->getName()),
                'href' => $page . '?' . $parameter . '=' . $this->getID(),
                'nodes' => $nodes
            );
        } else {
            $tree = array('text' => htmlspecialchars($this->getName()),
                'href' => $page . '?' . $parameter . '=' .  $this->getID()
            );
        }


        return $tree;
    }

    /**
     * Creates a template loop for a Breadcrumb bar, representing the structural DB element.
     * @param $page string The base page, to which the breadcrumb links should be directing to.
     * @param $parameter string The parameter, which selects the ID of the StructuralDBElement.
     * @param bool $show_root Show the root as its own breadcrumb.
     * @param string $root_name The label which should be used for the root breadcrumb.
     * @return array An Loop containing multiple arrays, which contains href and caption for the breadcrumb.
     */
    public function buildBreadcrumbLoop(string $page, string $parameter, bool $show_root = false, $root_name = '$$', bool $element_is_link = false) : array
    {
        $breadcrumb = array();

        if ($root_name == '$$') {
            $root_name = _('Oberste Ebene');
        }

        if ($show_root) {
            $breadcrumb[] = array('label' => $root_name,
                'disabled' => true);
        }

        if (!$this->current_user->canDo(static::getPermissionName(), StructuralPermission::READ)) {
            return array('label' => '???',
                'disabled' => true);
        }

        $tmp = array();

        if ($element_is_link) {
            $tmp[] = array('label' => $this->getName(), 'href' => $page . '?' . $parameter . '=' .$this->getID(), 'selected' => true);
        } else {
            $tmp[] = array('label' => $this->getName(), 'selected' => true);
        }

        $parent_id = $this->getParentID();
        while ($parent_id > 0) {
            /** @var StructuralDBElement $element */
            $element = static::getInstance($this->database, $this->current_user, $this->log, $parent_id);
            $parent_id = $element->getParentID();
            $tmp[] = array('label' => $element->getName(), 'href' => $page . '?' . $parameter . '=' . $element->getID());
        }
        $tmp = array_reverse($tmp);

        $breadcrumb = array_merge($breadcrumb, $tmp);

        return $breadcrumb;
    }

}