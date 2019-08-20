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
 *
 * @package App\Controller
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
        if ($category !== null) {
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
        if ($footprint !== null) {
            $tree[] = $builder->elementToTreeNode($footprint);
        } else {
            $tree = $builder->typeToTree(Footprint::class, null);
        }


        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/location/{id}", name="tree_location")
     * @Route("/tree/locations")
     */
    public function locationTree(TreeBuilder $builder, Storelocation $location = null)
    {
        if ($location !== null) {
            $tree[] = $builder->elementToTreeNode($location);
        } else {
            $tree = $builder->typeToTree(Storelocation::class, null);
        }


        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/manufacturer/{id}", name="tree_manufacturer")
     * @Route("/tree/manufacturers")
     */
    public function manufacturerTree(TreeBuilder $builder, Manufacturer $manufacturer = null)
    {
        if ($manufacturer !== null) {
            $tree[] = $builder->elementToTreeNode($manufacturer);
        } else {
            $tree = $builder->typeToTree(Manufacturer::class, null);
        }


        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/supplier/{id}", name="tree_supplier")
     * @Route("/tree/suppliers")
     */
    public function supplierTree(TreeBuilder $builder, Supplier $supplier = null)
    {
        if ($supplier !== null) {
            $tree[] = $builder->elementToTreeNode($supplier);
        } else {
            $tree = $builder->typeToTree(Supplier::class, null);
        }


        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/device/{id}", name="tree_device")
     * @Route("/tree/devices")
     */
    public function deviceTree(TreeBuilder $builder, Device $device = null)
    {
        if ($device !== null) {
            $tree[] = $builder->elementToTreeNode($device);
        } else {
            $tree = $builder->typeToTree(Device::class, null);
        }


        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }


}
