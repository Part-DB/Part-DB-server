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
        return [
            'name' => 'clock',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'description' => ['type' => 'string', 'description' => 'Product description'],
                    'manufacturer' => ['type' => ['string', 'null'], 'description' => 'Manufacturer name'],
                    'mpn' => ['type' => ['string', 'null'], 'description' => 'Manufacturer Part Number'],
                    'category' => ['type' => ['string', 'null'], 'description' => 'Product category'],
                    'manufacturing_status' => ['type' => ['string', 'null'], 'enum' => ['active', 'obsolete', 'nrfnd', 'discontinued', null], 'description' => 'Manufacturing status'],
                    'footprint' => ['type' => ['string', 'null'], 'description' => 'Package/footprint type'],
                    'mass' => ['type' => ['number', 'null'], 'description' => 'Mass of the product in grams'],
                    'gtin' => ['type' => ['string', 'null'], 'description' => 'Global Trade Item Number (GTIN) / EAN / UPC code'],
                    'parameters' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                                'unit' => ['type' => ['string', 'null']],
                            ],
                            'required' => ['name', 'value'],
                        ],
                    ],
                    'datasheets' => [
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
                                'order_number' => ['type' => ['string', 'null'], 'description' => 'The order number or SKU used by the distributor. Optional, but can help to find the product on the distributor website.'],
                                'product_url' => ['type' => 'string'],
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
                    'manufacturer_product_url' => ['type' => ['string', 'null'], 'description' => 'Manufacturer product page URL'],
                ],
                'required' => ['name', 'description'],
            ]
        ];
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
                default => null,
            };
        }

        // Build parameters
        $parameters = null;
        if (!empty($data['parameters']) && is_array($data['parameters'])) {
            $parameters = [];
            foreach ($data['parameters'] as $p) {
                if (!empty($p['name'])) {
                    $value = $p['value'] ?? '';
                    $unit = $p['unit'] ?? null;
                    // Combine value and unit for parsing
                    $valueWithUnit = $unit ? $value . ' ' . $unit : $value;
                    $parameters[] = ParameterDTO::parseValueField(
                        name: $p['name'],
                        value: $valueWithUnit
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
                            currency_iso_code: $p['currency'] ?? 'USD',
                            price_related_quantity: (int) ($p['minimum_quantity'] ?? 1),
                        );
                    }
                }

                $vendorInfos[] = new PurchaseInfoDTO(
                    distributor_name: $v['distributor_name'] ?? $distributorNameFallback,
                    order_number: $v['order_number'] ?? 'Unknown',
                    prices: $prices,
                    product_url: $v['product_url'] ?? $productUrl,
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
            category: $data['category'] ?? null,
            manufacturer: $data['manufacturer'] ?? null,
            mpn: $data['mpn'] ?? null,
            preview_image_url: $previewImageUrl,
            manufacturing_status: $manufacturingStatus,
            provider_url: $productUrl,
            footprint: $data['footprint'] ?? null,
            gtin: $data['gtin'] ?? null,
            notes: null,
            datasheets: $datasheets,
            images: $images,
            parameters: $parameters,
            vendor_infos: $vendorInfos,
            mass: isset($data['mass']) && is_numeric($data['mass']) ? (float) $data['mass'] : null,
            manufacturer_product_url: $data['manufacturer_product_url'] ?? null,
        );
    }

}
