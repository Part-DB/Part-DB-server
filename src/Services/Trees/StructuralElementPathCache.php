<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\Cache\ElementCacheTagGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Returns the full path of structural elements without walking the (lazy loaded) parent chain of the entity.
 * AbstractStructuralDBElement::getFullPath() needs one query per ancestor, which adds up quickly when rendering tables.
 * Instead, the paths of all elements of a class are built from a single query and cached. The cache is invalidated
 * by the TreeCacheInvalidationListener whenever an element of the class changes.
 * @see \App\Tests\Services\Trees\StructuralElementPathCacheTest
 */
class StructuralElementPathCache implements ResetInterface
{
    /**
     * The maximum number of ancestors which are followed, the same limit as in AbstractStructuralDBElement::getFullPath()
     */
    private const MAX_DEPTH = 20;

    /**
     * @var array<string, array<int, list<string>>> The already loaded paths, indexed by the cache tag of the class
     */
    private array $paths = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly ElementCacheTagGenerator $tagGenerator,
    ) {
    }

    /**
     * Get the full path of the given element, like AbstractStructuralDBElement::getFullPath() does.
     *
     * @param  AbstractStructuralDBElement  $element
     * @param  string  $delimiter the delimiter of the returned string
     * @return string the full path (incl. the name of this element), delimited by $delimiter
     */
    public function getFullPath(AbstractStructuralDBElement $element,
        string $delimiter = AbstractStructuralDBElement::PATH_DELIMITER_ARROW): string
    {
        $path = $element->getID() !== null ? ($this->getPathsForClass($element::class)[$element->getID()] ?? null) : null;

        //Elements, which are not persisted yet (or were created after the paths were loaded) are resolved via the entity
        if ($path === null) {
            return $element->getFullPath($delimiter);
        }

        return implode($delimiter, $path);
    }

    /**
     * @param  class-string<AbstractStructuralDBElement>  $class_name
     * @return array<int, list<string>>
     */
    private function getPathsForClass(string $class_name): array
    {
        //Resolve proxy classes to the real entity class
        $class_name = $this->em->getClassMetadata($class_name)->getName();
        $tag = $this->tagGenerator->getElementTypeCacheTag($class_name);

        return $this->paths[$tag] ??= $this->cache->get('full_paths_'.$tag,
            function (ItemInterface $item) use ($class_name, $tag): array {
                $item->tag([$tag]);

                return $this->buildPaths($class_name);
            });
    }

    /**
     * @param  class-string<AbstractStructuralDBElement>  $class_name
     * @return array<int, list<string>>
     */
    private function buildPaths(string $class_name): array
    {
        $rows = $this->em->createQuery(
            'SELECT e.id, e.name, IDENTITY(e.parent) AS parent_id FROM '.$class_name.' e'
        )->getArrayResult();

        $names = [];
        $parents = [];
        foreach ($rows as $row) {
            $names[$row['id']] = $row['name'];
            $parents[$row['id']] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }

        $paths = [];
        foreach ($names as $id => $name) {
            $path = [$name];
            $current = $parents[$id];
            $depth = 0;

            while ($current !== null && isset($names[$current]) && $depth++ <= self::MAX_DEPTH) {
                $path[] = $names[$current];
                $current = $parents[$current];
            }

            $paths[$id] = array_reverse($path);
        }

        return $paths;
    }

    public function reset(): void
    {
        $this->paths = [];
    }
}
