<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\ApiPlatform;

use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use function Symfony\Component\String\u;

/**
 * This decorator removes all camelCase property names from the property name collection, if a snake_case version exists.
 * This is a fix for https://github.com/Part-DB/Part-DB-server/issues/862, as the openapi schema generator wrongly collects
 * both camelCase and snake_case property names, which leads to duplicate properties in the schema.
 * This seems to come from the fact that the openapi schema generator uses no serializerContext, which seems then to collect
 * the getters too...
 */
#[AsDecorator('api_platform.metadata.property.name_collection_factory')]
class NormalizePropertyNameCollectionFactory implements PropertyNameCollectionFactoryInterface
{
    public function __construct(private readonly PropertyNameCollectionFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass, array $options = []): PropertyNameCollection
    {
        // Get the default properties from the decorated service
        $propertyNames = $this->decorated->create($resourceClass, $options);

        //Only become active in the context of the openapi schema generation
        if (!isset($options['schema_type'])) {
            return $propertyNames;
        }

        //If we are not in the jsonapi generator (which sets no serializer groups), return the property names as is
        if (isset($options['serializer_groups'])) {
            return $propertyNames;
        }

        //Remove all camelCase property names from the collection, if a snake_case version exists
        $properties = iterator_to_array($propertyNames);

        foreach ($properties as $property) {
            if (str_contains($property, '_')) {
                $camelized = u($property)->camel()->toString();

                //If the camelized version exists, remove it from the collection
                $index = array_search($camelized, $properties, true);
                if ($index !== false) {
                    unset($properties[$index]);
                }
            }
        }

        return new PropertyNameCollection($properties);
    }
}