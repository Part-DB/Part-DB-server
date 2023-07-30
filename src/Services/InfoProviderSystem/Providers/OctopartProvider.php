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
use App\Services\OAuth\OAuthTokenManager;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
                name
                path
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
            sellers(authorizedOnly: false) {
                company {
                    name
                    homepageUrl
                }
                isAuthorized
                offers {
                    clickUrl
                    inventoryLevel
                    moq
                    packaging
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
        private readonly OAuthTokenManager $authTokenManager)
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
            'disabled_help' => 'Set the PROVIDER_OCTOPART_CLIENT_ID and PROVIDER_OCTOPART_SECRET env option.'
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
        return true;
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
            } else if ($spec['attribute']['shortname'] === "case_package") { //Package
                $package = $spec['value'];
            } else if ($spec['attribute']['shortname'] === "numberofpins") { //Pin Count
                $pinCount = $spec['value'];
            } else if ($spec['attribute']['shortname'] === "lifecyclestatus") { //LifeCycleStatus
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

        //Generate a footprint name from the package and pin count
        $footprint = null;
        if ($package !== null) {
            $footprint = $package;
            if ($pinCount !== null) { //Add pin count if available
                $footprint .= '-' . $pinCount;
            }
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $part['id'],
            name: $part['mpn'],
            description: $part['shortDescription'],
            category: $part['category']['name'],
            manufacturer: $part['manufacturer']['name'],
            mpn: $part['mpn'],
            preview_image_url: $part['bestImage']['url'],
            manufacturing_status: $mStatus,
            provider_url: $part['octopartUrl'],
            footprint: $footprint,
            datasheets: [new FileDTO($part['bestDatasheet']['url'], $part['bestDatasheet']['name'])],
            parameters: $parameters,
            mass: $mass,
            manufacturer_product_url: $part['manufacturerUrl'],
        );
    }

    public function searchByKeyword(string $keyword): array
    {
        $graphQL = sprintf(<<<'GRAPHQL'
            query partSearch($keyword: String, $limit: Int) {
              supSearch(
                q: $keyword
                inStockOnly: false
                limit: $limit
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
            'limit' => 4,
        ]);

        $tmp = [];

        foreach ($result['data']['supSearch']['results'] as $p) {
            $tmp[] = $this->partResultToDTO($p['part']);
        }

        return $tmp;
    }



    public function getDetails(string $id): PartDetailDTO
    {
        $graphql = sprintf(<<<'GRAPHQL'
            query partSearch($ids: [String!]!) {
              supParts(ids: $ids)
              %s
            }
            GRAPHQL, self::GRAPHQL_PART_SECTION);

        $result = $this->makeGraphQLCall($graphql, [
            'ids' => [$id],
        ]);

        return $this->partResultToDTO($result['data']['supParts'][0]);
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