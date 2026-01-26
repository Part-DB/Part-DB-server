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


namespace App\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\ConradSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ConradProvider implements InfoProviderInterface
{

    private const SEARCH_ENDPOINT = '/search/1/v3/facetSearch';

    public function __construct(private HttpClientInterface $httpClient, private ConradSettings $settings)
    {
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Conrad',
            'description' => 'Retrieves part information from conrad.de',
            'url' => 'https://www.conrad.de/',
            'disabled_help' => 'Set API key in settings',
            'settings_class' => ConradSettings::class,
        ];
    }

    public function getProviderKey(): string
    {
        return 'conrad';
    }

    public function isActive(): bool
    {
        return !empty($this->settings->apiKey);
    }

    private function getProductUrl(string $productId): string
    {
        return 'https://' . $this->settings->shopID->getDomain() . '/' . $this->settings->shopID->getLanguage() . '/p/' . $productId;
    }

    private function getFootprintFromTechnicalDetails(array $technicalDetails): ?string
    {
        foreach ($technicalDetails as $detail) {
            if ($detail['name'] === 'ATT_LOV_HOUSING_SEMICONDUCTORS') {
                return $detail['values'][0] ?? null;
            }
        }

        return null;
    }

    private function getFootprintFromTechnicalAttributes(array $technicalDetails): ?string
    {
        foreach ($technicalDetails as $detail) {
            if ($detail['attributeID'] === 'ATT.LOV.HOUSING_SEMICONDUCTORS') {
                return $detail['values'][0]['value'] ?? null;
            }
        }

        return null;
    }

    private function technicalAttributesToParameters(array $technicalAttributes): array
    {
        $parameters = [];
        foreach ($technicalAttributes as $attribute) {
            if ($attribute['multiValue'] ?? false === true) {
                throw new \LogicException('Multi value attributes are not supported yet');
            }
            $parameters[] = ParameterDTO::parseValueField($attribute['attributeName'],
                $attribute['values'][0]['value'], $attribute['values'][0]['unit']['name'] ?? null);
        }

        return $parameters;
    }

    public function searchByKeyword(string $keyword): array
    {
        $url = $this->settings->shopID->getAPIRoot() . self::SEARCH_ENDPOINT . '/'
            . $this->settings->shopID->getDomainEnd() . '/' . $this->settings->shopID->getLanguage()
            . '/' . $this->settings->shopID->getCustomerType();

        $response = $this->httpClient->request('POST', $url, [
            'query' => [
                'apikey' => $this->settings->apiKey,
            ],
            'json' => [
                'query' => $keyword,
                'size' => 25,
            ],
        ]);

        $out = [];
        $results = $response->toArray();

        foreach($results['hits'] as $result) {

            $out[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $result['productId'],
                name: $result['title'],
                description: '',
                manufacturer: $result['brand']['name'] ?? null,
                mpn: $result['manufacturerId'] ??  null,
                preview_image_url: $result['image'] ?? null,
                provider_url: $this->getProductUrl($result['productId']),
                footprint: $this->getFootprintFromTechnicalDetails($result['technicalDetails'] ?? []),
            );
        }

        return $out;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $productInfoURL = $this->settings->shopID->getAPIRoot() . '/product/1/service/' . $this->settings->shopID->getShopID()
            . '/product/' . $id;

        $response = $this->httpClient->request('GET', $productInfoURL, [
            'query' => [
                'apikey' => $this->settings->apiKey,
            ]
        ]);

        $data = $response->toArray();

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $data['shortProductNumber'],
            name: $data['productShortInformation']['title'],
            description: $data['productShortInformation']['shortDescription'] ?? '',
            manufacturer: $data['brand']['displayName'] ?? null,
            mpn: $data['productFullInformation']['manufacturer']['id'] ?? null,
            preview_image_url: $data['productShortInformation']['mainImage']['imageUrl'] ?? null,
            provider_url: $this->getProductUrl($data['shortProductNumber']),
            footprint: $this->getFootprintFromTechnicalAttributes($data['productFullInformation']['technicalAttributes'] ?? []),
            notes: $data['productFullInformation']['description'] ?? null,
            parameters: $this->technicalAttributesToParameters($data['productFullInformation']['technicalAttributes'] ?? []),
        );
    }

    public function getCapabilities(): array
    {
        return [ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::PRICE,];
    }
}
