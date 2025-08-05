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


namespace App\ApiPlatform\DocumentedAPIProperties;

use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * This decorator adds the virtual property names defined by the DocumentedAPIProperty attribute to the property name collection
 * which then get picked up by the openapi schema generator
 */
#[AsDecorator('api_platform.metadata.property.name_collection_factory')]
class PropertyNameCollectionFactory implements PropertyNameCollectionFactoryInterface
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

        if (!class_exists($resourceClass)) {
            return $propertyNames;
        }

        $properties = iterator_to_array($propertyNames);

        $refClass = new ReflectionClass($resourceClass);

        foreach ($refClass->getAttributes(DocumentedAPIProperty::class) as $attribute) {
            /** @var DocumentedAPIProperty $instance */
            $instance = $attribute->newInstance();
            $properties[] = $instance->property;
        }

        return new PropertyNameCollection($properties);
    }
}