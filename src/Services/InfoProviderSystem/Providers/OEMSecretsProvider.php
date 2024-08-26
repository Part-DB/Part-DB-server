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
 * 
 * Key functionalities:
 * - Aggregation of results based on part_number and manufacturer_id to ensure unique identification of parts.
 * - Conversion of component descriptions into structured parameters (ParameterDTO) for better clarity and searchability.
 * - Aggregation of datasheets and images from multiple distributors, ensuring that all available resources are collected.
 * - Price handling, including filtering of distributors that offer zero prices, controlled by the `zero_price` configuration variable.
 * 
 * Configuration:
 * - The `zero_price` variable must be set in the `.env.local` file. If `zero_price` is set to 0, the class will skip 
 *   distributors that do not offer valid prices for the components.
 * - Currency and country settings can also be specified for localized pricing and distributor filtering.
 * 
 * Example Usage:
 * - `searchByKeyword`: This method takes a keyword and searches the OEMSecrets database for matching electronic components,
 *   aggregating results by part_number and manufacturer, and storing them in session for later retrieval.
 * - `getDetails`: Returns detailed information about a specific part, including parameters, images, and purchase information.
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
 * - Supports memory optimization techniques by clearing unused data from memory after processing batches of products.
 * - The class uses the session to store data temporarily for retrieval across multiple API requests and user actions.
 * - Data processing includes sanitizing input, avoiding duplicates, and dynamically adjusting information as new distributor 
 *   data becomes available (e.g., adding missing datasheets or parameters from subsequent API responses).
 * 
 * @package App\Services\InfoProviderSystem\Providers
 * @author Pasquale D'Orsi (https://github.com/pdo59)
 * @version 1.0.0
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
use Symfony\Contracts\HttpClient\ResponseInterface;

use Symfony\Component\HttpFoundation\RequestStack;


class OEMSecretsProvider implements InfoProviderInterface
{

    private const ENDPOINT_URL = 'https://oemsecretsapi.com/partsearch';

    public const DISTRIBUTOR_NAME = 'OEMSecrets';

    public function __construct(
        private readonly HttpClientInterface $oemsecretsClient,
        private readonly string $api_key,
        private readonly string $country_code,
        private readonly string $currency,
        private readonly string $zero_price,
        private readonly RequestStack $requestStack  
    ) {
    }

    // Store each data category in separate arrays in the session
    private array $basicInfoResults = [];
    private array $datasheetsResults = [];
    private array $imagesResults = [];
    private array $parametersResults = [];
    private array $purchaseInfoResults = [];



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
     * Searches for a keyword and returns a list of search results
     * @param  string  $keyword The keyword to search for
     * @return SearchResultDTO[] A list of search results
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

    
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $session->remove('basic_info_results');
        $session->remove('datasheets_results');
        $session->remove('images_results');
        $session->remove('parameters_results');
        $session->remove('purchase_info_results');
        //throw new \Exception("Session purged.");

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
        //------------------

        // Or activate this block when we use json file for debugging
        //------------------
        /*
        $jsonFilePath = 'response_1724678208985.json'; // 'response_1724245205141.json'; //'response_44-elem.json'; //'response_1724245205141.json'; //;  //'response_44-elem.json'; // 'mock_data.json';

        if (!file_exists($jsonFilePath)) {
            throw new \Exception("Il file JSON non è stato trovato.");
        }
        $jsonContent = file_get_contents($jsonFilePath);
        $response_array = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Errore nella decodifica del JSON: " . json_last_error_msg());
        }
        //------------------
        */

        $products = $response_array['stock'] ?? [];
        //dump($products);
        //throw new \Exception("Products");

        $basicInfoResults = [];
        $datasheetsResults = [];
        $imagesResults = [];
        $parametersResults = [];
        $purchaseInfoResults = [];

        foreach ($products as $product) {
            $this->processBatch($product, $basicInfoResults, $datasheetsResults, $imagesResults, $parametersResults, $purchaseInfoResults);
        }

        $session->set('basic_info_results', $basicInfoResults);
        $session->set('datasheets_results', $datasheetsResults);
        $session->set('images_results', $imagesResults);
        $session->set('parameters_results', $parametersResults);
        $session->set('purchase_info_results', $purchaseInfoResults);
        
        //throw new \Exception("Session created.");

        $this->sortBasicInfoByKeywordSimilarity($basicInfoResults, $keyword);

        return $basicInfoResults; 
    }

    /**
     * Returns detailed information about the part with the given id
     * @param  string  $id
     * @return PartDetailDTO
     */
    public function getDetails(string $provider_id): PartDetailDTO
    {
        // Get the session from the current request via RequestStack
        $session = $this->requestStack->getCurrentRequest()->getSession();

        // Retrieve the data related to provider_id from the various arrays saved in the session directly
        $basicInfo = $session->get('basic_info_results')[$provider_id] ?? null;
        $datasheets = $session->get('datasheets_results')[$provider_id] ?? [];
        $images = $session->get('images_results')[$provider_id] ?? [];
        $parameters = $session->get('parameters_results')[$provider_id] ?? [];
        $purchaseInfos = $session->get('purchase_info_results')[$provider_id] ?? [];

        // If the basicInfo does not exist, return a default PartDetailDTO to indicate that the product was not found
        if ($basicInfo === null) {
            return new PartDetailDTO(
                provider_key: $this->getProviderKey(),
                provider_id: 'unknown',
                name: 'Product not found',
                description: 'No description available',
                category: null,
                manufacturer: null,
                mpn: null,
                preview_image_url: null,
                manufacturing_status: null,
                provider_url: null,
                datasheets: [],
                images: [],
                parameters: [],
                vendor_infos: [],
                notes: null,
                footprint: null
            );
        }

        // Rebuild and return the PartDetailDTO object with all aggregated data
        return new PartDetailDTO(
            provider_key: $basicInfo['provider_key'] ?? $this->getProviderKey(),
            provider_id: $basicInfo['provider_id'] ?? $provider_id,
            name: $basicInfo['name'] ?? 'Unknown',
            description: $basicInfo['description'] ?? 'No description available',
            category: $basicInfo['category'] ?? null,
            manufacturer: $basicInfo['manufacturer'] ?? null,
            mpn: $basicInfo['mpn'] ?? null,
            preview_image_url: $basicInfo['preview_image_url'] ?? null,
            manufacturing_status: $basicInfo['manufacturing_status'] ?? null,
            provider_url: $basicInfo['provider_url'] ?? null,
            datasheets: $datasheets,
            images: $images,
            parameters: $parameters,
            vendor_infos: $purchaseInfos,
            notes: $basicInfo['notes'] ?? null, 
            footprint: null
        );

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
     *
     * @param array $product The product data received from the OEMSecrets API.
     * @param array &$basicInfoResults Array containing the basic product information (e.g., name, description, category).
     * @param array &$datasheetsResults Array containing datasheets collected from various distributors for the product.
     * @param array &$imagesResults Array containing images of the product collected from various distributors.
     * @param array &$parametersResults Array containing technical parameters extracted from the product descriptions.
     * @param array &$purchaseInfoResults Array containing purchase information, including distributors and pricing details.
     *
     * @return void
     * 
     * @see createOrUpdateBasicInfo() Creates or updates the basic product information.
     * @see getPrices() Extracts the pricing information for the product.
     * @see parseDataSheets() Parses and prevents duplication of datasheets.
     * @see getImages() Extracts and avoids duplication of images.
     * @see getParameters() Extracts technical parameters from the product description.
     * @see createPurchaseInfoDTO() Creates a PurchaseInfoDTO containing distributor and price information.
     *
     */
    private function processBatch(
        array $product, 
        array &$basicInfoResults, 
        array &$datasheetsResults, 
        array &$imagesResults, 
        array &$parametersResults, 
        array &$purchaseInfoResults
    ): void {
        $manufacturer = $product['manufacturer'];
        $part_number = $product['part_number'];
    
        if (is_null($manufacturer) || is_null($part_number)) {
            return;
        }
    
        $provider_id = trim($part_number) . '|' . trim($manufacturer);
    
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
            return; // Skip products without valid prices
        }

        $existingBasicInfo = isset($basicInfoResults[$provider_id]) && is_array($basicInfoResults[$provider_id]) 
        ? $basicInfoResults[$provider_id] 
        : [];

        // Ensure $existingBasicInfo is an array, otherwise initialize it as an empty array
        if (!is_array($existingBasicInfo)) {
            $existingBasicInfo = [];
        }
        
        $basicInfoResults[$provider_id] = $this->createOrUpdateBasicInfo(
            $provider_id, 
            $product, 
            $description, 
            $thenotes, 
            $existingBasicInfo);
    
    
        // Update images, datasheets, and parameters
        
        $newDatasheets = $this->parseDataSheets($product['datasheet_url'] ?? null, null, $datasheetsResults[$provider_id] ?? []);
        if ($newDatasheets !== null) {
            $datasheetsResults[$provider_id] = array_merge($datasheetsResults[$provider_id] ?? [], $newDatasheets);
            //dump("Provider: " . $provider_id);
            //dump($datasheetsResults[$provider_id]);
        }
        
        $imagesResults[$provider_id] = $this->getImages($product, $imagesResults[$provider_id] ?? []);

        $parametersResults[$provider_id] = $this->getParameters($product, $parametersResults[$provider_id] ?? []);
    
        // Handle purchase information
        $currentDistributor = $this->createPurchaseInfoDTO($product, $priceDTOs, $purchaseInfoResults[$provider_id] ?? []);
    
        // Update purchaseInfoResults only if the distributor is valid
        if ($currentDistributor !== null) {
            $purchaseInfoResults[$provider_id][] = $currentDistributor;
        }

        // Force garbage collection to deallocate unused memory cycles
        gc_collect_cycles();
    }
        
    /**
     * Extracts pricing information from the product data, converts it to PriceDTO objects,
     * and returns them as an array.
     *
     * @param array $product The product data from the OEMSecrets API containing price details.
     *
     * @return PriceDTO[] Array of PriceDTO objects representing different price tiers for the product.
     */
    private function getPrices(array $product): array
    {
        $prices = $product['prices'] ?? [];
        $priceDTOs = [];

        if (isset($prices[$this->currency])) {
            $priceDetails = $prices[$this->currency];

            if (is_array($priceDetails)) {
                foreach ($priceDetails as $priceDetail) {
                    if (
                        is_array($priceDetail) &&
                        isset($priceDetail['unit_break'], $priceDetail['unit_price']) &&
                        $priceDetail['unit_price'] !== "0.0000"
                    ) {
                        $priceDTOs[] = new PriceDTO(
                            minimum_discount_amount: (float)$priceDetail['unit_break'],
                            price: (string)$priceDetail['unit_price'],
                            currency_iso_code: $this->currency,
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
     *
     * @param array $product The product data from the OEMSecrets API containing image URLs.
     * @param array|null $existingImages Optional. Existing images for the product to avoid duplicates.
     *
     * @return FileDTO[] Array of FileDTO objects representing the product images.
     */
    private function getImages(array $product, array $existingImages = []): array
    {
        $images = $existingImages;
        $imageUrl = $product['image_url'] ?? null;
    
        if ($imageUrl) {
            $imageName = basename(parse_url($imageUrl, PHP_URL_PATH));
            if (!in_array($imageName, array_column($images, 'name'))) {
                $images[] = new FileDTO(url: $imageUrl, name: $imageName);
            }
        }
    
        return $images;
    }
    
    /**
     * Extracts technical parameters from the product description, ensures no duplicates, and returns them as an array.
     *
     * @param array $product The product data from the OEMSecrets API containing product descriptions.
     * @param array|null $existingParameters Optional. Existing parameters for the product to avoid duplicates.
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
     * @param array $product The product data from the OEMSecrets API.
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
            $order_number = $sku ?: (string)$product['source_part_number'];

            // Check if this distributor is already present
            foreach ($existingPurchaseInfos as $purchaseInfo) {
                if ($purchaseInfo->distributor_name === $distributor_name && $purchaseInfo->order_number === $order_number) {
                    return null; // Evitiamo di duplicare i distributori
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
     * Creates or updates the basic information of a product, including the description, category, manufacturer,
     * and other metadata. This function manages the PartDetailDTO creation or update.
     *
     * @param string $provider_id The unique identifier for the product based on part_number and manufacturer.
     * @param array $product The product data from the OEMSecrets API.
     * @param string $description The truncated description for the product.
     * @param string $thenotes The full description saved as notes for the product.
     * @param PartDetailDTO|null $existingPartDetail Optional. The existing PartDetailDTO to update if the product already exists.
     *
     * @return PartDetailDTO The updated or newly created PartDetailDTO containing basic product information.
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
                'provider_url' => $this->generateInquiryUrl($product['part_number']), //$product['buy_now_url'],
                'notes' => $thenotes, 
                'footprint' => null
            ];
        }

        // Update fields only if empty or undefined, with additional check for preview_image_url
        return [
            'provider_key' => $existingBasicInfo['provider_key'] ?? $this->getProviderKey(),
            'provider_id' => $existingBasicInfo['provider_id'] ?? $provider_id,
            'name' => $existingBasicInfo['name'] ?? $product['part_number'],
            //'description' => $existingBasicInfo['description'] ?? $description,
             // Update description if it's null/empty
            'description' => !empty($existingBasicInfo['description']) 
                ? $existingBasicInfo['description'] 
                : $description,
            //'category' => $existingBasicInfo['category'] ?? $product['category'],
            // Update category if it's null/empty
            'category' => !empty($existingBasicInfo['category']) 
                ? $existingBasicInfo['category'] 
                : $product['category'],
            'manufacturer' => $existingBasicInfo['manufacturer'] ?? $product['manufacturer'],
            'mpn' => $existingBasicInfo['mpn'] ?? $product['source_part_number'],
            //'preview_image_url' => $existingBasicInfo['preview_image_url'] ?? ($product['image_url'] ?? null),
            'preview_image_url' => !empty($existingBasicInfo['preview_image_url']) 
                ? $existingBasicInfo['preview_image_url'] 
                : ($product['image_url'] ?? null),
            //'manufacturing_status' => $existingBasicInfo['manufacturing_status'] ?? $this->releaseStatusCodeToManufacturingStatus(
            //    $product['life_cycle'] ?? null,
            //    (int)($product['quantity_in_stock'] ?? 0)
            //),
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
     *
     * @return array|null Returns an array containing the new datasheet if unique, or null if the datasheet is a duplicate or invalid.
     *
     * @see FileDTO Used to create datasheet objects with a URL and name.
     */
    private function parseDataSheets(?string $sheetUrl, ?string $sheetName, array $existingDatasheets = [], string $eventLinkParam = 'event_link'): ?array
    {
        //dump($sheetUrl);

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
                if (strpos($sheetName, '.') === false || !preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx)$/i', $sheetName)) {
                    // If the name does not have a valid extension, assign a default name
                    //$sheetName = 'datasheet.pdf';
                    $sheetName = 'datasheet_' . uniqid() . '.pdf';
                }
            }
        }

        // Create an array of existing file names
        $existingNames = array_map(function ($existingDatasheet) {
            return $existingDatasheet->name;
        }, $existingDatasheets);

        // Check if the name already exists
        if (in_array($sheetName, $existingNames)) {
            // The name already exists, so do not add the datasheet
            return null;
        }
        
        /*
        $originalSheetName = $sheetName;
        $counter = 1;
        while (in_array($sheetName, $existingNames)) {
            // If the name already exists, append a counter to the default name
            if ($originalSheetName === 'datasheet.pdf') {
                $sheetName = 'datasheet(' . $counter . ').pdf';
            } else {
                // If it's not the default name, just keep it as is
                return null;
            }
            $counter++;
        }
        */

        // Create an array with the datasheet data if it does not already exist
        $result = [];
        $result[] = new FileDTO(url: $sheetUrl, name: $sheetName);
        return $result;
    }

    /**
     * Converts the lifecycle status from the API to a ManufacturingStatus
     *  - Factory Special Order / Ordine speciale in fabbrica
     *  - Not Recommended for New Designs / Non raccomandato per nuovi progetti
     *  - New Product / Nuovo prodotto (if availableInStock > 0 else ANNOUNCED)
     *  - End of Life / Fine vita
     *  - vuoto / Attivo 
     *  
     * @param  string|null  $productStatus The lifecycle status from the Mouser API
     * @param  int  $availableInStock The number of parts available in stock
     * @return ManufacturingStatus|null
     * 
     * @todo Probably need to review the values of field Lifecyclestatus
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
     * The function processes the description by splitting it using the `;` character to identify key-value pairs,
     * and assigns appropriate values, such as name, value, unit, and symbol to the parameters. 
     * If the description is empty or cannot be processed, the function returns null.
     *
     * The extraction logic handles typical values, ranges, units, and textual information from the description.
     * It tries to account for various formats, such as numerical ranges, values with units, and other textual information.
     *
     * @param string|null $description The description text from which parameters are to be extracted.
     *                                 The description should have key-value pairs separated by `;`.
     *
     * @return array|null Returns an array of `ParameterDTO` objects if parameters are successfully extracted,
     *                    or null if no valid parameters can be extracted from the description.
     *
     * @see ParameterDTO Used to create parameter objects with name, value, unit, and additional information.
     */
    private function parseDescriptionToParameters(?string $description): ?array
    {
        // If the description is null or empty, return null
        if ($description === null || trim($description) === '') {
            return null;
        }

         // Array to store parsed parameters
        $parameters = [];

        // Split the description using the ';' separator
        $parts = explode(';', $description);

        // Process each part of the description
        foreach ($parts as $part) {
            $part = trim($part);

            // Check if the part contains a key-value structure
            if (strpos($part, ':') !== false) {
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
                        value_typ: $value_typ,
                        value_min: $value_min,
                        value_max: $value_max,
                        value_text: $parsedValue['value_text'] ?? null,
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
     * @param string|null $value The value string to be parsed, which may contain a number, unit, or both.
     * @param string|null $defaultUnit The default unit to use if no unit is found in the value string.
     *
     * @return array|null An associative array with parsed components: 
     *                    - 'value_typ' => float|null (the typical or parsed value)
     *                    - 'range_min' => float|null (the minimum value if it's a range)
     *                    - 'range_max' => float|null (the maximum value if it's a range)
     *                    - 'unit' => string|null (the detected or default unit)
     *                    - 'symbol' => string|null (any special symbol or additional text)
     *                    Returns null if parsing fails or if the input is empty.
     */
    private function customParseValueIncludingUnit(string $name, string $value): ?array
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
        if (strpos($value, '...') !== false || strpos($value, '~') !== false || strpos($value, '±') !== false) {
            // Handle ranges
            $value = str_replace(['...', '~'], '...', $value); // Uniformiamo i separatori di range
            $rangeParts = preg_split('/\s*[\.\~]\s*/', $value);

            if (count($rangeParts) === 2) {
                [$result['value_min'], $result['value_max']] = $this->customSplitIntoValueAndUnit($rangeParts[0], $rangeParts[1]);
                $result['unit'] = $rangeParts[1]['unit'] ?? $rangeParts[0]['unit'];
            }
        } elseif (strpos($value, '@') !== false) {
            // If we find "@", we treat it as additional textual information
            [$numericValue, $textValue] = explode('@', $value);
            $result['value_typ'] = (float) $numericValue;
            $result['value_text'] = trim($textValue);
        } else {
            // Check if the value is numeric with a unit
            if (preg_match('/^([\+\-]?\d+(\.\d+)?)([a-zA-Z%°]+)?$/', $value, $matches)) {
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
     * @param string $input The input string containing both a numerical value and a unit.
     *
     * @return array An associative array with two elements:
     *               - 'value' => string The numerical part of the string.
     *               - 'unit' => string|null The unit part of the string, or null if no unit is detected.
     */
    private function customSplitIntoValueAndUnit(string $value1, string $value2 = null): array
    {
        // Separate numbers and units (basic parsing handling)
        $unit = null;
        $value_typ = null;

        // Search for the number + unit pattern
        if (preg_match('/^([\+\-]?\d+(\.\d+)?)([a-zA-Z%°]+)?$/', $value1, $matches)) {
            $value_typ = $matches[1];
            $unit = $matches[3] ?? null;
        }

        $result = [
            'value_typ' => $value_typ,
            'unit' => $unit,
        ];

        if ($value2 !== null) {
            if (preg_match('/^([\+\-]?\d+(\.\d+)?)([a-zA-Z%°]+)?$/', $value2, $matches2)) {
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
     *
     * @param string $partNumber The part number to include in the URL.
     * @param string $oemInquiry The inquiry path for the OEMSecrets API, with a default value of 'compare/'.
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
        $inquiryUrl = $baseUrl . $oemInquiry . $encodedPartNumber;
        return $inquiryUrl;
    }

    /**
     * Sorts the $basicInfoResults array by the similarity of the 'name' field to the search keyword.
     * The comparison is case-insensitive and trims any extra spaces before calculating the similarity.
     * The comparison is based on the Levenshtein distance, which measures the number of single-character 
     * edits required to change one word into another. The element with the smallest distance to the search
     * keyword will appear first in the sorted list.
     *
     * @param array $basicInfoResults The array of results to be sorted. Each element must have a 'name' key.
     * @param string $searchKeyword The keyword to compare each 'name' against for similarity.
     *
     * @return void The function sorts the $basicInfoResults array in place.
     */
    private function sortBasicInfoByKeywordSimilarity(array &$basicInfoResults, string $searchKeyword): void
    {
        usort($basicInfoResults, function ($a, $b) use ($searchKeyword) {
            // Trim whitespace from the strings to avoid issues with spaces
            $nameA = trim($a['name']);
            $nameB = trim($b['name']);
    
            // Primary sort: exact match with the search keyword
            $isExactMatchA = strcasecmp($nameA, $searchKeyword) === 0;
            $isExactMatchB = strcasecmp($nameB, $searchKeyword) === 0;
            if ($isExactMatchA && !$isExactMatchB) {
                return -1;
            } elseif (!$isExactMatchA && $isExactMatchB) {
                return 1;
            }
    
            // Secondary sort: names that start with the search keyword
            $startsWithKeywordA = stripos($nameA, $searchKeyword) === 0;
            $startsWithKeywordB = stripos($nameB, $searchKeyword) === 0;
            if ($startsWithKeywordA && !$startsWithKeywordB) {
                return -1;
            } elseif (!$startsWithKeywordA && $startsWithKeywordB) {
                return 1;
            }
    
            // If both names start with the keyword, compare numeric parts followed by alphabetic characters
            if ($startsWithKeywordA && $startsWithKeywordB) {
                $suffixA = substr($nameA, strlen($searchKeyword));
                $suffixB = substr($nameB, strlen($searchKeyword));
    
                // Extract numeric part and alphabetic part using regex
                preg_match('/^(\d+)([a-zA-Z]*)/', $suffixA, $matchesA);
                preg_match('/^(\d+)([a-zA-Z]*)/', $suffixB, $matchesB);
    
                $numericPartA = isset($matchesA[1]) ? (int)$matchesA[1] : 0;
                $numericPartB = isset($matchesB[1]) ? (int)$matchesB[1] : 0;
    
                // Compare numeric parts first
                if ($numericPartA !== $numericPartB) {
                    return $numericPartA - $numericPartB;
                }
    
                // If numeric parts are equal, compare the alphabetic parts
                $alphaPartA = $matchesA[2] ?? '';
                $alphaPartB = $matchesB[2] ?? '';
                $alphaComparison = strcmp($alphaPartA, $alphaPartB);
    
                if ($alphaComparison !== 0) {
                    return $alphaComparison;
                }
            }
    
            // Tertiary sort: general similarity between name and search keyword
            $similarityA = 0;
            $similarityB = 0;
            similar_text($searchKeyword, $nameA, $similarityA);
            similar_text($searchKeyword, $nameB, $similarityB);
    
            if ($similarityA > $similarityB) {
                return -1;
            } elseif ($similarityB > $similarityA) {
                return 1;
            }
    
            // Final sort by manufacturer name when the names are identical
            return strcasecmp($a['manufacturer'], $b['manufacturer']);
        }); 
       
    }


   
}