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

use App\Entity\AssemblySystem\Assembly;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\ProjectSystem\Project;
use App\Helpers\Trees\TreeViewNode;
use App\Helpers\Trees\TreeViewNodeIterator;
use App\Repository\NamedDBElementRepository;
use App\Repository\StructuralDBElementRepository;
use App\Services\Cache\ElementCacheTagGenerator;
use App\Services\Cache\UserCacheKeyGenerator;
use App\Services\EntityURLGenerator;
use App\Settings\BehaviorSettings\SidebarSettings;
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

    private readonly bool $rootNodeExpandedByDefault;
    private readonly bool $rootNodeEnabled;

    public function __construct(
        protected EntityURLGenerator $urlGenerator,
        protected EntityManagerInterface $em,
        protected TagAwareCacheInterface $cache,
        protected ElementCacheTagGenerator $tagGenerator,
        protected UserCacheKeyGenerator $keyGenerator,
        protected TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $router,
        private readonly SidebarSettings $sidebarSettings,
    ) {
        $this->rootNodeEnabled = $this->sidebarSettings->rootNodeEnabled;
        $this->rootNodeExpandedByDefault = $this->sidebarSettings->rootNodeExpanded;
    }

    /**
     * Gets a TreeView list for the entities of the given class.
     * The result is cached, if the full tree should be shown and no element should be selected.
     *
     * @param  string  $class  The class for which the treeView should be generated
     * @param  AbstractStructuralDBElement|null  $parent  The root nodes in the tree should have this element as parent (use null, if you want to get all entities)
     * @param  string  $mode  The link type that will be generated for the hyperlink section of each node (see EntityURLGenerator for possible values).
     *                                                          Set to empty string, to disable href field.
     * @param  AbstractDBElement|null  $selectedElement  The element that should be selected. If set to null, no element will be selected.
     *
     * @return TreeViewNode[] an array of TreeViewNode[] elements of the root elements
     */
    public function getTreeView(
        string $class,
        ?AbstractStructuralDBElement $parent = null,
        string $mode = 'list_parts',
        ?AbstractDBElement $selectedElement = null
    ): array
    {
        //If we just want a part of a tree, don't cache it or select a specific element, don't cache it
        if ($parent instanceof AbstractStructuralDBElement || $selectedElement instanceof AbstractDBElement) {
            return $this->getTreeViewUncached($class, $parent, $mode, $selectedElement);
        }

        $secure_class_name = $this->tagGenerator->getElementTypeCacheTag($class);
        $key = 'sidebar_treeview_'.$this->keyGenerator->generateKey().'_'.$secure_class_name;
        $key .= $mode;

        return $this->cache->get($key, function (ItemInterface $item) use ($class, $parent, $mode, $selectedElement, $secure_class_name) {
            // Invalidate when groups, an element with the class or the user changes
            $item->tag(['groups', 'tree_treeview', $this->keyGenerator->generateKey(), $secure_class_name]);
            return $this->getTreeViewUncached($class, $parent, $mode, $selectedElement);
        });
    }

    /**
     * Gets a TreeView list for the entities of the given class.
     *
     * @param  string  $class  The class for which the treeView should be generated
     * @param  AbstractStructuralDBElement|null  $parent  The root nodes in the tree should have this element as parent (use null, if you want to get all entities)
     * @param  string  $mode  The link type that will be generated for the hyperlink section of each node (see EntityURLGenerator for possible values).
     *                                                          Set to empty string, to disable href field.
     * @param  AbstractDBElement|null  $selectedElement  The element that should be selected. If set to null, no element will be selected.
     *
     * @return TreeViewNode[] an array of TreeViewNode[] elements of the root elements
     */
    private function getTreeViewUncached(
        string $class,
        ?AbstractStructuralDBElement $parent = null,
        string $mode = 'list_parts',
        ?AbstractDBElement $selectedElement = null
    ): array {
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

        if ($mode === 'assemblies') {
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
                $item->addTag((string)count($item->getNodes()));
            }

            if ($href_type !== '' && null !== $item->getId()) {
                $entity = $this->em->find($class, $item->getId());
                $item->setHref($this->urlGenerator->getURL($entity, $href_type));
            }

            //Translate text if text starts with $$
            if (str_starts_with($item->getText(), '$$')) {
                $item->setText($this->translator->trans(substr($item->getText(), 2)));
            }
        }

        if (($mode === 'list_parts_root' || $mode === 'devices') && $this->rootNodeEnabled) {
            $root_node = new TreeViewNode($this->entityClassToRootNodeString($class), $this->entityClassToRootNodeHref($class), $generic);
            $root_node->setExpanded($this->rootNodeExpandedByDefault);
            $root_node->setIcon($this->entityClassToRootNodeIcon($class));

            $generic = [$root_node];
        }

        return array_merge($head, $generic);
    }

    protected function entityClassToRootNodeHref(string $class): ?string
    {
        //If the root node should redirect to the new entity page, we return the URL for the new entity.
        if ($this->sidebarSettings->rootNodeRedirectsToNewEntity) {
            return match ($class) {
                Category::class => $this->router->generate('category_new'),
                StorageLocation::class => $this->router->generate('store_location_new'),
                Footprint::class => $this->router->generate('footprint_new'),
                Manufacturer::class => $this->router->generate('manufacturer_new'),
                Supplier::class => $this->router->generate('supplier_new'),
                Project::class => $this->router->generate('project_new'),
                default => null,
            };
        }

        return match ($class) {
            Project::class => $this->router->generate('project_new'),
            default => $this->router->generate('parts_show_all')
        };
    }

    protected function entityClassToRootNodeString(string $class): string
    {
        return match ($class) {
            Category::class => $this->translator->trans('category.labelp'),
            StorageLocation::class => $this->translator->trans('storelocation.labelp'),
            Footprint::class => $this->translator->trans('footprint.labelp'),
            Manufacturer::class => $this->translator->trans('manufacturer.labelp'),
            Supplier::class => $this->translator->trans('supplier.labelp'),
            Project::class => $this->translator->trans('project.labelp'),
            Assembly::class => $this->translator->trans('assembly.labelp'),
            default => $this->translator->trans('tree.root_node.text'),
        };
    }

    protected function entityClassToRootNodeIcon(string $class): ?string
    {
        $icon = "fa-fw fa-treeview fa-solid ";
        return match ($class) {
            Category::class => $icon.'fa-tags',
            StorageLocation::class => $icon.'fa-cube',
            Footprint::class => $icon.'fa-microchip',
            Manufacturer::class => $icon.'fa-industry',
            Supplier::class => $icon.'fa-truck',
            Project::class => $icon.'fa-archive',
            Assembly::class => $icon.'fa-list',
            default => null,
        };
    }

    /**
     * /**
     * Gets a tree of TreeViewNode elements. The root elements has $parent as parent.
     * The treeview is generic, that means the href are null and ID values are set.
     *
     * @param  string  $class  The class for which the tree should be generated
     * @phpstan-param class-string<AbstractNamedDBElement> $class
     * @param  AbstractStructuralDBElement|null  $parent  the parent the root elements should have
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

        /** @var NamedDBElementRepository<AbstractNamedDBElement> $repo */
        $repo = $this->em->getRepository($class);

        //If we just want a part of a tree, don't cache it
        if ($parent instanceof AbstractStructuralDBElement) {
            return $repo->getGenericNodeTree($parent); //@phpstan-ignore-line PHPstan does not seem to recognize, that we have a StructuralDBElementRepository here, which have 1 argument
        }

        $secure_class_name = $this->tagGenerator->getElementTypeCacheTag($class);
        $key = 'treeview_'.$this->keyGenerator->generateKey().'_'.$secure_class_name;

        return $this->cache->get($key, function (ItemInterface $item) use ($repo, $parent, $secure_class_name) {
            // Invalidate when groups, an element with the class or the user changes
            $item->tag(['groups', 'tree_treeview', $this->keyGenerator->generateKey(), $secure_class_name]);
            return $repo->getGenericNodeTree($parent); //@phpstan-ignore-line
        });
    }
}
