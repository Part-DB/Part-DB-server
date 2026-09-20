<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use PHPUnit\Framework\TestCase;

/**
 * @see DTOJsonSchemaConverter
 */
final class DTOJsonSchemaConverterTest extends TestCase
{
    /**
     * Collects every object of the schema, keyed by a readable path, so that a violation names the place.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, array<string, mixed>>
     */
    private function objectsOf(array $node, string $path = 'root'): array
    {
        $objects = [];

        if (($node['type'] ?? null) === 'object' && isset($node['properties'])) {
            $objects[$path] = $node;

            foreach ($node['properties'] as $name => $property) {
                if (is_array($property)) {
                    $objects += $this->objectsOf($property, $path.'.'.$name);
                }
            }
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $objects += $this->objectsOf($node['items'], $path.'[]');
        }

        return $objects;
    }

    public function testTheSchemaIsAcceptedInStrictMode(): void
    {
        //The schema is sent with strict: true, and a provider of the OpenAI family rejects the whole request
        //unless every object forbids additional properties and lists all of its properties as required. Through
        //a gateway that rejection arrives as an unspecific "Provider returned error", so it is worth pinning
        //down here rather than finding out at runtime.
        $schema = (new DTOJsonSchemaConverter())->getJSONSchema();

        self::assertTrue($schema['strict'], 'The schema is sent as a strict one');

        $objects = $this->objectsOf($schema['schema']);
        self::assertGreaterThan(1, count($objects), 'The schema is expected to contain nested objects');

        foreach ($objects as $path => $object) {
            self::assertFalse($object['additionalProperties'] ?? null,
                sprintf('%s has to forbid additional properties', $path));
            self::assertSame(array_keys($object['properties']), $object['required'] ?? [],
                sprintf('%s has to list all of its properties as required', $path));
        }
    }

    public function testOptionalValuesStayExpressibleAsNull(): void
    {
        //Making everything required is only acceptable because an optional value says so through its type
        $schema = (new DTOJsonSchemaConverter())->getJSONSchema();
        $properties = $schema['schema']['properties'];

        self::assertContains('null', (array) $properties['manufacturer']['type']);
        self::assertContains('null', (array) $properties['mpn']['type']);
    }
}
