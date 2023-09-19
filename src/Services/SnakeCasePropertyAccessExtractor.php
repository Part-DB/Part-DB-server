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

namespace App\Services;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;

/**
 * Workaround for using snake_case properties with ReflectionExtractor until this PR is merged:
 * https://github.com/symfony/symfony/pull/51697
 */
#[AsTaggedItem('property_info.access_extractor', priority: 0)]
class SnakeCasePropertyAccessExtractor implements PropertyAccessExtractorInterface
{

    public function __construct(#[Autowire(service: 'property_info.reflection_extractor')]
        private readonly PropertyAccessExtractorInterface $reflectionExtractor)
    {
        //$this->reflectionExtractor = new ReflectionExtractor();
    }

    public function isReadable(string $class, string $property, array $context = [])
    {
        //Null means skip this extractor
        return null;
    }

    /**
     * Camelizes a given string.
     */
    private function camelize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }


    public function isWritable(string $class, string $property, array $context = [])
    {
        //Check writeablity using a camelized property name
        $isWriteable = $this->reflectionExtractor->isWritable($class, $this->camelize($property), $context);
        //If we found a writeable property that way, return true
        if ($isWriteable === true) {
            return true;
        }

        //Otherwise skip this extractor
        return null;
    }
}