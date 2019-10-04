<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
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
 *
 */

namespace App\Services;

use App\Entity\Base\DBElement;
use App\Entity\Base\NamedDBElement;
use App\Entity\Base\StructuralDBElement;
use App\Helpers\TreeViewNode;
use App\Repository\StructuralDBElementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 *  This service gives you multiple possibilities to generate trees.
 *
 * @package App\Controller
 */
class TreeBuilder
{
    protected $url_generator;
    protected $em;
    protected $translator;
    protected $cache;
    protected $keyGenerator;

    public function __construct(EntityURLGenerator $URLGenerator, EntityManagerInterface $em,
                                TranslatorInterface $translator, TagAwareCacheInterface $treeCache, UserCacheKeyGenerator $keyGenerator)
    {
        $this->url_generator = $URLGenerator;
        $this->em = $em;
        $this->translator = $translator;
        $this->keyGenerator = $keyGenerator;
        $this->cache = $treeCache;
    }

    /**
     * Generates a tree for the given Element. The given element is the top node, all children are child nodes.
     * @param StructuralDBElement $element The element for which the tree should be generated.
     * @param string $href_type The type of the links that should be used for the links. Set to null, to disable links.
     *                          See EntityURLGenerator::getURL for possible types.
     * @param DBElement|null $selectedElement When a element is given here, its tree node will be marked as selected in
     * the resulting tree. When $selectedElement is not existing in the tree, then nothing happens.
     * @return TreeViewNode The Node for the given Element.
     * @throws \App\Exceptions\EntityNotSupportedException
     */
    public function elementToTreeNode(NamedDBElement $element, ?string $href_type = 'list_parts', DBElement $selectedElement = null) : TreeViewNode
    {
        $children_nodes = null;

        if ($element instanceof StructuralDBElement) {
            $children = $element->getSubelements();
            foreach ($children as $child) {
                $children_nodes[] = $this->elementToTreeNode($child, $href_type, $selectedElement);
            }
        }

        //Check if we need to generate a href type
        $href = null;

        if (!empty($href_type)) {
            $href = $this->url_generator->getURL($element, $href_type);
        }

        $tree_node = new TreeViewNode($element->__toString(), $href, $children_nodes);

        if($children_nodes != null) {
            $tree_node->addTag((string) count($children_nodes));
        }

        //Check if we need to select the current part
        if ($selectedElement !== null && $element->getID() === $selectedElement->getID()) {
            $tree_node->setSelected(true);
        }

        return $tree_node;
    }

    /**
     * Generates a tree for all elements of the given type
     * @param StructuralDBElement $class_name The class name of the StructuralDBElement class for which the tree should
     *                                          be generated.
     * @param string $href_type The type of the links that should be used for the links. Set to null, to disable links.
     *                          See EntityURLGenerator::getURL for possible types.
     * @param DBElement|null $selectedElement When a element is given here, its tree node will be marked as selected in
     * the resulting tree. When $selectedElement is not existing in the tree, then nothing happens.
     * @return TreeViewNode[] Returns an array, containing all nodes. It is empty if the given class has no elements.
     * @throws \App\Exceptions\EntityNotSupportedException
     */
    public function typeToTree(string $class_name, ?string $href_type = 'list_parts', DBElement $selectedElement = null) : array
    {
        /**
         * @var $repo StructuralDBElementRepository
         */
        $repo = $this->em->getRepository($class_name);

        if (new $class_name() instanceof StructuralDBElement) {
            $root_nodes = $repo->findRootNodes();
        } else {
            $root_nodes = $repo->findAll();
        }

        $array = array();

        //When we use the newEdit type, add the New Element node.
        if ($href_type === 'newEdit') {
            //Generate the url for the new node
            $href = $this->url_generator->createURL(new $class_name());
            $new_node = new TreeViewNode($this->translator->trans('entity.tree.new'), $href);
            //When the id of the selected element is null, then we have a new element, and we need to select "new" node
            if ($selectedElement != null && $selectedElement->getID() == null) {
                $new_node->setSelected(true);
            }
            $array[] = $new_node;
            //Add spacing
            $array[] = (new TreeViewNode(''))->setDisabled(true);

            //Every other treeNode will be used for edit
            $href_type = "edit";
        }

        foreach ($root_nodes as $node) {
            $array[] = $this->elementToTreeNode($node, $href_type, $selectedElement);
        }

        return $array;
    }

    /**
     * Gets a flattened hierachical tree. Useful for generating option lists.
     * In difference to the Repository Function, the results here are cached.
     * @param string $class_name The class name of the entity you want to retrieve.
     * @param StructuralDBElement|null $parent This entity will be used as root element. Set to null, to use global root
     * @return StructuralDBElement[] A flattened list containing the tree elements.
     */
    public function typeToNodesList(string $class_name, ?StructuralDBElement $parent = null): array
    {
        $parent_id = $parent != null ? $parent->getID() : "0";
        // Backslashes are not allowed in cache keys
        $secure_class_name = str_replace("\\", '_', $class_name);
        $key = "list_" . $this->keyGenerator->generateKey() . "_" . $secure_class_name . $parent_id;

        $ret = $this->cache->get($key, function (ItemInterface $item) use ($class_name, $parent, $secure_class_name) {
            // Invalidate when groups, a element with the class or the user changes
            $item->tag(['groups', 'tree_list', $this->keyGenerator->generateKey(), $secure_class_name]);
            /**
             * @var $repo StructuralDBElementRepository
             */
            $repo = $this->em->getRepository($class_name);

            return $repo->toNodesList($parent);
        });

        return $ret;
    }
}
