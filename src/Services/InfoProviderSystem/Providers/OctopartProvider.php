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
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\OAuth\OAuthTokenManager;
use App\Settings\InfoProviderSystem\OctopartSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This class implements the Octopart/Nexar API as an InfoProvider
 *
 * As the limits for Octopart are quite limited, we use an additional layer of caching here, we get the full parts during a search
 * and cache them, so we can use them for the detail view without having to query the API again.
 */
class OctopartProvider implements InfoProviderInterface
{
    private const OAUTH_APP_NAME = 'ip_octopart_oauth';

    /**
     * This defines what fields are returned in the answer from the Octopart API
     */
    private const GRAPHQL_PART_SECTION = <<<'GRAPHQL'
        {
            id
            mpn
            octopartUrl
            manufacturer {
              name
            }
            shortDescription
            category {
                ancestors {
                    name
                }
                name
            }
            bestImage {
                url
            }
            bestDatasheet {
                url
                name
            }
            manufacturerUrl
            medianPrice1000 {
              price
              currency
              quantity
            }
            sellers(authorizedOnly: $authorizedOnly) {
                company {
                    name
                }
                isAuthorized
                offers {
                    clickUrl
                    inventoryLevel
                    moq
                    sku
                    packaging
                    prices {
                        price
                        currency
                        quantity
                    }
                }
            },
            specs {
                attribute {
                    name
                    shortname
                    group
                    id
                }
                displayValue
                value
                siValue
                units
                unitsName
                unitsSymbol
                valueType
            }
        }
        GRAPHQL;


    public function __construct(private readonly HttpClientInterface $httpClient,
        private readonly OAuthTokenManager $authTokenManager, private readonly CacheItemPoolInterface $partInfoCache,
        private readonly OctopartSettings $settings,
    )
    {

    }

    /**
     * Gets the latest OAuth token for the Octopart API, or creates a new one if none is available
     * @return string
     */
    private function getToken(): string
    {
        //Check if we already have a token saved for this app, otherwise we have to retrieve one via OAuth
        if (!$this->authTokenManager->hasToken(self::OAUTH_APP_NAME)) {
            $this->authTokenManager->retrieveClientCredentialsToken(self::OAUTH_APP_NAME);
        }

        $tmp = $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME);
        if ($tmp === null) {
            throw new \RuntimeException('Could not retrieve OAuth token for Octopart');
        }

        return $tmp;
    }

    /**
     * Make a GraphQL call to the Octopart API
     * @return array
     */
    private function makeGraphQLCall(string $query, ?array $variables = null): array
    {
        if ($variables === []) {
            $variables = null;
        }

        $options = (new HttpOptions())
            ->setJson(['query' => $query, 'variables' => $variables])
            ->setAuthBearer($this->getToken())
        ;

        $response = $this->httpClient->request(
            'POST',
            'https://api.nexar.com/graphql/',
            $options->toArray(),
        );

        return $response->toArray(true);
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Octopart',
            'description' => 'This provider uses the Nexar/Octopart API to search for parts on Octopart.',
            'url' => 'https://www.octopart.com/',
            'disabled_help' => 'Set the Client ID and Secret in provider settings.',
            'settings_class' => OctopartSettings::class
        ];
    }

    public function getProviderKey(): string
    {
        return 'octopart';
    }

    public function isActive(): bool
    {
        //The client ID has to be set and a token has to be available (user clicked connect)
        //return /*!empty($this->clientId) && */ $this->authTokenManager->hasToken(self::OAUTH_APP_NAME);
        return $this->settings->clientId !== null && $this->settings->clientId !== ''
            && $this->settings->secret !== null && $this->settings->secret !== '';
    }

    private function mapLifeCycleStatus(?string $value): ?ManufacturingStatus
    {
        return match ($value) {
            'Production', 'New' => ManufacturingStatus::ACTIVE,
            'Obsolete' => ManufacturingStatus::DISCONTINUED,
            'NRND' => ManufacturingStatus::NRFND,
            'EOL' => ManufacturingStatus::EOL,
            default => null,
        };
    }

    /**
     * Saves the given part to the cache.
     * Everytime this function is called, the cache is overwritten.
     * @param  PartDetailDTO  $part
     * @return void
     */
    private function saveToCache(PartDetailDTO $part): void
    {
        $key = 'octopart_part_'.$part->provider_id;

        $item = $this->partInfoCache->getItem($key);
        $item->set($part);
        $item->expiresAfter(3600 * 24); //Cache for 1 day
        $this->partInfoCache->save($item);
    }

    /**
     * Retrieves a from the cache, or null if it was not cached yet.
     * @param  string  $id
     * @return PartDetailDTO|null
     */
    private function getFromCache(string $id): ?PartDetailDTO
    {
        $key = 'octopart_part_'.$id;

        $item = $this->partInfoCache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        return null;
    }

    private function partResultToDTO(array $part): PartDetailDTO
    {
        //Parse the specifications
        $parameters = [];
        $mass = null;
        $package = null;
        $pinCount = null;
        $mStatus = null;
        foreach ($part['specs'] as $spec) {

            //If we encounter the mass spec, we save it for later
            if ($spec['attribute']['shortname'] === "weight") {
                $mass = (float) $spec['siValue'];
            } elseif ($spec['attribute']['shortname'] === "case_package") {
                //Package
                $package = $spec['value'];
            } elseif ($spec['attribute']['shortname'] === "numberofpins") {
                //Pin Count
                $pinCount = $spec['value'];
            } elseif ($spec['attribute']['shortname'] === "lifecyclestatus") {
                //LifeCycleStatus
                $mStatus = $this->mapLifeCycleStatus($spec['value']);
            }

            $parameters[] = new ParameterDTO(
                name: $spec['attribute']['name'],
                value_text: $spec['valueType'] === 'text' ? $spec['value'] : null,
                value_typ: in_array($spec['valueType'], ['float', 'integer'], true) ? (float) $spec['value'] : null,
                unit: $spec['valueType'] === 'text' ? null : $spec['units'],
                group: $spec['attribute']['group'],
            );
        }

        //Parse the offers
        $orderinfos = [];
        foreach ($part['sellers'] as $seller) {
            foreach ($seller['offers'] as $offer) {
                $prices = [];
                foreach ($offer['prices'] as $price) {
                    $prices[] = new PriceDTO(
                        minimum_discount_amount: $price['quantity'],
                        price: (string) $price['price'],
                        currency_iso_code: $price['currency'],
                    );
                }

                $orderinfos[] = new PurchaseInfoDTO(
                    distributor_name: $seller['company']['name'],
                    order_number: $offer['sku'],
                    prices: $prices,
                    product_url: $offer['clickUrl'],
                );
            }
        }

        //Generate a footprint name from the package and pin count
        $footprint = null;
        if ($package !== null) {
            $footprint = $package;
            if ($pinCount !== null) { //Add pin count if available
                $footprint .= '-' . $pinCount;
            }
        }

        //Built the category full path
        $category = null;
        if (!empty($part['category']['name'])) {
            $category = implode(' -> ', array_map(static fn($c) => $c['name'], $part['category']['ancestors'] ?? []));
            if ($category !== '' && $category !== '0') {
                $category .= ' -> ';
            }
            $category .= $part['category']['name'];
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $part['id'],
            name: $part['mpn'],
            description: $part['shortDescription'] ?? null,
            category: $category ,
            manufacturer: $part['manufacturer']['name'] ?? null,
            mpn: $part['mpn'],
            preview_image_url: $part['bestImage']['url'] ?? null,
            manufacturing_status: $mStatus,
            provider_url: $part['octopartUrl'] ?? null,
            footprint: $footprint,
            datasheets: $part['bestDatasheet'] !== null ? [new FileDTO($part['bestDatasheet']['url'], $part['bestDatasheet']['name'])]: null,
            parameters: $parameters,
            vendor_infos: $orderinfos,
            mass: $mass,
            manufacturer_product_url: $part['manufacturerUrl'] ?? null,
        );
    }

    public function searchByKeyword(string $keyword): array
    {
        $graphQL = sprintf(<<<'GRAPHQL'
            query partSearch($keyword: String, $limit: Int, $currency: String!, $country: String!, $authorizedOnly: Boolean!) {
              supSearch(
                q: $keyword
                inStockOnly: false
                limit: $limit
                currency: $currency
                country: $country
              ) {
                hits
                results {
                  part
                  %s
                }
              }
            }
            GRAPHQL, self::GRAPHQL_PART_SECTION);


        $result = $this->makeGraphQLCall($graphQL, [
            'keyword' => $keyword,
            'limit' => $this->settings->searchLimit,
            'currency' => $this->settings->currency,
            'country' => $this->settings->country,
            'authorizedOnly' => $this->settings->onlyAuthorizedSellers,
        ]);

        $tmp = [];

        foreach ($result['data']['supSearch']['results'] ?? [] as $p) {
            $dto = $this->partResultToDTO($p['part']);
            $tmp[] = $dto;
            //Cache the part, so we can get the details later, without having to make another request
            $this->saveToCache($dto);
        }

        return $tmp;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        //Check if we have the part cached
        $cached = $this->getFromCache($id);
        if ($cached !== null) {
            return $cached;
        }

        //Otherwise we have to make a request
        $graphql = sprintf(<<<'GRAPHQL'
            query partSearch($ids: [String!]!, $currency: String!, $country: String!, $authorizedOnly: Boolean!) {
              supParts(ids: $ids, currency: $currency, country: $country)
              %s
            }
            GRAPHQL, self::GRAPHQL_PART_SECTION);

        $result = $this->makeGraphQLCall($graphql, [
            'ids' => [$id],
            'currency' => $this->settings->currency,
            'country' => $this->settings->country,
            'authorizedOnly' => $this->settings->onlyAuthorizedSellers,
        ]);

        $tmp = $this->partResultToDTO($result['data']['supParts'][0]);
        $this->saveToCache($tmp);
        return $tmp;
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::FOOTPRINT,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
        ];
    }
}
