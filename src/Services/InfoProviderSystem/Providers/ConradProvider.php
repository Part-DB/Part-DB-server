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

use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\ConradSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ConradProvider implements InfoProviderInterface
{

    private const SEARCH_ENDPOINT = 'https://api.conrad.de/search/1/v3/facetSearch';

    public function __construct(private HttpClientInterface $httpClient, private ConradSettings $settings)
    {
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Pollin',
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

    public function searchByKeyword(string $keyword): array
    {
        $url = self::SEARCH_ENDPOINT . '/' . $this->settings->country . '/' . $this->settings->language . '/' . $this->settings->customerType;

        $response = $this->httpClient->request('POST', $url, [
            'query' => [
                'apikey' => $this->settings->apiKey,
            ],
            'json' => [
                'query' => $keyword,
            ],
        ]);

        $out = [];
        $results = $response->toArray();

        foreach($results as $result) {
            $out[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $result['productId'],
                name: $result['title'],
                description: '',
                manufacturer: $result['brand']['name'] ?? null,
                mpn: $result['manufacturerId'] ??  null,
                preview_image_url: $result['image'] ?? null,
            );
        }

        return $out;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        // TODO: Implement getDetails() method.
    }

    public function getCapabilities(): array
    {
        return [ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::PRICE,];
    }
}
