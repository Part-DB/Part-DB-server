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

use App\Entity\Parts\ManufacturingStatus;
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

    public function testOptionalValuesStayExpressible(): void
    {
        //Making everything required is only acceptable because an optional value can still be left out: texts as
        //an empty string, numbers through a nullable type (0 is a real value, so it can not be used for that)
        $schema = (new DTOJsonSchemaConverter())->getJSONSchema();
        $properties = $schema['schema']['properties'];

        self::assertSame('string', $properties['manufacturer']['type']);
        self::assertSame('string', $properties['mpn']['type']);
        self::assertContains('null', (array) $properties['mass']['type']);
        self::assertContains('null', (array) $properties['parameters']['items']['properties']['value_typical']['type']);
    }

    /**
     * Collects every property of the schema, which allows more than one type (a type array or anyOf).
     * @return string[] The paths of these properties
     */
    private function unionsOf(array $schema, string $path = '$'): array
    {
        $unions = (is_array($schema['type'] ?? null) || isset($schema['anyOf'])) ? [$path] : [];

        foreach ($schema['properties'] ?? [] as $name => $property) {
            $unions = [...$unions, ...$this->unionsOf($property, $path.'.'.$name)];
        }
        if (isset($schema['items'])) {
            $unions = [...$unions, ...$this->unionsOf($schema['items'], $path.'[]')];
        }

        return $unions;
    }

    public function testTheNumberOfUnionTypesStaysBelowTheLimitOfAnthropic(): void
    {
        //Anthropic rejects a schema with more than 16 union typed properties ("Schemas contains too many parameters
        //with union types"), which makes the whole extraction fail with every Claude model. Texts are therefore
        //not nullable, only numbers and booleans are.
        $unions = $this->unionsOf((new DTOJsonSchemaConverter())->getJSONSchema()['schema']);

        self::assertLessThanOrEqual(16, count($unions), 'Union typed properties: '.implode(', ', $unions));
    }

    public function testEmptyTextsBecomeNull(): void
    {
        $dto = (new DTOJsonSchemaConverter())->jsonToDTO([
            'name' => 'BC547', 'description' => 'NPN transistor',
            'manufacturer' => '', 'mpn' => '  ', 'category' => '', 'footprint' => '', 'gtin' => '', 'notes' => '',
            'manufacturer_product_url' => '',
            'parameters' => [['name' => 'hFE', 'value_typical' => 200, 'value_min' => null, 'value_max' => null,
                'value_text' => '', 'symbol' => '', 'group' => '', 'unit' => '']],
            'vendor_infos' => [['distributor_name' => 'Shop', 'order_number' => '', 'product_url' => '',
                'prices_include_vat' => null, 'prices' => []]],
        ], 'test', 'id', 'https://example.com/part');

        //An empty text means "not known", otherwise a manufacturer or footprint without a name would be created
        self::assertNull($dto->manufacturer);
        self::assertNull($dto->mpn);
        self::assertNull($dto->category);
        self::assertNull($dto->footprint);
        self::assertNull($dto->gtin);
        self::assertNull($dto->notes);
        self::assertNull($dto->manufacturer_product_url);

        $parameter = $dto->parameters[0];
        self::assertSame(200.0, $parameter->value_typ);
        self::assertNull($parameter->value_text);
        self::assertNull($parameter->symbol);
        self::assertNull($parameter->group);
        self::assertNull($parameter->unit);

        //Here the empty texts fall back to the same values as missing ones
        self::assertSame('Unknown', $dto->vendor_infos[0]->order_number);
        self::assertSame('https://example.com/part', $dto->vendor_infos[0]->product_url);
    }

    public function testFilledTextsAreKept(): void
    {
        $dto = (new DTOJsonSchemaConverter())->jsonToDTO([
            'name' => 'BC547', 'description' => '', 'manufacturer' => 'Example Semi', 'footprint' => 'TO-92',
            'parameters' => [['name' => 'I_C', 'value_typical' => 0.1, 'unit' => 'A', 'value_text' => '']],
        ], 'test', 'id');

        self::assertSame('Example Semi', $dto->manufacturer);
        self::assertSame('TO-92', $dto->footprint);
        self::assertSame('A', $dto->parameters[0]->unit);
    }

    /**
     * Collects every node of the schema, which restricts its values with an enum.
     * @return array<string, array> The nodes, indexed by their path in the schema
     */
    private function enumsOf(array $schema, string $path = '$'): array
    {
        $enums = isset($schema['enum']) ? [$path => $schema] : [];

        foreach ($schema['properties'] ?? [] as $name => $property) {
            $enums += $this->enumsOf($property, $path.'.'.$name);
        }
        if (isset($schema['items'])) {
            $enums += $this->enumsOf($schema['items'], $path.'[]');
        }

        return $enums;
    }

    public function testEnumsHaveASingleType(): void
    {
        //Anthropic rejects an enum on a nullable type (["string", "null"]) with "Enum value 'active' does not match
        //declared type", which makes the whole extraction fail with every Claude model
        $enums = $this->enumsOf((new DTOJsonSchemaConverter())->getJSONSchema()['schema']);
        self::assertNotEmpty($enums, 'The schema is expected to contain enums');

        foreach ($enums as $path => $node) {
            self::assertIsString($node['type'] ?? null, sprintf('%s has to declare exactly one type', $path));
            foreach ($node['enum'] as $value) {
                self::assertSame($node['type'], get_debug_type($value),
                    sprintf('The enum value %s of %s has to match the declared type', var_export($value, true), $path));
            }
        }
    }

    public function testUnknownManufacturingStatusBecomesNull(): void
    {
        $converter = new DTOJsonSchemaConverter();

        $dto = $converter->jsonToDTO(['name' => 'BC547', 'description' => '', 'manufacturing_status' => 'unknown'], 'test', 'id');
        self::assertNull($dto->manufacturing_status);

        $dto = $converter->jsonToDTO(['name' => 'BC547', 'description' => '', 'manufacturing_status' => 'nrfnd'], 'test', 'id');
        self::assertSame(ManufacturingStatus::NRFND, $dto->manufacturing_status);
    }
}
