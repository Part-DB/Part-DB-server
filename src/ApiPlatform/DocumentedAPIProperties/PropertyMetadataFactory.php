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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * This decorator adds the virtual properties defined by the DocumentedAPIProperty attribute to the property metadata
 * which then get picked up by the openapi schema generator
 */
#[AsDecorator('api_platform.metadata.property.metadata_factory')]
class PropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(private PropertyMetadataFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $metadata = $this->decorated->create($resourceClass, $property, $options);

        //Only become active in the context of the openapi schema generation
        if (!isset($options['schema_type'])) {
            return $metadata;
        }

        if (!class_exists($resourceClass)) {
            return $metadata;
        }

        $refClass = new ReflectionClass($resourceClass);
        $attributes = $refClass->getAttributes(DocumentedAPIProperty::class);

        //Look for the DocumentedAPIProperty attribute with the given property name
        foreach ($attributes as $attribute) {
            /** @var DocumentedAPIProperty $api_property */
            $api_property = $attribute->newInstance();
            //If attribute not matches the property name, skip it
            if ($api_property->property !== $property) {
                continue;
            }

            //Return the virtual property
            return $api_property->toAPIProperty();
        }

        return $metadata;
    }
}