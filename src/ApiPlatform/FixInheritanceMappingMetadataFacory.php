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


namespace App\ApiPlatform;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\AbstractParameter;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * API Platform has problems with single table inheritance, as it assumes that they all have different endpoints.
 * This decorator fixes this problem by using the parent class for the metadata collection.
 */
#[AsDecorator('api_platform.metadata.resource.metadata_collection_factory')]
class FixInheritanceMappingMetadataFacory implements ResourceMetadataCollectionFactoryInterface
{
    private const SINGLE_INHERITANCE_ENTITY_CLASSES = [
        Attachment::class,
        AbstractParameter::class,
    ];

    private array $cache = [];

    public function __construct(private readonly ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        //If we already have a cached value, we can return it
        if (isset($this->cache[$resourceClass])) {
            return $this->cache[$resourceClass];
        }

        //Check if the resourceClass is a single inheritance class, then we can use the parent class to access it
        foreach (self::SINGLE_INHERITANCE_ENTITY_CLASSES as $class) {
            if (is_a($resourceClass, $class, true)) {
                $this->cache[$resourceClass] = $class;
                break;
            }
        }

        //If it was not found in the list of single inheritance classes, we can use the original class
        if (!isset($this->cache[$resourceClass])) {
            $this->cache[$resourceClass] = $resourceClass;
        }

        return $this->decorated->create($this->cache[$resourceClass] ?? $resourceClass);
    }
}