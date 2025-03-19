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

namespace App\Controller;

use App\Entity\AssemblySystem\Assembly;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Services\Trees\ToolsTreeBuilder;
use App\Services\Trees\TreeViewGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * This controller has the purpose to provide the data for all treeviews.
 */
#[Route(path: '/tree')]
class TreeController extends AbstractController
{
    public function __construct(protected TreeViewGenerator $treeGenerator)
    {
    }

    #[Route(path: '/tools', name: 'tree_tools')]
    public function tools(ToolsTreeBuilder $builder): JsonResponse
    {
        $tree = $builder->getTree();

        return new JsonResponse($tree);
    }

    #[Route(path: '/category/{id}', name: 'tree_category')]
    #[Route(path: '/categories', name: 'tree_category_root')]
    public function categoryTree(?Category $category = null): JsonResponse
    {
        if ($this->isGranted('@parts.read') && $this->isGranted('@categories.read')) {
            $tree = $this->treeGenerator->getTreeView(Category::class, $category, 'list_parts_root');
        } else {
            return new JsonResponse("Access denied", Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($tree);
    }

    #[Route(path: '/footprint/{id}', name: 'tree_footprint')]
    #[Route(path: '/footprints', name: 'tree_footprint_root')]
    public function footprintTree(?Footprint $footprint = null): JsonResponse
    {
        if ($this->isGranted('@parts.read') && $this->isGranted('@footprints.read')) {
            $tree = $this->treeGenerator->getTreeView(Footprint::class, $footprint, 'list_parts_root');
        } else {
            return new JsonResponse("Access denied", Response::HTTP_FORBIDDEN);
        }
        return new JsonResponse($tree);
    }

    #[Route(path: '/location/{id}', name: 'tree_location')]
    #[Route(path: '/locations', name: 'tree_location_root')]
    public function locationTree(?StorageLocation $location = null): JsonResponse
    {
        if ($this->isGranted('@parts.read') && $this->isGranted('@storelocations.read')) {
            $tree = $this->treeGenerator->getTreeView(StorageLocation::class, $location, 'list_parts_root');
        } else {
            return new JsonResponse("Access denied", Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($tree);
    }

    #[Route(path: '/manufacturer/{id}', name: 'tree_manufacturer')]
    #[Route(path: '/manufacturers', name: 'tree_manufacturer_root')]
    public function manufacturerTree(?Manufacturer $manufacturer = null): JsonResponse
    {
        if ($this->isGranted('@parts.read') && $this->isGranted('@manufacturers.read')) {
            $tree = $this->treeGenerator->getTreeView(Manufacturer::class, $manufacturer, 'list_parts_root');
        } else {
            return new JsonResponse("Access denied", Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($tree);
    }

    #[Route(path: '/supplier/{id}', name: 'tree_supplier')]
    #[Route(path: '/suppliers', name: 'tree_supplier_root')]
    public function supplierTree(?Supplier $supplier = null): JsonResponse
    {
        if ($this->isGranted('@parts.read') && $this->isGranted('@suppliers.read')) {
            $tree = $this->treeGenerator->getTreeView(Supplier::class, $supplier, 'list_parts_root');
        } else {
            return new JsonResponse("Access denied", Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($tree);
    }

    #[Route(path: '/device/{id}', name: 'tree_device')]
    #[Route(path: '/devices', name: 'tree_device_root')]
    public function deviceTree(?Project $device = null): JsonResponse
    {
        if ($this->isGranted('@projects.read')) {
            $tree = $this->treeGenerator->getTreeView(Project::class, $device, 'devices');
        } else {
            return new JsonResponse("Access denied", Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($tree);
    }

    #[Route(path: '/assembly/{id}', name: 'tree_assembly')]
    #[Route(path: '/assemblies', name: 'tree_assembly_root')]
    public function assemblyTree(?Assembly $assembly = null): JsonResponse
    {
        if ($this->isGranted('@assemblies.read')) {
            $tree = $this->treeGenerator->getTreeView(Assembly::class, $assembly, 'assemblies');
        } else {
            return new JsonResponse("Access denied", Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($tree);
    }
}
