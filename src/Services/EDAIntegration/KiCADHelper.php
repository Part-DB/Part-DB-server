<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\Services\EDAIntegration;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\EntityListeners\TreeCacheInvalidationListener;
use App\Services\Cache\ElementCacheTagGenerator;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class KiCADHelper
{

    public function __construct(
        private readonly NodesListBuilder $nodesListBuilder,
        private readonly TagAwareCacheInterface $kicadCache,
        private readonly EntityManagerInterface $em,
        private readonly ElementCacheTagGenerator $tagGenerator,
    )
    {

    }

    /**
     * Returns an array of objects containing all categories in the database in the format required by KiCAD.
     * The categories are flattened and sorted by their full path.
     * Categories, which contain no parts, are filtered out.
     * The result is cached for performance and invalidated on category changes.
     * @return array
     */
    public function getCategories(): array
    {
        return $this->kicadCache->get('kicad_categories', function (ItemInterface $item) {
            //Invalidate the cache on category changes
            $secure_class_name = $this->tagGenerator->getElementTypeCacheTag(Category::class);
            $item->tag($secure_class_name);

            $categories = $this->nodesListBuilder->typeToNodesList(Category::class);
            $repo = $this->em->getRepository(Category::class);
            $result = [];
            foreach ($categories as $category) {
                /** @var $category Category */
                //Ensure that the category contains parts
                if ($repo->getPartsCount($category) < 1) {
                    continue;
                }

                //Format the category for KiCAD
                $result[] = [
                    'id' => (string) $category->getId(),
                    'name' => $category->getFullPath('/'),
                ];
            }

            return $result;
        });
    }

    /**
     * Returns an array of objects containing all parts for the given category in the format required by KiCAD.
     * The result is cached for performance and invalidated on category or part changes.
     * @param  Category  $category
     * @return array
     */
    public function getCategoryParts(Category $category): array
    {
        return $this->kicadCache->get('kicad_category_parts_' . $category->getID(), function (ItemInterface $item) use ($category) {
            $item->tag([$this->tagGenerator->getElementTypeCacheTag(Category::class), $this->tagGenerator->getElementTypeCacheTag(Part::class)]);

            $category_repo = $this->em->getRepository(Category::class);
            $parts = $category_repo->getParts($category);

            $result = [];
            foreach ($parts as $part) {
                $result[] = [
                    'id' => (string) $part->getId(),
                    'name' => $part->getName(),
                    'description' => $part->getDescription(),
                ];
            }

            return $result;
        });
    }
}