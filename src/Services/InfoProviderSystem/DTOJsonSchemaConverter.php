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


namespace App\Services\InfoProviderSystem;

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;

/**
 * This class allows to convert the JSON data returned by an LLM into the DTOs used by the info provider system later.
 */
final class DTOJsonSchemaConverter
{
    /**
     * Returns the JSON schema, that defines the expected structure of the JSON data returned by the LLM.
     * @return array
     */
    public function getJSONSchema(): array
    {
        //Anthropic rejects schemas with more than 16 union typed (e.g. nullable) properties, as they are expensive to
        //compile. So only numbers and booleans are nullable, where null means something else than any value. Texts
        //which are not known are empty strings instead, which jsonToDTO() treats like null.
        $schema = [
            'name' => 'part_detail',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'description' => ['type' => 'string', 'description' => 'A short description of the product, maybe containing the most important things. Onnly One line.'],
                    'manufacturer' => ['type' => 'string', 'description' => 'Manufacturer name'],
                    'mpn' => ['type' => 'string', 'description' => 'Manufacturer Part Number'],
                    'category' => ['type' => 'string', 'description' => 'Product category, e.g. "Passive components -> Resistors"'],
                    //An enum must not be combined with a nullable type (["string", "null"]): Anthropic rejects such a schema
                    //("Enum value 'active' does not match declared type"), so "unknown" takes the place of null here
                    'manufacturing_status' => ['type' => 'string', 'enum' => ['active', 'obsolete', 'nrfnd', 'discontinued', 'unknown'], 'description' => 'Manufacturing status, "unknown" if it is not stated'],
                    'footprint' => ['type' => 'string', 'description' => 'Package/footprint type, like "SOT-23", "DIP-8", "QFN-32" etc.'],
                    'mass' => ['type' => ['number', 'null'], 'description' => 'Mass of the product in grams'],
                    'gtin' => ['type' => 'string', 'description' => 'Global Trade Item Number (GTIN) / EAN / UPC code for barcodes'],
                    'notes' => ['type' => 'string', 'description' => 'Optional long description of the part with more details than description. Can be markdown formatted.'],
                    'parameters' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'symbol' => ['type' => 'string', 'description' => 'An optional quantity symbol for the parameter in latex code, like R_1'],
                                'value_typical' => ['type' => ['number', 'null'], 'description' => 'The typical value of the parameter. For example, for a resistor this could be 100 for a 100 Ohm resistor. Also used if only one numeric value is given. If used an unit should be given'],
                                'value_min' => ['type' => ['number', 'null'], 'description' => 'If a range is given for the parameter, this is the minimum value. Null if no range is given.'],
                                'value_max' => ['type' => ['number', 'null'], 'description' => 'If a range is given for the parameter, this is the maximum value. Null if not a range.'],
                                'value_text' => ['type' => 'string', 'description' => 'When a value is not numeric it can be put here as text. Only use if it does not fit in value_min, value_typical or value_max. E.g. "Yes", "Red", etc.'],
                                'group' => ['type' => 'string', 'description' => 'An optional group name for the parameter, e.g. "Electrical parameters", "Mechanical parameters" etc.'],
                                'unit' => ['type' => 'string', 'description' => 'The unit of the parameter values, e.g. kg, Ohm, V, etc.'],
                            ],
                            'required' => ['name', 'value_typical', 'value_min', 'value_max', 'value_text']
                        ],
                    ],
                    'datasheets' => [
                        'description' => 'A list of datasheets, manuals, or other technical documents related to the product. Not images, but actual documents, preferably PDFs.',
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'url' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                            ],
                            'required' => ['url'],
                        ],
                    ],
                    'images' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'url' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                            ],
                            'required' => ['url'],
                        ],
                    ],
                    'vendor_infos' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'distributor_name' => ['type' => 'string', 'description' => 'Name of the distributor or vendor. Typically the shop name'],
                                'order_number' => ['type' => 'string', 'description' => 'The order number or SKU used by the distributor. Optional, but can help to find the product on the distributor website.'],
                                'product_url' => ['type' => 'string'],
                                'prices_include_vat' => ['type' => ['boolean', 'null'], 'description' => 'Whether the prices include VAT or not. Null if unknown.'],
                                'prices' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'minimum_quantity' => ['type' => 'integer', 'description' => 'Minimum quantity for this price tier. 1 when no tiered pricing is available.'],
                                            'price' => ['type' => 'number', 'description' => 'Price for the given minimum quantity.'],
                                            'currency' => ['type' => 'string', 'description' => 'Currency ISO code, e.g. USD'],
                                        ],
                                        'required' => ['minimum_quantity', 'price', 'currency'],
                                    ],
                                ],
                            ],
                            'required' => ['distributor_name', 'product_url'],
                        ],
                    ],
                    'manufacturer_product_url' => ['type' => 'string', 'description' => 'Manufacturer product page URL'],
                ],
                'required' => ['name', 'description'],
            ]
        ];

        //The schema is written out above for readability; the rules of the strict mode are applied here, so
        //that they cannot be forgotten when a property is added later
        $schema['schema'] = $this->applyStrictModeRules($schema['schema']);

        return $schema;
    }

    /**
     * Applies the two rules a schema has to follow to be accepted in strict mode.
     *
     * Providers of the OpenAI family reject a strict schema unless every object forbids additional properties
     * and lists all of its properties as required - an optional value is expressed by allowing null in its type
     * instead, which is what the schema above already does. Without this the whole extraction fails with
     * "Invalid schema for response_format", and through a gateway that arrives as an unspecific
     * "Provider returned error".
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function applyStrictModeRules(array $schema): array
    {
        if (($schema['type'] ?? null) === 'object' && isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['properties'] = array_map($this->applyStrictModeRules(...), $schema['properties']);
            $schema['additionalProperties'] = false;
            $schema['required'] = array_keys($schema['properties']);
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->applyStrictModeRules($schema['items']);
        }

        return $schema;
    }

    public function jsonToDTO(array $data, string $providerKey, string $providerId, ?string $productUrl = null, string $distributorNameFallback = '???'): PartDetailDTO
    {
        // Map manufacturing status
        $manufacturingStatus = null;
        if (!empty($data['manufacturing_status'])) {
            $status = strtolower((string) $data['manufacturing_status']);
            $manufacturingStatus = match ($status) {
                'active' => ManufacturingStatus::ACTIVE,
                'obsolete', 'discontinued' => ManufacturingStatus::DISCONTINUED,
                'nrfnd', 'not recommended for new designs' => ManufacturingStatus::NRFND,
                'eol' => ManufacturingStatus::EOL,
                'announced' => ManufacturingStatus::ANNOUNCED,
                //Includes "unknown", which the schema uses instead of null
                default => null,
            };
        }

        // Build parameters
        $parameters = null;
        if (!empty($data['parameters']) && is_array($data['parameters'])) {
            $parameters = [];
            foreach ($data['parameters'] as $p) {
                if (!empty($p['name'])) {
                    $parameters[] = new ParameterDTO(
                        name: $p['name'],
                        value_text: self::textOrNull($p['value_text'] ?? null),
                        value_typ: isset($p['value_typical']) && is_numeric($p['value_typical']) ? (float) $p['value_typical'] : null,
                        value_min: isset($p['value_min']) && is_numeric($p['value_min']) ? (float) $p['value_min'] : null,
                        value_max: isset($p['value_max']) && is_numeric($p['value_max']) ? (float) $p['value_max'] : null,
                        unit: self::textOrNull($p['unit'] ?? null),
                        symbol: self::textOrNull($p['symbol'] ?? null),
                        group: self::textOrNull($p['group'] ?? null),
                    );
                }
            }
        }

        // Build datasheets
        $datasheets = null;
        if (!empty($data['datasheets']) && is_array($data['datasheets'])) {
            $datasheets = [];
            foreach ($data['datasheets'] as $d) {
                if (!empty($d['url'])) {
                    $datasheets[] = new FileDTO(
                        url: $d['url'],
                        name: $d['description'] ?? 'Datasheet'
                    );
                }
            }
        }

        // Build images
        $images = null;
        if (!empty($data['images']) && is_array($data['images'])) {
            $images = [];
            foreach ($data['images'] as $i) {
                if (!empty($i['url'])) {
                    $images[] = new FileDTO(
                        url: $i['url'],
                        name: $i['description'] ?? 'Image'
                    );
                }
            }
        }

        // Build vendor infos
        $vendorInfos = null;
        if (!empty($data['vendor_infos']) && is_array($data['vendor_infos'])) {
            $vendorInfos = [];
            foreach ($data['vendor_infos'] as $v) {
                $prices = [];
                if (!empty($v['prices']) && is_array($v['prices'])) {
                    foreach ($v['prices'] as $p) {
                        $prices[] = new PriceDTO(
                            minimum_discount_amount: (int) ($p['minimum_quantity'] ?? 1),
                            price: (string) ($p['price'] ?? 0),
                            currency_iso_code: $p['currency'] ?? null,
                            price_related_quantity: 1,
                        );
                    }
                }

                $vendorInfos[] = new PurchaseInfoDTO(
                    distributor_name: $v['distributor_name'] ?? $distributorNameFallback,
                    order_number: self::textOrNull($v['order_number'] ?? null) ?? 'Unknown',
                    prices: $prices,
                    product_url: self::textOrNull($v['product_url'] ?? null) ?? $productUrl,
                    prices_include_vat: $v['prices_include_vat'] ?? null,
                );
            }
        }

        // Get preview image URL
        $previewImageUrl = null;
        if (!empty($data['images']) && is_array($data['images']) && !empty($data['images'][0]['url'])) {
            $previewImageUrl = $data['images'][0]['url'];
        }

        return new PartDetailDTO(
            provider_key: $providerKey,
            provider_id: $providerId,
            name: $data['name'] ?? 'Unknown',
            description: $data['description'] ?? '',
            category: self::textOrNull($data['category'] ?? null),
            manufacturer: self::textOrNull($data['manufacturer'] ?? null),
            mpn: self::textOrNull($data['mpn'] ?? null),
            preview_image_url: $previewImageUrl,
            manufacturing_status: $manufacturingStatus,
            provider_url: $productUrl,
            footprint: self::textOrNull($data['footprint'] ?? null),
            gtin: self::textOrNull($data['gtin'] ?? null),
            notes: self::textOrNull($data['notes'] ?? null),
            datasheets: $datasheets,
            images: $images,
            parameters: $parameters,
            vendor_infos: $vendorInfos,
            mass: isset($data['mass']) && is_numeric($data['mass']) ? (float) $data['mass'] : null,
            manufacturer_product_url: self::textOrNull($data['manufacturer_product_url'] ?? null),
        );
    }

    /**
     * Returns the given text, or null if it is empty. The schema uses empty strings for unknown texts (see getJSONSchema()).
     */
    private static function textOrNull(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
