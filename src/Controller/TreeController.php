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

use App\Entity\Category;
use App\Helpers\TreeViewNode;
use App\Services\ToolsTreeBuilder;
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
     * @Route("/tree/tools/", name="tree_tools")
     */
    public function tools(ToolsTreeBuilder $builder)
    {
        $tree = $builder->getTree();

        //Ignore null values, to save data
        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }

    /**
     * @Route("/tree/category/{id}", name="tree_category")
     */
    public function categoryTree(TreeBuilder $builder, Category $category = null)
    {
        if($category != null) {
            $tree[] = $builder->elementToTreeNode($category);
        } else {
            $tree = $builder->typeToTree(Category::class);
        }


        return $this->json($tree, 200, [], ['skip_null_values' => true]);
    }




}
