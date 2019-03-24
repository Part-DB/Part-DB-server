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

namespace App\Controller;

use App\Entity\StructuralDBElement;
use App\Helpers\TreeViewNode;
use App\Repository\StructuralDBElementRepository;
use App\Services\EntityURLGenerator;
use App\Services\ToolsTreeBuilder;
use Doctrine\ORM\EntityManagerInterface;

/**
 *  This service gives you multiple possibilities to generate trees.
 *
 * @package App\Controller
 */
class TreeBuilder
{
    protected $url_generator;
    protected $em;

    public function __construct(EntityURLGenerator $URLGenerator, EntityManagerInterface $em)
    {
        $this->url_generator = $URLGenerator;
        $this->em = $em;
    }

    /**
     * Generates a tree for the given Element. The given element is the top node, all children are child nodes.
     * @param StructuralDBElement $element The element for which the tree should be generated.
     * @param string $href_type The type of the links that should be used for the links. Set to null, to disable links.
     *                          See EntityURLGenerator::getURL for possible types.
     * @return TreeViewNode The Node for the given Element.
     */
    public function elementToTreeNode(StructuralDBElement $element, string $href_type = 'list_parts') : TreeViewNode
    {
        $children = $element->getSubelements();

        $children_nodes = null;
        foreach ($children as $child) {
            $children_nodes[] = $this->elementToTreeNode($child);
        }

        //Check if we need to generate a href type
        $href = null;

        if (!empty($href_type)) {
            $href = $this->url_generator->getURL($element, $href_type);
        }

        return new TreeViewNode($element->getName(), $href, $children_nodes);
    }

    /**
     * Generates a tree for all elements of the given type
     * @param StructuralDBElement $class_name The class name of the StructuralDBElement class for which the tree should
     *                                          be generated.
     * @param string $href_type The type of the links that should be used for the links. Set to null, to disable links.
     *                          See EntityURLGenerator::getURL for possible types.
     * @return TreeViewNode[] Returns an array, containing all nodes. It is empty if the given class has no elements.
     */
    public function typeToTree(StructuralDBElement $class_name, string $href_type = 'list_parts') : array
    {
        /**
         * @var $repo StructuralDBElementRepository
         */
        $repo = $this->em->getRepository($class_name);
        $root_nodes = $repo->findRootNodes();

        $array = array();
        foreach ($root_nodes as $node) {
            $array = $this->elementToTreeNode($node, $href_type);
        }

        return $array;
    }
}
