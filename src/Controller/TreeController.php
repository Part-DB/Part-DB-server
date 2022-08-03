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

namespace App\Controller;

use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Services\Trees\ToolsTreeBuilder;
use App\Services\Trees\TreeViewGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller has the purpose to provide the data for all treeviews.
 *
 * @Route("/tree")
 */
class TreeController extends AbstractController
{
    protected $treeGenerator;

    public function __construct(TreeViewGenerator $treeGenerator)
    {
        $this->treeGenerator = $treeGenerator;
    }

    /**
     * @Route("/tools", name="tree_tools")
     */
    public function tools(ToolsTreeBuilder $builder): JsonResponse
    {
        $tree = $builder->getTree();

        return new JsonResponse($tree);
    }

    /**
     * @Route("/category/{id}", name="tree_category")
     * @Route("/categories", name="tree_category_root")
     */
    public function categoryTree(?Category $category = null): JsonResponse
    {
        $tree = $this->treeGenerator->getTreeView(Category::class, $category, 'list_parts_root');

        return new JsonResponse($tree);
    }

    /**
     * @Route("/footprint/{id}", name="tree_footprint")
     * @Route("/footprints", name="tree_footprint_root")
     */
    public function footprintTree(?Footprint $footprint = null): JsonResponse
    {
        $tree = $this->treeGenerator->getTreeView(Footprint::class, $footprint, 'list_parts_root');

        return new JsonResponse($tree);
    }

    /**
     * @Route("/location/{id}", name="tree_location")
     * @Route("/locations", name="tree_location_root")
     */
    public function locationTree(?Storelocation $location = null): JsonResponse
    {
        $tree = $this->treeGenerator->getTreeView(Storelocation::class, $location, 'list_parts_root');

        return new JsonResponse($tree);
    }

    /**
     * @Route("/manufacturer/{id}", name="tree_manufacturer")
     * @Route("/manufacturers", name="tree_manufacturer_root")
     */
    public function manufacturerTree(?Manufacturer $manufacturer = null): JsonResponse
    {
        $tree = $this->treeGenerator->getTreeView(Manufacturer::class, $manufacturer, 'list_parts_root');

        return new JsonResponse($tree);
    }

    /**
     * @Route("/supplier/{id}", name="tree_supplier")
     * @Route("/suppliers", name="tree_supplier_root")
     */
    public function supplierTree(?Supplier $supplier = null): JsonResponse
    {
        $tree = $this->treeGenerator->getTreeView(Supplier::class, $supplier, 'list_parts_root');

        return new JsonResponse($tree);
    }

    /**
     * @Route("/device/{id}", name="tree_device")
     * @Route("/devices", name="tree_device_root")
     */
    public function deviceTree(?Device $device = null): JsonResponse
    {
        $tree = $this->treeGenerator->getTreeView(Device::class, $device, 'devices');

        return new JsonResponse($tree);
    }
}
