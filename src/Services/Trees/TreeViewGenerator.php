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

namespace App\Services\Trees;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Helpers\Trees\TreeViewNode;
use App\Helpers\Trees\TreeViewNodeIterator;
use App\Helpers\Trees\TreeViewNodeState;
use App\Repository\StructuralDBElementRepository;
use App\Services\EntityURLGenerator;
use App\Services\MarkdownParser;
use App\Services\UserCacheKeyGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TreeViewGenerator
{
    protected $urlGenerator;
    protected $em;
    protected $cache;
    protected $keyGenerator;
    protected $translator;

    protected $rootNodeExpandedByDefault;
    protected $rootNodeEnabled;

    public function __construct(EntityURLGenerator $URLGenerator, EntityManagerInterface $em,
        TagAwareCacheInterface $treeCache, UserCacheKeyGenerator $keyGenerator, TranslatorInterface $translator, bool $rootNodeExpandedByDefault, bool $rootNodeEnabled)
    {
        $this->urlGenerator = $URLGenerator;
        $this->em = $em;
        $this->cache = $treeCache;
        $this->keyGenerator = $keyGenerator;
        $this->translator = $translator;

        $this->rootNodeExpandedByDefault = $rootNodeExpandedByDefault;
        $this->rootNodeEnabled = $rootNodeEnabled;
    }

    /**
     * Gets a TreeView list for the entities of the given class.
     *
     * @param string                           $class           The class for which the treeView should be generated
     * @param AbstractStructuralDBElement|null $parent          The root nodes in the tree should have this element as parent (use null, if you want to get all entities)
     * @param string                           $mode            The link type that will be generated for the hyperlink section of each node (see EntityURLGenerator for possible values).
     *                                                          Set to empty string, to disable href field.
     * @param AbstractDBElement|null           $selectedElement The element that should be selected. If set to null, no element will be selected.
     *
     * @return TreeViewNode[] an array of TreeViewNode[] elements of the root elements
     */
    public function getTreeView(string $class, ?AbstractStructuralDBElement $parent = null, string $mode = 'list_parts', ?AbstractDBElement $selectedElement = null): array
    {
        $head = [];

        $href_type = $mode;

        //When we use the newEdit type, add the New Element node.
        if ('newEdit' === $mode) {
            //Generate the url for the new node
            $href = $this->urlGenerator->createURL(new $class());
            $new_node = new TreeViewNode($this->translator->trans('entity.tree.new'), $href);
            //When the id of the selected element is null, then we have a new element, and we need to select "new" node
            if (null === $selectedElement || null === $selectedElement->getID()) {
                $new_node->setSelected(true);
            }
            $head[] = $new_node;
            //Add spacing
            $head[] = (new TreeViewNode(''))->setDisabled(true);

            //Every other treeNode will be used for edit
            $href_type = 'edit';
        }

        if ($mode === 'list_parts_root') {
            $href_type = 'list_parts';
        }

        if ($mode === 'devices') {
            $href_type = '';
        }

        $generic = $this->getGenericTree($class, $parent);
        $treeIterator = new TreeViewNodeIterator($generic);
        $recursiveIterator = new \RecursiveIteratorIterator($treeIterator, \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($recursiveIterator as $item) {
            /** @var TreeViewNode $item */
            if (null !== $selectedElement && $item->getId() === $selectedElement->getID()) {
                $item->setSelected(true);
            }

            if (!empty($item->getNodes())) {
                $item->addTag((string) \count($item->getNodes()));
            }

            if (!empty($href_type) && null !== $item->getId()) {
                $entity = $this->em->getPartialReference($class, $item->getId());
                $item->setHref($this->urlGenerator->getURL($entity, $href_type));
            }

            //Translate text if text starts with $$
            if (0 === strpos($item->getText(), '$$')) {
                $item->setText($this->translator->trans(substr($item->getText(), 2)));
            }
        }

        if (($mode === 'list_parts_root' || $mode === 'devices') && $this->rootNodeEnabled) {
            $root_node = new TreeViewNode($this->entityClassToRootNodeString($class), null, $generic);
            $root_node->setExpanded($this->rootNodeExpandedByDefault);
            $generic = [$root_node];
        }

        return array_merge($head, $generic);
    }

    protected function entityClassToRootNodeString(string $class): string
    {
        switch ($class) {
            case Category::class:
                return $this->translator->trans('category.labelp');
            case Storelocation::class:
                return $this->translator->trans('storelocation.labelp');
            case Footprint::class:
                return $this->translator->trans('footprint.labelp');
            case Manufacturer::class:
                return $this->translator->trans('manufacturer.labelp');
            case Supplier::class:
                return $this->translator->trans('supplier.labelp');
            case Device::class:
                return $this->translator->trans('device.labelp');
            default:
                return $this->translator->trans('tree.root_node.text');
        }
    }

    /**
     * /**
     * Gets a tree of TreeViewNode elements. The root elements has $parent as parent.
     * The treeview is generic, that means the href are null and ID values are set.
     *
     * @param string                           $class  The class for which the tree should be generated
     * @param AbstractStructuralDBElement|null $parent the parent the root elements should have
     *
     * @return TreeViewNode[]
     */
    public function getGenericTree(string $class, ?AbstractStructuralDBElement $parent = null): array
    {
        if (!is_a($class, AbstractNamedDBElement::class, true)) {
            throw new \InvalidArgumentException('$class must be a class string that implements StructuralDBElement or NamedDBElement!');
        }
        if (null !== $parent && !is_a($parent, $class)) {
            throw new \InvalidArgumentException('$parent must be of the type $class!');
        }

        /** @var StructuralDBElementRepository $repo */
        $repo = $this->em->getRepository($class);

        //If we just want a part of a tree, dont cache it
        if (null !== $parent) {
            return $repo->getGenericNodeTree($parent);
        }

        $secure_class_name = str_replace('\\', '_', $class);
        $key = 'treeview_'.$this->keyGenerator->generateKey().'_'.$secure_class_name;

        return $this->cache->get($key, function (ItemInterface $item) use ($repo, $parent, $secure_class_name) {
            // Invalidate when groups, a element with the class or the user changes
            $item->tag(['groups', 'tree_treeview', $this->keyGenerator->generateKey(), $secure_class_name]);

            return $repo->getGenericNodeTree($parent);
        });
    }
}
