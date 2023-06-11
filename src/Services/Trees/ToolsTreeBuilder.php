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

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Attachments\AttachmentType;
use App\Entity\ProjectSystem\Project;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Helpers\Trees\TreeViewNode;
use App\Services\UserSystem\UserCacheKeyGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This Service generates the tree structure for the tools.
 * Whenever you change something here, you have to clear the cache, because the results are cached for performance reasons.
 */
class ToolsTreeBuilder
{
    public function __construct(protected TranslatorInterface $translator, protected UrlGeneratorInterface $urlGenerator, protected TagAwareCacheInterface $cache, protected UserCacheKeyGenerator $keyGenerator, protected Security $security)
    {
    }

    /**
     * Generates the tree for the tools' menu.
     * The result is cached.
     *
     * @return TreeViewNode[] the array containing all Nodes for the tools menu
     */
    public function getTree(): array
    {
        $key = 'tree_tools_'.$this->keyGenerator->generateKey();

        return $this->cache->get($key, function (ItemInterface $item) {
            //Invalidate tree, whenever group or the user changes
            $item->tag(['tree_tools', 'groups', $this->keyGenerator->generateKey()]);

            $tree = [];
            if (!empty($this->getToolsNode())) {
                $tree[] = (new TreeViewNode($this->translator->trans('tree.tools.tools'), null, $this->getToolsNode()))
                    ->setIcon('fa-fw fa-treeview fa-solid fa-toolbox');
            }

            if (!empty($this->getEditNodes())) {
                $tree[] = (new TreeViewNode($this->translator->trans('tree.tools.edit'), null, $this->getEditNodes()))
                    ->setIcon('fa-fw fa-treeview fa-solid fa-pen-to-square');
            }
            if (!empty($this->getShowNodes())) {
                $tree[] = (new TreeViewNode($this->translator->trans('tree.tools.show'), null, $this->getShowNodes()))
                    ->setIcon('fa-fw fa-treeview fa-solid fa-eye');
            }
            if (!empty($this->getSystemNodes())) {
                $tree[] = (new TreeViewNode($this->translator->trans('tree.tools.system'), null, $this->getSystemNodes()))
                ->setIcon('fa-fw fa-treeview fa-solid fa-server');
            }

            return $tree;
        });
    }

    protected function getToolsNode(): array
    {
        $nodes = [];

        if ($this->security->isGranted('@labels.create_labels')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.tools.label_dialog'),
                $this->urlGenerator->generate('label_dialog')
            ))->setIcon("fa-treeview fa-fw fa-solid fa-qrcode");
        }

        if ($this->security->isGranted('@tools.label_scanner')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.tools.label_scanner'),
                $this->urlGenerator->generate('scan_dialog')
            ))->setIcon('fa-treeview fa-fw fa-solid fa-camera-retro');
        }

        if ($this->security->isGranted('@tools.reel_calculator')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.tools.reel_calculator'),
                $this->urlGenerator->generate('tools_reel_calculator')
            ))->setIcon('fa-treeview fa-fw fa-solid fa-ruler');
        }
        if ($this->security->isGranted('@tools.builtin_footprints_viewer')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tools.builtin_footprints_viewer.title'),
                $this->urlGenerator->generate('tools_builtin_footprints_viewer')
            ))->setIcon('fa-treeview fa-fw fa-solid fa-images');
        }
        if ($this->security->isGranted('@tools.ic_logos')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('perm.tools.ic_logos'),
                $this->urlGenerator->generate('tools_ic_logos')
            ))->setIcon('fa-treeview fa-fw fa-solid fa-flag');
        }
        if ($this->security->isGranted('@parts.import')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('parts.import.title'),
                $this->urlGenerator->generate('parts_import')
            ))->setIcon('fa-treeview fa-fw fa-solid fa-file-import');
        }

        return $nodes;
    }

    /**
     * This functions creates a tree entries for the "edit" node of the tool's tree.
     *
     * @return TreeViewNode[]
     */
    protected function getEditNodes(): array
    {
        $nodes = [];

        if ($this->security->isGranted('read', new AttachmentType())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.attachment_types'),
                $this->urlGenerator->generate('attachment_type_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-file-alt');
        }
        if ($this->security->isGranted('read', new Category())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.categories'),
                $this->urlGenerator->generate('category_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-tags');
        }
        if ($this->security->isGranted('read', new Project())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.projects'),
                $this->urlGenerator->generate('project_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-archive');
        }
        if ($this->security->isGranted('read', new Supplier())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.suppliers'),
                $this->urlGenerator->generate('supplier_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-truck');
        }
        if ($this->security->isGranted('read', new Manufacturer())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.manufacturer'),
                $this->urlGenerator->generate('manufacturer_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-industry');
        }
        if ($this->security->isGranted('read', new Storelocation())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.storelocation'),
                $this->urlGenerator->generate('store_location_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-cube');
        }
        if ($this->security->isGranted('read', new Footprint())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.footprint'),
                $this->urlGenerator->generate('footprint_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-microchip');
        }
        if ($this->security->isGranted('read', new Currency())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.currency'),
                $this->urlGenerator->generate('currency_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-coins');
        }
        if ($this->security->isGranted('read', new MeasurementUnit())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.measurement_unit'),
                $this->urlGenerator->generate('measurement_unit_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-balance-scale');
        }
        if ($this->security->isGranted('read', new LabelProfile())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.label_profile'),
                $this->urlGenerator->generate('label_profile_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-qrcode');
        }
        if ($this->security->isGranted('create', new Part())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.edit.part'),
                $this->urlGenerator->generate('part_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-plus-square');
        }

        return $nodes;
    }

    /**
     * This function creates the tree entries for the "show" node of the tools tree.
     *
     * @return TreeViewNode[]
     */
    protected function getShowNodes(): array
    {
        $show_nodes = [];

        if ($this->security->isGranted('@parts.read')) {
            $show_nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.show.all_parts'),
                $this->urlGenerator->generate('parts_show_all')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-globe');
        }

        if ($this->security->isGranted('@attachments.list_attachments')) {
            $show_nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.show.all_attachments'),
                $this->urlGenerator->generate('attachment_list')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-paperclip');
        }

        if ($this->security->isGranted('@tools.statistics')) {
            $show_nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.show.statistics'),
                $this->urlGenerator->generate('statistics_view')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-chart-bar');
        }

        return $show_nodes;
    }

    /**
     * This function creates the tree entries for the "system" node of the tools tree.
     */
    protected function getSystemNodes(): array
    {
        $nodes = [];

        if ($this->security->isGranted('read', new User())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.system.users'),
                $this->urlGenerator->generate('user_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-user');
        }
        if ($this->security->isGranted('read', new Group())) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.system.groups'),
                $this->urlGenerator->generate('group_new')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-users');
        }

        if ($this->security->isGranted('@system.show_logs')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tree.tools.system.event_log'),
                $this->urlGenerator->generate('log_view')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-binoculars');
        }

        if ($this->security->isGranted('@system.server_infos')) {
            $nodes[] = (new TreeViewNode(
                $this->translator->trans('tools.server_infos.title'),
                $this->urlGenerator->generate('tools_server_infos')
            ))->setIcon('fa-fw fa-treeview fa-solid fa-database');
        }

        return $nodes;
    }
}
