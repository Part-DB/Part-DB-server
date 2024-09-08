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

/**
 * OEMSecretsProvider Class
 * 
 * This class is responsible for interfacing with the OEMSecrets API (version 3.0.1) to retrieve and manage information
 * about electronic components. Since the API does not provide a unique identifier for each part, the class aggregates
 * results based on "part_number" and "manufacturer_id". It also transforms unstructured descriptions into structured 
 * parameters and aggregates datasheets and images provided by multiple distributors.
 * The OEMSecrets API returns results by matching the provided part number not only with the original part number 
 * but also with the distributor-assigned part number and/or the part description.
 * 
 * Key functionalities:
 * - Aggregation of results based on part_number and manufacturer_id to ensure unique identification of parts.
 * - Conversion of component descriptions into structured parameters (ParameterDTO) for better clarity and searchability.
 * - Aggregation of datasheets and images from multiple distributors, ensuring that all available resources are collected.
 * - Price handling, including filtering of distributors that offer zero prices, controlled by the `zero_price` configuration variable.
 * - A sorting algorithm that first prioritizes exact matches with the keyword, followed by alphabetical sorting of items 
 *   with the same prefix (e.g., "BC546", "BC546A", "BC546B"), and finally, sorts by either manufacturer or completeness 
 *   based on the specified criteria.
 * - Sorting the distributors: 
 *   1. Environment's country_code first.
 *   2. Region matching environment's country_code, prioritizing "Global" ('XX').
 *   3. Distributors with null country_code/region are placed last.
 *   4. Final fallback is alphabetical sorting by region and country_code.
 * 
 * Configuration:
 * - The ZERO_PRICE variable must be set in the `.env.local` file. If is set to 0, the class will skip distributors 
 *   that do not offer valid prices for the components.
 * - Currency and country settings can also be specified for localized pricing and distributor filtering.
 * - Generation of parameters: if SET_PARAM is set to 1 the parameters for the part are generated from the description 
 *   transforming unstructured descriptions into structured parameters; each parameter in description should have the form:
 *   "...;name1:value1;name2:value2"
 * - Sorting is guided by SORT_CRITERIA variable. The sorting process first arranges items based on the provided keyword. 
 *   Then, if set to 'C', it further sorts by completeness (prioritizing items with the most detailed information). 
 *   If set to 'M', it further sorts by manufacturer name. If unset or set to any other value, no sorting is performed.
 *   Distributors within each item are further sorted based on country_code and region, following the rules explained 
 *   in the previous comment.
 * 
 * Data Handling:
 * - The class divides and stores component information across multiple session arrays:
 *     - `basic_info_results`: Stores basic information like name, description, manufacturer, and category.
 *     - `datasheets_results`: Aggregates datasheets provided by distributors, ensuring no duplicates.
 *     - `images_results`: Collects images of components from various sources, preventing duplication.
 *     - `parameters_results`: Extracts and stores key parameters parsed from component descriptions.
 *     - `purchase_info_results`: Contains detailed purchasing information like pricing and distributor details.
 * 
 * - By splitting the data into separate session arrays, the class optimizes memory usage and simplifies retrieval
 *   of specific details without loading the entire dataset at once.
 * 
 * Technical Details:
 * - Uses OEMSecrets API (version 3.0.1) to retrieve component data.
 * - Data processing includes sanitizing input, avoiding duplicates, and dynamically adjusting information as new distributor 
 *   data becomes available (e.g., adding missing datasheets or parameters from subsequent API responses).
 * 
 * @package App\Services\InfoProviderSystem\Providers
 * @author Pasquale D'Orsi (https://github.com/pdo59)
 * @version 1.2.0
 * @since 2024 August
 */


declare(strict_types=1);

namespace App\Services\InfoProviderSystem\Providers;

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Cache\CacheItemPoolInterface;


class OEMSecretsProvider implements InfoProviderInterface
{

    private const ENDPOINT_URL = 'https://oemsecretsapi.com/partsearch';

    public function __construct(
        private readonly HttpClientInterface $oemsecretsClient,
        private readonly string $api_key,
        private readonly string $country_code,
        private readonly string $currency,
        private readonly string $zero_price,
        private readonly string $set_param,
        private readonly string $sort_criteria,
        private readonly CacheItemPoolInterface $partInfoCache
    ) 
    {
    }
    
    private array $countryNameToCodeMap = [
        'Andorra' => 'AD',
        'United Arab Emirates' => 'AE',
        'Antarctica' => 'AQ',
        'Argentina' => 'AR',
        'Austria' => 'AT',
        'Australia' => 'AU',
        'Belgium' => 'BE',
        'Bolivia' => 'BO',
        'Brazil' => 'BR',
        'Bouvet Island' => 'BV',
        'Belarus' => 'BY',
        'Canada' => 'CA',
        'Switzerland' => 'CH',
        'Chile' => 'CL',
        'China' => 'CN',
        'Colombia' => 'CO',
        'Czech Republic' => 'CZ',
        'Germany' => 'DE',
        'Denmark' => 'DK',
        'Ecuador' => 'EC',
        'Estonia' => 'EE',
        'Western Sahara' => 'EH',
        'Spain' => 'ES',
        'Finland' => 'FI',
        'Falkland Islands' => 'FK',
        'Faroe Islands' => 'FO',
        'France' => 'FR',
        'United Kingdom' => 'GB',
        'Georgia' => 'GE',
        'French Guiana' => 'GF',
        'Guernsey' => 'GG',
        'Gibraltar' => 'GI',
        'Greenland' => 'GL',
        'Greece' => 'GR',
        'South Georgia and the South Sandwich Islands' => 'GS',
        'Guyana' => 'GY',
        'Hong Kong' => 'HK',
        'Heard Island and McDonald Islands' => 'HM',
        'Croatia' => 'HR',
        'Hungary' => 'HU',
        'Ireland' => 'IE',
        'Isle of Man' => 'IM',
        'India' => 'IN',
        'Iceland' => 'IS',
        'Italy' => 'IT',
        'Jamaica' => 'JM',
        'Japan' => 'JP',
        'North Korea' => 'KP',
        'South Korea' => 'KR',
        'Kazakhstan' => 'KZ',
        'Liechtenstein' => 'LI',
        'Sri Lanka' => 'LK',
        'Lithuania' => 'LT',
        'Luxembourg' => 'LU',
        'Monaco' => 'MC',
        'Moldova' => 'MD',
        'Montenegro' => 'ME',
        'North Macedonia' => 'MK',
        'Malta' => 'MT',
        'Netherlands' => 'NL',
        'Norway' => 'NO',
        'New Zealand' => 'NZ',
        'Peru' => 'PE',
        'Philippines' => 'PH',
        'Poland' => 'PL',
        'Portugal' => 'PT',
        'Paraguay' => 'PY',
        'Romania' => 'RO',
        'Serbia' => 'RS',
        'Russia' => 'RU',
        'Solomon Islands' => 'SB',
        'Sudan' => 'SD',
        'Sweden' => 'SE',
        'Singapore' => 'SG',
        'Slovenia' => 'SI',
        'Svalbard and Jan Mayen' => 'SJ',
        'Slovakia' => 'SK',
        'San Marino' => 'SM',
        'Somalia' => 'SO',
        'Suriname' => 'SR',
        'Syria' => 'SY',
        'Eswatini' => 'SZ',
        'Turks and Caicos Islands' => 'TC',
        'French Southern Territories' => 'TF',
        'Togo' => 'TG',
        'Thailand' => 'TH',
        'Tajikistan' => 'TJ',
        'Tokelau' => 'TK',
        'Turkmenistan' => 'TM',
        'Tunisia' => 'TN',
        'Tonga' => 'TO',
        'Turkey' => 'TR',
        'Trinidad and Tobago' => 'TT',
        'Tuvalu' => 'TV',
        'Taiwan' => 'TW',
        'Tanzania' => 'TZ',
        'Ukraine' => 'UA',
        'Uganda' => 'UG',
        'United States Minor Outlying Islands' => 'UM',
        'United States' => 'US',
        'Uruguay' => 'UY',
        'Uzbekistan' => 'UZ',
        'Vatican City' => 'VA',
        'Venezuela' => 'VE',
        'British Virgin Islands' => 'VG',
        'U.S. Virgin Islands' => 'VI',
        'Vietnam' => 'VN',
        'Vanuatu' => 'VU',
        'Wallis and Futuna' => 'WF',
        'Yemen' => 'YE',
        'South Africa' => 'ZA',
        'Zambia' => 'ZM',
        'Zimbabwe' => 'ZW',
        'Global' => 'XX'
    ];

    private array $distributorCountryCodes = [];
    private array $countryCodeToRegionMap = [];

    /**
     * Get information about this provider
     *
     * @return array An associative array with the following keys (? means optional):
     * - name: The (user friendly) name of the provider (e.g. "Digikey"), will be translated
     * - description?: A short description of the provider (e.g. "Digikey is a ..."), will be translated
     * - logo?: The logo of the provider (e.g. "digikey.png")
     * - url?: The url of the provider (e.g. "https://www.digikey.com")
     * - disabled_help?: A help text which is shown when the provider is disabled, explaining how to enable it
     * - oauth_app_name?: The name of the OAuth app which is used for authentication (e.g. "ip_digikey_oauth"). If this is set a connect button will be shown
     *
     * @phpstan-return array{ name: string, description?: string, logo?: string, url?: string, disabled_help?: string, oauth_app_name?: string }
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => 'OEMSecrets',
            'description' => 'This provider uses the OEMSecrets API to search for parts.',
            'url' => 'https://www.oemsecrets.com/',
            'disabled_help' => 'Configure the API key in the PROVIDER_OEMSECRETS_KEY environment variable to enable.'
        ];
    }
    /**
     * Returns a unique key for this provider, which will be saved into the database
     * and used to identify the provider
     * @return string A unique key for this provider (e.g. "digikey")
     */
    public function getProviderKey(): string
    {
        return 'oemsecrets';
    }

    /**
     * Checks if this provider is enabled or not (meaning that it can be used for searching)
     * @return bool True if the provider is enabled, false otherwise
     */
    public function isActive(): bool
    {
        return $this->api_key !== '';
    }
    

    /**
     * Searches for products based on a given keyword using the OEMsecrets Part Search API.
     * 
     * This method queries the OEMsecrets API to retrieve distributor data for the provided part number,
     * including details such as pricing, compliance, and inventory. It supports both direct API queries
     * and debugging with local JSON files. The results are processed, cached, and then sorted based 
     * on the keyword and specified criteria.
     * 
     * @param  string  $keyword The part number to search for
     * @return array An array of processed product details, sorted by relevance and additional criteria.
     * 
     * @throws \Exception If the JSON file used for debugging is not found or contains errors.
     */
    public function searchByKeyword(string $keyword): array
    {
        /*
        oemsecrets Part Search API  3.0.1 

        "https://oemsecretsapi.com/partsearch?
        searchTerm=BC547
        &apiKey=icawpb0bspoo2c6s64uv4vpdfp2vgr7e27bxw0yct2bzh87mpl027x353uelpq2x
        &currency=EUR
        &countryCode=IT" 
        
        partsearch description:
        Use the Part Search API to find distributor data for a full or partial manufacturer 
        part number including part details, pricing, compliance and inventory.
        
        Required Parameter  	Format	        Description
        searchTerm	            string	        Part number you are searching for
        apiKey	                string	        Your unique API key provided to you by OEMsecrets

        Additional Parameter	Format	        Description
        countryCode	            string	        The country you want to output for
        currency	            string / array	The currency you want the prices to be displayed as
        
        To display the output for GB and to view prices in USD, add [ countryCode=GB ] and [ currency=USD ]
        as seen below:
        oemsecretsapi.com/partsearch?apiKey=abcexampleapikey123&searchTerm=bd04&countryCode=GB&currency=USD
        
        To view prices in both USD and GBP add [ currency[]=USD&currency[]=GBP ]
        oemsecretsapi.com/partsearch?searchTerm=bd04&apiKey=abcexampleapikey123&currency[]=USD&currency[]=GBP
        
        */           


        // Activate this block when querying the real APIs
        //------------------
        
        $response = $this->oemsecretsClient->request('GET', self::ENDPOINT_URL, [
            'query' => [
                'searchTerm' => $keyword,
                'apiKey' => $this->api_key,
                'currency' => $this->currency,
                'countryCode' => $this->country_code,
            ],
        ]);

        $response_array = $response->toArray();
        //------------------*/

        // Or activate this block when we use json file for debugging
        /*/------------------
        $jsonFilePath = '';
        if (!file_exists($jsonFilePath)) {
            throw new \Exception("JSON file not found.");
        }
        $jsonContent = file_get_contents($jsonFilePath);
        $response_array = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON file decode failed: " . json_last_error_msg());
        }
        //------------------*/
        
        $products = $response_array['stock'] ?? [];

        $results = [];
        $basicInfoResults = [];
        $datasheetsResults = [];
        $imagesResults = [];
        $parametersResults = [];
        $purchaseInfoResults = [];

        foreach ($products as $product) {
            if (!isset($product['part_number'], $product['manufacturer'])) {
                continue; // Skip invalid product entries
            }
            $provider_id = $this->generateProviderId($product['part_number'], $product['manufacturer']);

            $partDetailDTO = $this->processBatch(
                $product, 
                $provider_id, 
                $basicInfoResults, 
                $datasheetsResults, 
                $imagesResults, 
                $parametersResults, 
                $purchaseInfoResults
            );

            if ($partDetailDTO !== null) {
                $results[$provider_id] = $partDetailDTO;
                $cacheKey = $this->getCacheKey($provider_id);
                $cacheItem = $this->partInfoCache->getItem($cacheKey);
                $cacheItem->set($partDetailDTO);
                $cacheItem->expiresAfter(3600 * 24); 
                $this->partInfoCache->save($cacheItem);
            }
        }

        // Sort of the results
        $this->sortResultsData($results, $keyword);
        return $results;

    }

    /**
     * Generates a cache key for storing part details based on the provided provider ID.
     * 
     * This method creates a unique cache key by prefixing the provider ID with 'part_details_' 
     * and hashing the provider ID using MD5 to ensure a consistent and compact key format.
     * 
     * @param string $provider_id The unique identifier of the provider or part.
     * @return string The generated cache key.
     */
    private function getCacheKey(string $provider_id): string {
        return 'oemsecrets_part_' . md5($provider_id);
    }


    /**
     * Retrieves detailed information about the part with the given provider ID from the cache.
     *
     * This method checks the cache for the details of the specified part. If the details are
     * found in the cache, they are returned. If not, an exception is thrown indicating that 
     * the details could not be found.
     *
     * @param string $id The unique identifier of the provider or part.
     * @return PartDetailDTO The detailed information about the part.
     *
     * @throws \Exception If no details are found for the given provider ID.
     */
    public function getDetails(string $id): PartDetailDTO
    {
        $cacheKey = $this->getCacheKey($id);
        $cacheItem = $this->partInfoCache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $details = $cacheItem->get();
        } else {
            // If the details are not found in the cache, throw an exception
            throw new \RuntimeException("Details not found for provider_id $id");
        }

        return $details;
    }

    
    /**
     * A list of capabilities this provider supports (which kind of data it can provide).
     * Not every part have to contain all of these data, but the provider should be able to provide them in general.
     * Currently, this list is purely informational and not used in functional checks.
     * @return ProviderCapabilities[]
     */
    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
        ];
    }


    /**
     * Processes a single product and updates arrays for basic information, datasheets, images, parameters,
     * and purchase information. Aggregates and organizes data received for a specific `part_number` and `manufacturer_id`.
     * Distributors within the product are also sorted based on country_code and region.
     *
     * @param array $product The product data received from the OEMSecrets API.
     * @param string $provider_id A string that contains the unique key created for the part
     * @param array &$basicInfoResults Array containing the basic product information (e.g., name, description, category).
     * @param array &$datasheetsResults Array containing datasheets collected from various distributors for the product.
     * @param array &$imagesResults Array containing images of the product collected from various distributors.
     * @param array &$parametersResults Array containing technical parameters extracted from the product descriptions.
     * @param array &$purchaseInfoResults Array containing purchase information, including distributors and pricing details.
     *
     * @return PartDetailDTO|null Returns a PartDetailDTO object if the product is processed successfully, otherwise null.
     * 
     * @throws \Exception If a required key in the product data is missing or if there is an issue creating the DTO.
     * 
     * @see createOrUpdateBasicInfo() Creates or updates the basic product information.
     * @see getPrices() Extracts the pricing information for the product.
     * @see parseDataSheets() Parses and prevents duplication of datasheets.
     * @see getImages() Extracts and avoids duplication of images.
     * @see getParameters() Extracts technical parameters from the product description.
     * @see createPurchaseInfoDTO() Creates a PurchaseInfoDTO containing distributor and price information.
     *
     * @note Distributors within the product are sorted by country_code and region:
     *       1. Distributors with the environment's country_code come first.
     *       2. Distributors in the same region as the environment's country_code are next, 
     *          with "Global" ('XX') prioritized within this region.
     *       3. Distributors with null country_code or region are placed last.
     *       4. Remaining distributors are sorted alphabetically by region and country_code.
 
     */
     private function processBatch( 
        array $product, 
        string $provider_id,
        array &$basicInfoResults, 
        array &$datasheetsResults, 
        array &$imagesResults, 
        array &$parametersResults, 
        array &$purchaseInfoResults 
     ): ?PartDetailDTO
    {   
        if (!isset($product['manufacturer'], $product['part_number'])) {
            throw new \InvalidArgumentException("Missing required product data: 'manufacturer' or 'part_number'");
        }

        // Retrieve the country_code associated with the distributor and store it in the $distributorCountryCodes array.
        $distributorCountry = $product['distributor']['distributor_country'] ?? null;
        $distributorName = $product['distributor']['distributor_name'] ?? null;
        $distributorRegion = $product['distributor']['distributor_region'] ?? null;

        if ($distributorCountry && $distributorName) {
            $countryCode = $this->mapCountryNameToCode($distributorCountry);
            if ($countryCode) {
                $this->distributorCountryCodes[$distributorName] = $countryCode;
            }
            if ($distributorRegion) {
                $this->countryCodeToRegionMap[$countryCode] = $distributorRegion;
            }
        }

        // Truncate the description and handle notes
        $thenotes = '';
        $description = $product['description'] ?? '';
        if (strlen($description) > 100) {
            $thenotes = $description; // Save the complete description
            $description = substr($description, 0, 100) . '...'; // Truncate the description
        }
    
        // Extract prices
        $priceDTOs = $this->getPrices($product);
        if (empty($priceDTOs) && (int)$this->zero_price === 0) {
            return null; // Skip products without valid prices
        }

        $existingBasicInfo = isset($basicInfoResults[$provider_id]) && is_array($basicInfoResults[$provider_id]) 
        ? $basicInfoResults[$provider_id] 
        : [];

        $basicInfoResults[$provider_id] = $this->createOrUpdateBasicInfo(
            $provider_id, 
            $product, 
            $description, 
            $thenotes, 
            $existingBasicInfo
        );
    
        // Update images, datasheets, and parameters
        
        $newDatasheets = $this->parseDataSheets($product['datasheet_url'] ?? null, null, $datasheetsResults[$provider_id] ?? []);
        if ($newDatasheets !== null) {
            $datasheetsResults[$provider_id] = array_merge($datasheetsResults[$provider_id] ?? [], $newDatasheets);
        }
        
        $imagesResults[$provider_id] = $this->getImages($product, $imagesResults[$provider_id] ?? []);
        if ($this->set_param == 1) {
            $parametersResults[$provider_id] = $this->getParameters($product, $parametersResults[$provider_id] ?? []);
        } else {
            $parametersResults[$provider_id] = [];
        }

        // Handle purchase information
        $currentDistributor = $this->createPurchaseInfoDTO($product, $priceDTOs, $purchaseInfoResults[$provider_id] ?? []);
        if ($currentDistributor !== null) {
            $purchaseInfoResults[$provider_id][] = $currentDistributor;
        }

        // If there is data in $purchaseInfoResults, sort it before creating the PartDetailDTO
        if (!empty($purchaseInfoResults[$provider_id])) {
            usort($purchaseInfoResults[$provider_id], function ($a, $b) {
                $nameA = $a->distributor_name;
                $nameB = $b->distributor_name;

                $countryCodeA = $this->distributorCountryCodes[$nameA] ?? null;
                $countryCodeB = $this->distributorCountryCodes[$nameB] ?? null;
                
                $regionA = $this->countryCodeToRegionMap[$countryCodeA] ?? '';
                $regionB = $this->countryCodeToRegionMap[$countryCodeB] ?? '';

                // If the map is empty or doesn't contain the key for $this->country_code, assign a placeholder region.
                $regionForEnvCountry = $this->countryCodeToRegionMap[$this->country_code] ?? '';
                
                // Convert to string before comparison to avoid mixed types
                $countryCodeA = (string) $countryCodeA;
                $countryCodeB = (string) $countryCodeB;
                $regionA = (string) $regionA;
                $regionB = (string) $regionB;


                // Step 0: If either country code is null, place it at the end
                if ($countryCodeA === '' || $regionA === '') {
                    return 1; // Metti A dopo B
                } elseif ($countryCodeB === '' || $regionB === '') {
                    return -1; // Metti B dopo A
                }

                // Step 1: country_code from the environment
                if ($countryCodeA === $this->country_code && $countryCodeB !== $this->country_code) {
                    return -1; 
                } elseif ($countryCodeA !== $this->country_code && $countryCodeB === $this->country_code) {
                    return 1;  
                }

                // Step 2: Sort by environment's region, prioritizing "Global" (XX)
                if ($regionA === $regionForEnvCountry && $regionB !== $regionForEnvCountry) {
                    return -1;
                } elseif ($regionA !== $regionForEnvCountry && $regionB === $regionForEnvCountry) {
                    return 1;
                }

                // Step 3: If regions are the same, prioritize "Global" (XX)
                if ($regionA === $regionB) {
                    if ($countryCodeA === 'XX' && $countryCodeB !== 'XX') {
                        return -1;
                    } elseif ($countryCodeA !== 'XX' && $countryCodeB === 'XX') {
                        return 1;
                    }
                }
                
                // Step 4: Alphabetical sorting by region and country_code
                $regionComparison = strcasecmp($regionA , $regionB);
                if ($regionComparison !== 0) {
                    return $regionComparison;
                }

                // Alphabetical sorting as a fallback
                return strcasecmp($countryCodeA, $countryCodeB);
            });
        }
        // Convert the gathered data into a PartDetailDTO
        
        $partDetailDTO = new PartDetailDTO(
            provider_key: $basicInfoResults[$provider_id]['provider_key'],
            provider_id: $provider_id,
            name: $basicInfoResults[$provider_id]['name'],
            description: $basicInfoResults[$provider_id]['description'],
            category: $basicInfoResults[$provider_id]['category'],
            manufacturer: $basicInfoResults[$provider_id]['manufacturer'],
            mpn: $basicInfoResults[$provider_id]['mpn'],
            preview_image_url: $basicInfoResults[$provider_id]['preview_image_url'],
            manufacturing_status: $basicInfoResults[$provider_id]['manufacturing_status'],
            provider_url: $basicInfoResults[$provider_id]['provider_url'],
            footprint: $basicInfoResults[$provider_id]['footprint'] ?? null,
            notes: $basicInfoResults[$provider_id]['notes'] ?? null,
            datasheets: $datasheetsResults[$provider_id] ?? [],
            images: $imagesResults[$provider_id] ?? [],
            parameters: $parametersResults[$provider_id] ?? [],
            vendor_infos: $purchaseInfoResults[$provider_id] ?? []
        );

        // Force garbage collection to deallocate unused memory cycles
        // Without this instruction, when in dev mode, after the first or second call to getDetails, 
        // a memory error occurs due to memory not being freed properly, leading to memory exhaustion.
        gc_collect_cycles();
        
        return $partDetailDTO;
    }


   /**
     * Extracts pricing information from the product data, converts it to PriceDTO objects,
     * and returns them as an array.
     *
     * @param array{
     *     prices?: array<string, array<array{
     *         unit_break: mixed,
     *         unit_price: mixed
     *     }>>,
     *     source_currency?: string
     * } $product The product data from the OEMSecrets API containing price details.
     *
     * @return PriceDTO[] Array of PriceDTO objects representing different price tiers for the product.
     */
    private function getPrices(array $product): array
    {
        $prices = $product['prices'] ?? [];
        $sourceCurrency = $product['source_currency'] ?? null;
        $priceDTOs = [];

        // Flag to check if we have added prices in the preferred currency
        $foundPreferredCurrency = false;

        if (is_array($prices)) {
            // Step 1: Check if prices exist in the preferred currency
            if (isset($prices[$this->currency]) && is_array($prices[$this->currency])) {
                $priceDetails = $prices[$this->currency];
                foreach ($priceDetails as $priceDetail) {
                    if (
                        is_array($priceDetail) &&
                        isset($priceDetail['unit_break'], $priceDetail['unit_price']) &&
                        is_numeric($priceDetail['unit_break']) &&
                        is_string($priceDetail['unit_price']) &&
                        $priceDetail['unit_price'] !== "0.0000"
                    ) {
                        $priceDTOs[] = new PriceDTO(
                            minimum_discount_amount: (float)$priceDetail['unit_break'],
                            price: (string)$priceDetail['unit_price'],
                            currency_iso_code: $this->currency,
                            includes_tax: false,
                            price_related_quantity: 1.0
                        );
                        $foundPreferredCurrency = true;
                    }
                }
            }

            // Step 2: If no prices in the preferred currency, use source currency
            if (!$foundPreferredCurrency && $sourceCurrency && isset($prices[$sourceCurrency]) && is_array($prices[$sourceCurrency])) {
                $priceDetails = $prices[$sourceCurrency];
                foreach ($priceDetails as $priceDetail) {
                    if (
                        is_array($priceDetail) &&
                        isset($priceDetail['unit_break'], $priceDetail['unit_price']) &&
                        is_numeric($priceDetail['unit_break']) &&
                        is_string($priceDetail['unit_price']) &&
                        $priceDetail['unit_price'] !== "0.0000"
                    ) {
                        $priceDTOs[] = new PriceDTO(
                            minimum_discount_amount: (float)$priceDetail['unit_break'],
                            price: (string)$priceDetail['unit_price'],
                            currency_iso_code: $sourceCurrency,
                            includes_tax: false,
                            price_related_quantity: 1.0
                        );
                    }
                }
            }
        }

        return $priceDTOs;
    }


    /**
     * Retrieves product images provided by the distributor. Prevents duplicates based on the image name.
     * @param array{
     *     image_url?: string
     * } $product The product data from the OEMSecrets API containing image URLs.
     * @param FileDTO[] $existingImages Optional. Existing images for the product to avoid duplicates.
     *
     * @return FileDTO[] Array of FileDTO objects representing the product images.
     */
    private function getImages(array $product, array $existingImages = []): array
    {
        $images = $existingImages;
        $imageUrl = $product['image_url'] ?? null;
    
        if ($imageUrl) {
            $imageName = basename(parse_url($imageUrl, PHP_URL_PATH));
            if (!in_array($imageName, array_column($images, 'name'), true)) {
                $images[] = new FileDTO(url: $imageUrl, name: $imageName);
            }
        }
        return $images;
    }
    
    /**
     * Extracts technical parameters from the product description, ensures no duplicates, and returns them as an array.
     *
     * @param array{
     *     description?: string
     * } $product The product data from the OEMSecrets API containing product descriptions.
     * @param ParameterDTO[] $existingParameters Optional. Existing parameters for the product to avoid duplicates.
     *
     * @return ParameterDTO[] Array of ParameterDTO objects representing technical parameters extracted from the product description.
     */
    private function getParameters(array $product, array $existingParameters = []): array
    {
        $parameters = $existingParameters;
        $description = $product['description'] ?? '';

        // Logic to extract parameters from the description
        $extractedParameters = $this->parseDescriptionToParameters($description) ?? [];

        // Ensure that $extractedParameters is an array
        if (!is_array($extractedParameters)) {
            $extractedParameters = [];
        }
        
        foreach ($extractedParameters as $newParam) {
            $isDuplicate = false;
            foreach ($parameters as $existingParam) {
                if ($existingParam->name === $newParam->name) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $parameters[] = $newParam;
            }
        }

        return $parameters;
    }

   /**
     * Creates a PurchaseInfoDTO object containing distributor and pricing information for a product.
     * Ensures that the distributor name is valid and prices are available.
     *
     * @param array{
     *     distributor?: array{
     *         distributor_name?: string
     *     },
     *     sku?: string,
     *     source_part_number: string,
     *     buy_now_url?: string,
     *     lead_time_weeks?: mixed
     * } $product The product data from the OEMSecrets API.
     * @param PriceDTO[] $priceDTOs Array of PriceDTO objects representing pricing tiers.
     * @param PurchaseInfoDTO[] $existingPurchaseInfos Optional. Existing purchase information for the product to avoid duplicates.
     *
     * @return PurchaseInfoDTO|null A PurchaseInfoDTO object containing the distributor information, or null if invalid.
     */
    private function createPurchaseInfoDTO(array $product, array $priceDTOs, array $existingPurchaseInfos = []): ?PurchaseInfoDTO
    {
        $distributor_name = $product['distributor']['distributor_name'] ?? null;
        if ($distributor_name && !empty($priceDTOs)) {
            $sku = isset($product['sku']) ? (string)$product['sku'] : null;
            $order_number_base = $sku ?: (string)$product['source_part_number'];
            $order_number = $order_number_base;

            // Remove duplicates from the quantity/price tiers
            $uniquePriceDTOs = [];
            foreach ($priceDTOs as $priceDTO) {
                $key = $priceDTO->minimum_discount_amount . '-' . $priceDTO->price;
                $uniquePriceDTOs[$key] = $priceDTO;
            }
            $priceDTOs = array_values($uniquePriceDTOs);

            // Differentiate $order_number if duplicated
            if ($this->isDuplicateOrderNumber($order_number, $distributor_name, $existingPurchaseInfos)) {
                $lead_time_weeks = isset($product['lead_time_weeks']) ? (string)$product['lead_time_weeks'] : '';
                $order_number = $order_number_base . '-' . $lead_time_weeks;

                // If there is still a duplicate after adding lead_time_weeks
                $counter = 1;
                while ($this->isDuplicateOrderNumber($order_number, $distributor_name, $existingPurchaseInfos)) {
                    $order_number = $order_number_base . '-' . $lead_time_weeks . '-' . $counter;
                    $counter++;
                }
            }

            return new PurchaseInfoDTO(
                distributor_name: $distributor_name,
                order_number: $order_number,
                prices: $priceDTOs,
                product_url: $product['buy_now_url'] ?? ''
            );
        }
        return null; // Return null if no valid distributor exists
    }

    /**
     * Checks if an order number already exists for a given distributor in the existing purchase infos.
     *
     * @param string $order_number The order number to check.
     * @param string $distributor_name The name of the distributor.
     * @param PurchaseInfoDTO[] $existingPurchaseInfos The existing purchase information to check against.
     * @return bool True if a duplicate order number is found, otherwise false.
     */
    private function isDuplicateOrderNumber(string $order_number, string $distributor_name, array $existingPurchaseInfos): bool
    {
        foreach ($existingPurchaseInfos as $purchaseInfo) {
            if ($purchaseInfo->distributor_name === $distributor_name && $purchaseInfo->order_number === $order_number) {
                return true;
            }
        }
        return false;
    }

    /**
     * Creates or updates the basic information of a product, including the description, category, manufacturer,
     * and other metadata. This function manages the PartDetailDTO creation or update.
     *
     * @param string $provider_id The unique identifier for the product based on part_number and manufacturer.
     *  * @param array{
     *     part_number: string,
     *     category: string,
     *     manufacturer: string,
     *     source_part_number: string,
     *     image_url?: string,
     *     life_cycle?: string,
     *     quantity_in_stock?: int
     * } $product The product data from the OEMSecrets API.
     * @param string $description The truncated description for the product.
     * @param string $thenotes The full description saved as notes for the product.
     * 
     * @return array The updated or newly created PartDetailDTO containing basic product information.
     */
    private function createOrUpdateBasicInfo(
            string $provider_id,
            array $product,
            string $description,
            string $thenotes,
            ?array $existingBasicInfo
        ): array {
        // If there is no existing basic info array, we create a new one
        if (is_null($existingBasicInfo)) {
            return [
                'provider_key' => $this->getProviderKey(),
                'provider_id' => $provider_id,
                'name' => $product['part_number'],
                'description' => $description,
                'category' => $product['category'],
                'manufacturer' => $product['manufacturer'],
                'mpn' => $product['source_part_number'],
                'preview_image_url' => $product['image_url'] ?? null,
                'manufacturing_status' => $this->releaseStatusCodeToManufacturingStatus(
                    $product['life_cycle'] ?? null,
                    (int)($product['quantity_in_stock'] ?? 0)
                ),
                'provider_url' => $this->generateInquiryUrl($product['part_number']),
                'notes' => $thenotes, 
                'footprint' => null
            ];
        }

        // Update fields only if empty or undefined, with additional check for preview_image_url
        return [
            'provider_key' => $existingBasicInfo['provider_key'] ?? $this->getProviderKey(),
            'provider_id' => $existingBasicInfo['provider_id'] ?? $provider_id,
            'name' => $existingBasicInfo['name'] ?? $product['part_number'],
             // Update description if it's null/empty
            'description' => !empty($existingBasicInfo['description']) 
                ? $existingBasicInfo['description'] 
                : $description,
            // Update category if it's null/empty
            'category' => !empty($existingBasicInfo['category']) 
                ? $existingBasicInfo['category'] 
                : $product['category'],
            'manufacturer' => $existingBasicInfo['manufacturer'] ?? $product['manufacturer'],
            'mpn' => $existingBasicInfo['mpn'] ?? $product['source_part_number'],
            'preview_image_url' => !empty($existingBasicInfo['preview_image_url']) 
                ? $existingBasicInfo['preview_image_url'] 
                : ($product['image_url'] ?? null),
            'manufacturing_status' => !empty($existingBasicInfo['manufacturing_status']) 
                ? $existingBasicInfo['manufacturing_status'] 
                : $this->releaseStatusCodeToManufacturingStatus(
                    $product['life_cycle'] ?? null,
                    (int)($product['quantity_in_stock'] ?? 0)
                ),
            'provider_url' => $existingBasicInfo['provider_url'] ?? $this->generateInquiryUrl($product['part_number']), // ?? $product['buy_now_url'],
            'notes' => $existingBasicInfo['notes'] ?? $thenotes, 
            'footprint' => null
        ];
    }

    /**
     * Parses the datasheet URL and returns an array of FileDTO objects representing the datasheets.
     * If the datasheet name is not provided, it attempts to extract the file name from the URL.
     * If multiple datasheets with the same default name are encountered, the function appends a
     * numeric suffix to ensure uniqueness.
     * The query parameter used to extract the event link can be customized.
     * 
     * URL Requirements:
     * - The URL should be a valid URL string.
     * - The URL can include a query parameter named "event_link", which contains a sub-URL where the
     *   actual datasheet file name is located (e.g., a link to a PDF file).
     * - If "event_link" is not present, the function attempts to extract the file name directly from 
     *   the URL path.
     * - The URL path should ideally end with a valid file extension (e.g., .pdf, .doc, .xls, etc.).
     * 
     * Example 1:
     * Given URL: `https://example.com/datasheet.php?event_link=https%3A%2F%2Ffiles.example.com%2Fdatasheet.pdf`
     * Extracted name: `datasheet.pdf`
     * 
     * Example 2:
     * Given URL: `https://example.com/files/datasheet.pdf`
     * Extracted name: `datasheet.pdf`
     * 
     * Example 3 (default name fallback):
     * Given URL: `https://example.com/files/noextensionfile`
     * Extracted name: `datasheet.pdf`
     *
     * @param string|null $sheetUrl The URL of the datasheet.
     * @param string|null $sheetName The optional name of the datasheet. If null, the name is extracted from the URL.
     * @param array $existingDatasheets The array of existing datasheets to check for duplicates.
     * @param string $eventLinkParam The query parameter used to extract the event link. Default is 'event_link'.
     *
     * @return FileDTO[]|null Returns an array containing the new datasheet if unique, or null if the datasheet is a duplicate or invalid.
     *
     * @see FileDTO Used to create datasheet objects with a URL and name.
     */
    private function parseDataSheets(?string $sheetUrl, ?string $sheetName, array $existingDatasheets = [], string $eventLinkParam = 'event_link'): ?array
    {
        if ($sheetUrl === null || $sheetUrl === '' || $sheetUrl === '0') {
            return null;
        }

        // If the datasheet name is not provided, extract it from the URL
        if ($sheetName === null) {
            // Extract parameters from the query string of the URL
            $queryParams = [];
            $urlComponents = parse_url($sheetUrl);
            if (isset($urlComponents['query'])) {
                parse_str($urlComponents['query'], $queryParams);
            }
            // If the "event_link" parameter exists, use it to extract the PDF file name
            if (isset($queryParams[$eventLinkParam])) {
                $eventLink = $queryParams[$eventLinkParam];
                $sheetName = basename(parse_url($eventLink, PHP_URL_PATH));
            } else {
                // If "event_link" does not exist, try to extract the name from the main URL path
                $sheetName = basename($urlComponents['path']);
                if (!str_contains($sheetName, '.') || !preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx)$/i', $sheetName)) {
                    // If the name does not have a valid extension, assign a default name
                    $sheetName = 'datasheet_' . uniqid('', true) . '.pdf';
                }
            }
        }

        // Create an array of existing file names
        $existingNames = array_map(static function ($existingDatasheet) {
            return $existingDatasheet->name;
        }, $existingDatasheets);

        // Check if the name already exists
        if (in_array($sheetName, $existingNames, true)) {
            // The name already exists, so do not add the datasheet
            return null;
        }

        // Create an array with the datasheet data if it does not already exist
        $result = [];
        $result[] = new FileDTO(url: $sheetUrl, name: $sheetName);
        return $result;
    }

    /**
     * Converts the lifecycle status from the API to a ManufacturingStatus
     *  - "Factory Special Order" / "Ordine speciale in fabbrica"
     *  - "Not Recommended for New Designs" / "Non raccomandato per nuovi progetti"
     *  - "New Product" / "Nuovo prodotto" (if availableInStock > 0 else ANNOUNCED)
     *  - "End of Life" / "Fine vita"
     *  - vuoto / "Attivo" 
     *  
     * @param  string|null  $productStatus The lifecycle status from the Mouser API. Expected values are:
     *     - "Factory Special Order"
     *     - "Not Recommended for New Designs"
     *     - "New Product"
     *     - "End of Life"
     *     - "Obsolete"
     * @param  int  $availableInStock The number of parts available in stock.
     * @return ManufacturingStatus|null Returns the corresponding ManufacturingStatus or null if the status is unknown.
     * 
     * @todo Probably need to review the values of field Lifecyclestatus.
     */
    private function releaseStatusCodeToManufacturingStatus(?string $productStatus, int $availableInStock = 0): ?ManufacturingStatus
    {
        $tmp = match ($productStatus) {
            null => null,
            "New Product" => ManufacturingStatus::ANNOUNCED,
            "Not Recommended for New Designs" => ManufacturingStatus::NRFND,
            "Factory Special Order", "Obsolete" => ManufacturingStatus::DISCONTINUED,
            "End of Life" => ManufacturingStatus::EOL,
            default => null, //ManufacturingStatus::ACTIVE,
        };

        //If the part would be assumed to be announced, check if it is in stock, then it is active
        if ($tmp === ManufacturingStatus::ANNOUNCED && $availableInStock > 0) {
            $tmp = ManufacturingStatus::ACTIVE;
        }

        return $tmp;
    }

    /**
     * Parses the given product description to extract parameters and convert them into `ParameterDTO` objects.
     * If the description contains only a single `:`, it is considered unstructured and ignored.
     * The function processes the description by searching for key-value pairs in the format `name: value`,
     * ignoring any parts of the description that do not follow this format. Parameters are split using either
     * `;` or `,` as separators. 
     * 
     * The extraction logic handles typical values, ranges, units, and textual information from the description.
     * If the description is empty or cannot be processed into valid parameters, the function returns null.
     *
     * @param string|null $description The description text from which parameters are to be extracted.
     * 
     * @return ParameterDTO[]|null Returns an array of `ParameterDTO` objects if parameters are successfully extracted,
     *                             or null if no valid parameters can be extracted from the description.
     */
    private function parseDescriptionToParameters(?string $description): ?array
    {
        // If the description is null or empty, return null
        if ($description === null || trim($description) === '') {
            return null;
        }

        // If the description contains only a single ':', return null
        if (substr_count($description, ':') === 1) {
            return null;
        }

         // Array to store parsed parameters
        $parameters = [];

        // Split the description using the ';' separator
        $parts = preg_split('/[;,]/', $description); //explode(';', $description);

        // Process each part of the description
        foreach ($parts as $part) {
            $part = trim($part);

            // Check if the part contains a key-value structure
            if (str_contains($part, ':')) {
                [$name, $value] = explode(':', $part, 2);
                $name = trim($name);
                $value = trim($value);

                // Attempt to parse the value, handling ranges, units, and additional information
                $parsedValue = $this->customParseValueIncludingUnit($name, $value);

                // If the value was successfully parsed, create a ParameterDTO
                if ($parsedValue) {
                    // Convert numeric values to float
                    $value_typ = isset($parsedValue['value_typ']) ? (float)$parsedValue['value_typ'] : null;
                    $value_min = isset($parsedValue['value_min']) ? (float)$parsedValue['value_min'] : null;
                    $value_max = isset($parsedValue['value_max']) ? (float)$parsedValue['value_max'] : null;

                    $parameters[] = new ParameterDTO(
                        name: $parsedValue['name'],
                        value_text: $parsedValue['value_text'] ?? null,
                        value_typ: $value_typ,
                        value_min: $value_min,
                        value_max: $value_max,
                        unit: $parsedValue['unit'] ?? null,     // Add extracted unit
                        symbol: $parsedValue['symbol'] ?? null  // Add extracted symbol
                    );
                }
            }
        }

        return !empty($parameters) ? $parameters : null;
    }

    /**
     * Parses a value that may contain both a numerical value and its corresponding unit.
     * This function splits the value into its numerical part and its unit, handling cases
     * where the value includes symbols, ranges, or additional text. It also detects and
     * processes plus/minus ranges, typical values, and other special formats.
     *
     * Example formats that can be handled:
     * - "2.5V"
     * - "±5%"
     * - "1-10A"
     * - "2.5 @text"
     * - "~100 Ohm"
     *
     * @param string $value The value string to be parsed, which may contain a number, unit, or both.
     * 
     * @return array An associative array with parsed components:  
     *                    - 'name' => string (the name of the parameter)    
     *                    - 'value_typ' => float|null (the typical or parsed value)
     *                    - 'range_min' => float|null (the minimum value if it's a range)
     *                    - 'range_max' => float|null (the maximum value if it's a range)
     *                    - 'value_text' => string|null (any additional text or symbol)
     *                    - 'unit' => string|null (the detected or default unit)
     *                    - 'symbol' => string|null (any special symbol or additional text)             
     */
    private function customParseValueIncludingUnit(string $name, string $value): array
    {
        // Parse using logic for units, ranges, and other elements
        $result = [
            'name' => $name,
            'value_typ' => null,
            'value_min' => null,
            'value_max' => null,
            'value_text' => null,
            'unit' => null,
            'symbol' => null,
        ];

        // Trim any whitespace from the value
        $value = trim($value);

        // Handle ranges and plus/minus signs
        if (str_contains($value, '...') || str_contains($value, '~') || str_contains($value, '±')) {
            // Handle ranges
            $value = str_replace(['...', '~'], '...', $value); // Normalize range separators
            $rangeParts = preg_split('/\s*[\.\~]\s*/', $value);

            if (count($rangeParts) === 2) {
                // Splitting the values and units
                $parsedMin = $this->customSplitIntoValueAndUnit($rangeParts[0]);
                $parsedMax = $this->customSplitIntoValueAndUnit($rangeParts[1]);
            
                // Assigning the parsed values
                $result['value_min'] = $parsedMin['value_typ'];
                $result['value_max'] = $parsedMax['value_typ'];
            
                // Determine the unit
                $result['unit'] = $parsedMax['unit'] ?? $parsedMin['unit'];
            }
            
        } elseif (str_contains($value, '@')) {
            // If we find "@", we treat it as additional textual information
            [$numericValue, $textValue] = explode('@', $value);
            $result['value_typ'] = (float) $numericValue;
            $result['value_text'] = trim($textValue);
        } else {
            // Check if the value is numeric with a unit
            if (preg_match('/^([\+\-]?\d+(\.\d+)?)([a-zA-Z%°]+)?$/u', $value, $matches)) {
                // It is a number with or without a unit
                $result['value_typ'] = isset($matches[1]) ? (float)$matches[1] : null;
                $result['unit'] = $matches[3] ?? null;
            } else {
                // It's not a number, so we treat it as text
                $result['value_text'] = $value;
            }
        }

        return $result;
    }

    /**
     * Splits a string into a numerical value and its associated unit. The function attempts to separate
     * a number from its unit, handling common formats where the unit follows the number (e.g., "50kHz", "10A").
     * The function assumes the unit is the non-numeric part of the string.
     *
     * Example formats that can be handled:
     * - "100 Ohm"
     * - "10 MHz"
     * - "5kV"
     * - "±5%"
     *
     * @param string $value1 The input string containing both a numerical value and a unit.
     * @param string|null $value2 Optional. A second value string, typically used for ranges (e.g., "10-20A").
     *
     * @return array An associative array with the following elements:
     *               - 'value_typ' => string|null The first numerical part of the string.
     *               - 'unit' => string|null The unit part of the string, or null if no unit is detected.
     *               - 'value_min' => string|null The minimum value in a range, if applicable.
     *               - 'value_max' => string|null The maximum value in a range, if applicable.
     */
    private function customSplitIntoValueAndUnit(string $value1, string $value2 = null): array
    {
        // Separate numbers and units (basic parsing handling)
        $unit = null;
        $value_typ = null;

        // Search for the number + unit pattern
        if (preg_match('/^([\+\-]?\d+(\.\d+)?)([a-zA-Z%°]+)?$/u', $value1, $matches)) {
            $value_typ = $matches[1];
            $unit = $matches[3] ?? null;
        }

        $result = [
            'value_typ' => $value_typ,
            'unit' => $unit,
        ];

        if ($value2 !== null) {
            if (preg_match('/^([\+\-]?\d+(\.\d+)?)([a-zA-Z%°]+)?$/u', $value2, $matches2)) {
                $result['value_min'] = $value_typ;
                $result['value_max'] = $matches2[1];
                $result['unit'] = $matches2[3] ?? $unit; // If both values have the same unit, we keep it
            }
        }

        return $result;
    }

    /**
     * Generates the API URL to fetch product information for the specified part number from OEMSecrets.
     * Ensures that the base API URL and any query parameters are properly formatted.
     *
     * @param string $partNumber The part number to include in the URL.
     * @param string $oemInquiry The inquiry path for the OEMSecrets API, with a default value of 'compare/'.
     *                           This parameter represents the specific API endpoint to query.
     * 
     * @return string The complete provider URL including the base provider URL, the inquiry path, and the part number.
     * 
     * Example:
     * If the base URL is "https://www.oemsecrets.com/", the inquiry path is "compare/", and the part number is "NE555",
     * the resulting URL will be: "https://www.oemsecrets.com/compare/NE555"
     */
    private function generateInquiryUrl(string $partNumber, string $oemInquiry = 'compare/'): string
    {
        $baseUrl = rtrim($this->getProviderInfo()['url'], '/') . '/';
        $inquiryPath = trim($oemInquiry, '/') . '/';
        $encodedPartNumber = urlencode(trim($partNumber));
        return $baseUrl . $inquiryPath . $encodedPartNumber;
    }

    /**
     * Sorts the results data array based on the specified search keyword and sorting criteria.
     * The sorting process involves multiple phases:
     * 1. Exact match with the search keyword.
     * 2. Prefix match with the search keyword.
     * 3. Alphabetical order of the suffix following the keyword.
     * 4. Optional sorting by completeness or manufacturer based on the sort criteria.
     *
     * The sorting criteria (`sort_criteria`) is an environment variable configured in the `.env.local` file:
     * PROVIDER_OEMSECRETS_SORT_CRITERIA 
     * It determines the final sorting phase:
     * - 'C': Sort by completeness.
     * - 'M': Sort by manufacturer.
     *
     * @param array $resultsData The array of result objects to be sorted. Each object should have 'name' and 'manufacturer' properties.
     * @param string $searchKeyword The search keyword used for sorting the results.
     *
     * @return void
     */
    private function sortResultsData(array &$resultsData, string $searchKeyword): void
    {
        // If the SORT_CRITERIA is not 'C' or 'M', do not sort
        if ($this->sort_criteria !== 'C' && $this->sort_criteria !== 'M') {
            return;
        }
        usort($resultsData, function ($a, $b) use ($searchKeyword) {
            $nameA = trim($a->name);
            $nameB = trim($b->name);

            // First phase: Sorting by exact match with the keyword
            $exactMatchA = strcasecmp($nameA, $searchKeyword) === 0;
            $exactMatchB = strcasecmp($nameB, $searchKeyword) === 0;

            if ($exactMatchA && !$exactMatchB) {
                return -1;
            } elseif (!$exactMatchA && $exactMatchB) {
                return 1;
            }

            // Second phase: Sorting by prefix (name starting with the keyword)
            $startsWithKeywordA = stripos($nameA, $searchKeyword) === 0;
            $startsWithKeywordB = stripos($nameB, $searchKeyword) === 0;

            if ($startsWithKeywordA && !$startsWithKeywordB) {
                return -1;
            } elseif (!$startsWithKeywordA && $startsWithKeywordB) {
                return 1;
            }

            if ($startsWithKeywordA && $startsWithKeywordB) {
                // Alphabetical sorting of suffixes
                $suffixA = substr($nameA, strlen($searchKeyword));
                $suffixB = substr($nameB, strlen($searchKeyword));
                $suffixComparison = strcasecmp($suffixA, $suffixB);
    
                if ($suffixComparison !== 0) {
                    return $suffixComparison;
                }
            }

            // Final sorting: by completeness or manufacturer, if necessary
            if ($this->sort_criteria === 'C') {
                return $this->compareByCompleteness($a, $b);
            } elseif ($this->sort_criteria === 'M') {
                return strcasecmp($a->manufacturer, $b->manufacturer);
            }

        });
    }

    /**
     * Compares two objects based on their "completeness" score.
     * The completeness score is calculated by the `calculateCompleteness` method, which assigns a numeric score
     * based on the amount of information (such as parameters, datasheets, images, etc.) available for each object.
     * The comparison is done in descending order, giving priority to the objects with higher completeness.
     *
     * @param object $a The first object to compare.
     * @param object $b The second object to compare.
     *
     * @return int A negative value if $b is more complete than $a, zero if they are equally complete,
     *             or a positive value if $a is more complete than $b.
     */
    private function compareByCompleteness(object $a, object $b): int
    {
        // Calculate the completeness score for each object
        $completenessA = $this->calculateCompleteness($a);
        $completenessB = $this->calculateCompleteness($b);
        
        // Sort in descending order by completeness (higher score is better)
        return $completenessB - $completenessA;
    }


    /**
     * Calculates a "completeness" score for a given part object based on the presence and count of various attributes.
     * The completeness score is used to prioritize parts that have more detailed information.
     *
     * The score is calculated as follows:
     * - Counts the number of elements in the `parameters`, `datasheets`, `images`, and `vendor_infos` arrays.
     * - Adds 1 point for the presence of `category`, `description`, `mpn`, `preview_image_url`, and `footprint`.
     * - Adds 1 or 2 points based on the presence or absence of `manufacturing_status` (higher score if `null`).
     *
     * @param object $part The part object for which the completeness score is calculated. The object is expected
     *                     to have properties like `parameters`, `datasheets`, `images`, `vendor_infos`, `category`,
     *                     `description`, `mpn`, `preview_image_url`, `footprint`, and `manufacturing_status`.
     *
     * @return int The calculated completeness score, with a higher score indicating more complete information.
     */
    private function calculateCompleteness(object $part): int
    {
        // Counts the number of elements in each field that can have multiple values
        $paramsCount = is_array($part->parameters) ? count($part->parameters) : 0;
        $datasheetsCount = is_array($part->datasheets) ? count($part->datasheets) : 0;
        $imagesCount = is_array($part->images) ? count($part->images) : 0;
        $vendorInfosCount = is_array($part->vendor_infos) ? count($part->vendor_infos) : 0;

        // Check for the presence of single fields and assign a score
        $categoryScore = !empty($part->category) ? 1 : 0;
        $descriptionScore = !empty($part->description) ? 1 : 0;
        $mpnScore = !empty($part->mpn) ? 1 : 0;
        $previewImageScore = !empty($part->preview_image_url) ? 1 : 0;
        $footprintScore = !empty($part->footprint) ? 1 : 0;

        // Weight for manufacturing_status: higher if null
        $manufacturingStatusScore = is_null($part->manufacturing_status) ? 2 : 1;

        // Sum the counts and scores to obtain a completeness score
        return $paramsCount
            + $datasheetsCount
            + $imagesCount
            + $vendorInfosCount
            + $categoryScore
            + $descriptionScore
            + $mpnScore
            + $previewImageScore
            + $footprintScore
            + $manufacturingStatusScore;
    }

    
    /**
     * Generates a unique provider ID by concatenating the part number and manufacturer name,
     * separated by a pipe (`|`). The generated ID is typically used to uniquely identify
     * a specific part from a particular manufacturer.
     *
     * @param string $partNumber The part number of the product.
     * @param string $manufacturer The name of the manufacturer.
     *
     * @return string The generated provider ID, in the format "partNumber|manufacturer".
     */
    private function generateProviderId(string $partNumber, string $manufacturer): string
    {
        return trim($partNumber) . '|' . trim($manufacturer);
    }

    /**
     * Maps the name of a country to its corresponding ISO 3166-1 alpha-2 code.
     *
     * @param string|null $countryName The name of the country to map.
     * @return string|null The ISO code for the country, or null if not found.
     */
    private function mapCountryNameToCode(?string $countryName): ?string
    {
        return $this->countryNameToCodeMap[$countryName] ?? null;
    }

}