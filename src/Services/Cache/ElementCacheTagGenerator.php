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


namespace App\Services\Cache;

use Doctrine\Persistence\Proxy;

/**
 * The purpose of this class is to generate cache tags for elements.
 * E.g. to easily invalidate all caches for a given element type.
 */
class ElementCacheTagGenerator
{
    private array $cache = [];

    public function __construct()
    {
    }

    /**
     * Returns a cache tag for the given element type, which can be used to invalidate all caches for this element type.
     * @param  string|object  $element
     * @return string
     */
    public function getElementTypeCacheTag(string|object $element): string
    {
        //Ensure that the given element is a class name
        if (is_object($element)) {
            $element = get_class($element);
        } else { //And that the class exists
            if (!class_exists($element)) {
                throw new \InvalidArgumentException("The given class '$element' does not exist!");
            }
        }

        //Check if the tag is already cached
        if (isset($this->cache[$element])) {
            return $this->cache[$element];
        }

        //If the element is a proxy, then get the real class name of the underlying object
        if ($element instanceof Proxy || str_starts_with($element, 'Proxies\\')) {
            $element = get_parent_class($element);
        }

        //Replace all backslashes with underscores to prevent problems with the cache and save the result
        $this->cache[$element] = str_replace('\\', '_', $element);
        return $this->cache[$element];
    }
}