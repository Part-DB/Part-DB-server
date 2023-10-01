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

/**
 * When this attribute is applied to a class, an property will be added to the API documentation using the given parameters.
 * This is useful for adding properties to the API documentation, that are not existing in the entity class itself,
 * but get added by a normalizer.
 */
#[\Attribute(\Attribute::TARGET_CLASS| \Attribute::IS_REPEATABLE)]
final class DocumentedAPIProperty
{
    public function __construct(
        /**
         * @param string $schemaName The name of the schema to add the property to (e.g. "Part-Read")
         */
        public readonly string $schemaName,
        /**
         * @var string $property The name of the property to add to the schema
         */
        public readonly string $property,
        public readonly string $type = 'string',
        public readonly bool $nullable = true,
        /**
         * @var string $description The description of the property
         */
        public readonly ?string $description = null,
        /**
         * @var bool True if the property is readable, false otherwise
         */
        public readonly bool $readable = true,
        /**
         * @var bool True if the property is writable, false otherwise
         */
        public readonly bool $writeable = false,
        /**
         * @var string|null The deprecation reason of the property
         */
        public readonly ?string $deprecationReason = null,
        /** @var mixed The default value of this property */
        public readonly mixed $default = null,
        public readonly mixed $example = null,
    )
    {
    }
}