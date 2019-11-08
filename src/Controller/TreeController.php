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

namespace App\Controller;

use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Services\ToolsTreeBuilder;
use App\Services\TreeBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller has the purpose to provide the data for all treeviews.
 */
class TreeController extends AbstractController
{
    /**
     * @Route("/tree/tools", name="tree_tools")
     */
    public function tools(ToolsTreeBuilder $builder)
    {
        $tree = $builder->getTree();

        //Ignore null values, to save data
        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/category/{id}", name="tree_category")
     * @Route("/tree/categories")
     */
    public function categoryTree(TreeBuilder $builder, Category $category = null)
    {
        if (null !== $category) {
            $tree[] = $builder->elementToTreeNode($category);
        } else {
            $tree = $builder->typeToTree(Category::class);
        }

        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/footprint/{id}", name="tree_footprint")
     * @Route("/tree/footprints")
     */
    public function footprintTree(TreeBuilder $builder, Footprint $footprint = null)
    {
        if (null !== $footprint) {
            $tree[] = $builder->elementToTreeNode($footprint);
        } else {
            $tree = $builder->typeToTree(Footprint::class);
        }

        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/location/{id}", name="tree_location")
     * @Route("/tree/locations")
     */
    public function locationTree(TreeBuilder $builder, Storelocation $location = null)
    {
        if (null !== $location) {
            $tree[] = $builder->elementToTreeNode($location);
        } else {
            $tree = $builder->typeToTree(Storelocation::class);
        }

        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/manufacturer/{id}", name="tree_manufacturer")
     * @Route("/tree/manufacturers")
     */
    public function manufacturerTree(TreeBuilder $builder, Manufacturer $manufacturer = null)
    {
        if (null !== $manufacturer) {
            $tree[] = $builder->elementToTreeNode($manufacturer);
        } else {
            $tree = $builder->typeToTree(Manufacturer::class);
        }

        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/supplier/{id}", name="tree_supplier")
     * @Route("/tree/suppliers")
     */
    public function supplierTree(TreeBuilder $builder, Supplier $supplier = null)
    {
        if (null !== $supplier) {
            $tree[] = $builder->elementToTreeNode($supplier);
        } else {
            $tree = $builder->typeToTree(Supplier::class);
        }

        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/device/{id}", name="tree_device")
     * @Route("/tree/devices")
     */
    public function deviceTree(TreeBuilder $builder, Device $device = null)
    {
        if (null !== $device) {
            $tree[] = $builder->elementToTreeNode($device);
        } else {
            $tree = $builder->typeToTree(Device::class, null);
        }

        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }
}
