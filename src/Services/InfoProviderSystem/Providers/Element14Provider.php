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


namespace App\Services\InfoProviderSystem\Providers;

use App\Entity\Parts\ManufacturingStatus;
use App\Form\InfoProviderSystem\ProviderSelectType;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Element14Provider implements InfoProviderInterface
{

    private const ENDPOINT_URL = 'https://api.element14.com/catalog/products';
    private const FARNELL_STORE_ID = 'de.farnell.com';
    private const API_VERSION_NUMBER = '1.2';
    private const NUMBER_OF_RESULTS = 20;

    private const COMPLIANCE_ATTRIBUTES = ['euEccn', 'hazardous', 'MSL', 'productTraceability', 'rohsCompliant',
        'rohsPhthalatesCompliant', 'SVHC', 'tariffCode', 'usEccn', 'hazardCode'];

    public function __construct(private readonly HttpClientInterface $element14Client, private readonly string $api_key)
    {

    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Farnell element14',
            'description' => 'This provider uses the Farnell element14 API to search for parts.',
            'url' => 'https://www.element14.com/',
        ];
    }

    public function getProviderKey(): string
    {
        return 'element14';
    }

    public function isActive(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * @param  string  $term
     * @return PartDetailDTO[]
     */
    private function queryByTerm(string $term): array
    {
        $response = $this->element14Client->request('GET', self::ENDPOINT_URL, [
            'query' => [
                'term' => $term,
                'storeInfo.id' => self::FARNELL_STORE_ID,
                'resultsSettings.offset' => 0,
                'resultsSettings.numberOfResults' => self::NUMBER_OF_RESULTS,
                'resultsSettings.responseGroup' => 'large',
                'callInfo.apiKey' => $this->api_key,
                'callInfo.responseDataFormat' => 'json',
                'callInfo.version' => self::API_VERSION_NUMBER,
            ],
        ]);

        $arr = $response->toArray();
        if (isset($arr['keywordSearchReturn'])) {
            $products = $arr['keywordSearchReturn']['products'] ?? [];
        } elseif (isset($arr['premierFarnellPartNumberReturn'])) {
            $products = $arr['premierFarnellPartNumberReturn']['products'] ?? [];
        } else {
            throw new \RuntimeException('Unknown response format');
        }

        $result = [];

        foreach ($products as $product) {
            $result[] = new PartDetailDTO(
                provider_key: $this->getProviderKey(), provider_id: $product['sku'],
                name: $product['translatedManufacturerPartNumber'],
                description: $this->displayNameToDescription($product['displayName'], $product['translatedManufacturerPartNumber']),
                manufacturer: $product['vendorName'] ?? $product['brandName'] ?? null,
                mpn: $product['translatedManufacturerPartNumber'],
                preview_image_url: $this->toImageUrl($product['image'] ?? null),
                manufacturing_status: $this->releaseStatusCodeToManufacturingStatus($product['releaseStatusCode'] ?? null),
                provider_url: 'https://' . self::FARNELL_STORE_ID . '/' . $product['sku'],
                datasheets: $this->parseDataSheets($product['datasheets'] ?? null),
                parameters: $this->attributesToParameters($product['attributes'] ?? null),
            );
        }

        return $result;
    }

    /**
     * @param  mixed[]|null  $datasheets
     * @return FileDTO[]|null Array of FileDTOs
     */
    private function parseDataSheets(?array $datasheets): ?array
    {
        if ($datasheets === null || count($datasheets) === 0) {
            return null;
        }

        $result = [];
        foreach ($datasheets as $datasheet) {
            $result[] = new FileDTO(url: $datasheet['url'], name: $datasheet['description']);
        }

        return $result;
   }

    private function toImageUrl(?array $image): ?string
    {
        if ($image === null || count($image) === 0) {
            return null;
        }

        //See Constructing an Image URL: https://partner.element14.com/docs/Product_Search_API_REST__Description
        $locale = 'en_GB';
        if ($image['vrntPath'] === 'nio/') {
            $locale = 'en_US';
        }

        return 'https://' . self::FARNELL_STORE_ID . '/productimages/standard/' . $locale . $image['baseName'];
    }

    /**
     * @param  array|null  $attributes
     * @return ParameterDTO[]|null
     */
    private function attributesToParameters(?array $attributes): ?array
    {
        $result = [];

        foreach ($attributes as $attribute) {
            $group = null;

            //Check if the attribute is a compliance attribute, they get assigned to the compliance group
            if (in_array($attribute['attributeLabel'], self::COMPLIANCE_ATTRIBUTES, true)) {
                $group = 'Compliance';
            }

            //tariffCode is a special case, we prepend a # to prevent conversion to float
            if (in_array($attribute['attributeLabel'], ['tariffCode', 'hazardCode'])) {
                $attribute['attributeValue'] = '#' . $attribute['attributeValue'];
            }

            $result[] = ParameterDTO::parseValueField(name: $attribute['attributeLabel'], value: $attribute['attributeValue'], unit: $attribute['attributeUnit'] ?? null, group: $group);
        }

        return $result;
    }

    private function displayNameToDescription(string $display_name, string $mpn): string
    {
        //Try to find the position of the '-' after the MPN
        $pos = strpos($display_name, $mpn . ' - ');
        if ($pos === false) {
            return $display_name;
        }

        //Remove the MPN and the '-' from the display name
        return substr($display_name, $pos + strlen($mpn) + 3);
    }

    private function releaseStatusCodeToManufacturingStatus(?int $releaseStatusCode): ?ManufacturingStatus
    {
        if ($releaseStatusCode === null) {
            return null;
        }

        return match ($releaseStatusCode) {
            1 => ManufacturingStatus::ANNOUNCED,
            2,4 => ManufacturingStatus::ACTIVE,
            6 => ManufacturingStatus::EOL,
            7 => ManufacturingStatus::DISCONTINUED,
            default => ManufacturingStatus::NOT_SET
        };
    }

    public function searchByKeyword(string $keyword): array
    {
        return $this->queryByTerm('any:' . $keyword);
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $tmp = $this->queryByTerm('id:' . $id);
        if (count($tmp) === 0) {
            throw new \RuntimeException('No part found with ID ' . $id);
        }

        if (count($tmp) > 1) {
            throw new \RuntimeException('Multiple parts found with ID ' . $id);
        }

        return $tmp[0];
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
        ];
    }
}