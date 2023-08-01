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

namespace App\Services\Trees;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Helpers\Trees\TreeViewNode;
use App\Helpers\Trees\TreeViewNodeIterator;
use App\Repository\StructuralDBElementRepository;
use App\Services\EntityURLGenerator;
use App\Services\UserSystem\UserCacheKeyGenerator;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RecursiveIteratorIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function count;

/**
 * @see \App\Tests\Services\Trees\TreeViewGeneratorTest
 */
class TreeViewGenerator
{
    public function __construct(protected EntityURLGenerator $urlGenerator, protected EntityManagerInterface $em, protected TagAwareCacheInterface $cache,
        protected UserCacheKeyGenerator $keyGenerator, protected TranslatorInterface $translator, private UrlGeneratorInterface $router,
        protected bool $rootNodeExpandedByDefault, protected bool $rootNodeEnabled)
    {
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
            //DO NOT try to create an object from the class, as this might be an proxy, which can not be easily initialized, so just pass the class_name directly
            $href = $this->urlGenerator->createURL($class);
            $new_node = new TreeViewNode($this->translator->trans('entity.tree.new'), $href);
            //When the id of the selected element is null, then we have a new element, and we need to select "new" node
            if (!$selectedElement instanceof AbstractDBElement || null === $selectedElement->getID()) {
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
            $href_type = 'list_parts';
        }

        $generic = $this->getGenericTree($class, $parent);
        $treeIterator = new TreeViewNodeIterator($generic);
        $recursiveIterator = new RecursiveIteratorIterator($treeIterator, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($recursiveIterator as $item) {
            /** @var TreeViewNode $item */
            if ($selectedElement instanceof AbstractDBElement && $item->getId() === $selectedElement->getID()) {
                $item->setSelected(true);
            }

            if ($item->getNodes() !== null && $item->getNodes() !== []) {
                $item->addTag((string) count($item->getNodes()));
            }

            if ($href_type !== '' && null !== $item->getId()) {
                $entity = $this->em->getPartialReference($class, $item->getId());
                $item->setHref($this->urlGenerator->getURL($entity, $href_type));
            }

            //Translate text if text starts with $$
            if (str_starts_with($item->getText(), '$$')) {
                $item->setText($this->translator->trans(substr($item->getText(), 2)));
            }
        }

        if (($mode === 'list_parts_root' || $mode === 'devices') && $this->rootNodeEnabled) {
            //We show the root node as a link to the list of all parts
            $show_all_parts_url = $this->router->generate('parts_show_all');

            $root_node = new TreeViewNode($this->entityClassToRootNodeString($class), $show_all_parts_url, $generic);
            $root_node->setExpanded($this->rootNodeExpandedByDefault);
            $root_node->setIcon($this->entityClassToRootNodeIcon($class));

            $generic = [$root_node];
        }

        return array_merge($head, $generic);
    }

    protected function entityClassToRootNodeString(string $class): string
    {
        return match ($class) {
            Category::class => $this->translator->trans('category.labelp'),
            Storelocation::class => $this->translator->trans('storelocation.labelp'),
            Footprint::class => $this->translator->trans('footprint.labelp'),
            Manufacturer::class => $this->translator->trans('manufacturer.labelp'),
            Supplier::class => $this->translator->trans('supplier.labelp'),
            Project::class => $this->translator->trans('project.labelp'),
            default => $this->translator->trans('tree.root_node.text'),
        };
    }

    protected function entityClassToRootNodeIcon(string $class): ?string
    {
        $icon = "fa-fw fa-treeview fa-solid ";
        return match ($class) {
            Category::class => $icon . 'fa-tags',
            Storelocation::class => $icon . 'fa-cube',
            Footprint::class => $icon . 'fa-microchip',
            Manufacturer::class => $icon . 'fa-industry',
            Supplier::class => $icon . 'fa-truck',
            Project::class => $icon . 'fa-archive',
            default => null,
        };
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
            throw new InvalidArgumentException('$class must be a class string that implements StructuralDBElement or NamedDBElement!');
        }
        if ($parent instanceof AbstractStructuralDBElement && !$parent instanceof $class) {
            throw new InvalidArgumentException('$parent must be of the type $class!');
        }

        /** @var StructuralDBElementRepository $repo */
        $repo = $this->em->getRepository($class);

        //If we just want a part of a tree, don't cache it
        if ($parent instanceof AbstractStructuralDBElement) {
            return $repo->getGenericNodeTree($parent);
        }

        $secure_class_name = str_replace('\\', '_', $class);
        $key = 'treeview_'.$this->keyGenerator->generateKey().'_'.$secure_class_name;

        return $this->cache->get($key, function (ItemInterface $item) use ($repo, $parent, $secure_class_name) {
            // Invalidate when groups, an element with the class or the user changes
            $item->tag(['groups', 'tree_treeview', $this->keyGenerator->generateKey(), $secure_class_name]);

            return $repo->getGenericNodeTree($parent);
        });
    }
}
