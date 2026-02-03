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

use App\Exceptions\ProviderIDNotSupportedException;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Settings\InfoProviderSystem\GenericWebProviderSettings;
use Brick\Schema\Interfaces\BreadcrumbList;
use Brick\Schema\Interfaces\ImageObject;
use Brick\Schema\Interfaces\Product;
use Brick\Schema\Interfaces\PropertyValue;
use Brick\Schema\Interfaces\QuantitativeValue;
use Brick\Schema\Interfaces\Thing;
use Brick\Schema\SchemaReader;
use Brick\Schema\SchemaTypeList;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GenericWebProvider implements InfoProviderInterface
{

    public const DISTRIBUTOR_NAME = 'Website';

    private readonly HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, private readonly GenericWebProviderSettings $settings,
        private readonly ProviderRegistry $providerRegistry, private readonly PartInfoRetriever $infoRetriever,
    )
    {
        $this->httpClient = $httpClient->withOptions(
            [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                ],
                'timeout' => 15,
            ]
        );
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Generic Web URL',
            'description' => 'Tries to extract a part from a given product webpage URL using common metadata standards like JSON-LD and OpenGraph.',
            //'url' => 'https://example.com',
            'disabled_help' => 'Enable in settings to use this provider',
            'settings_class' => GenericWebProviderSettings::class,
        ];
    }

    public function getProviderKey(): string
    {
        return 'generic_web';
    }

    public function isActive(): bool
    {
        return $this->settings->enabled;
    }

    public function searchByKeyword(string $keyword): array
    {
        $url = $this->fixAndValidateURL($keyword);

        //Before loading the page, try to delegate to another provider
        $delegatedPart = $this->delegateToOtherProvider($url);
        if ($delegatedPart !== null) {
            return [$delegatedPart];
        }

        try {
            return [
                $this->getDetails($keyword, false) //We already tried delegation
            ]; } catch (ProviderIDNotSupportedException $e) {
            return [];
        }
    }

    private function extractShopName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null) {
            return self::DISTRIBUTOR_NAME;
        }
        return $host;
    }

    private function breadcrumbToCategory(?BreadcrumbList $breadcrumbList): ?string
    {
        if ($breadcrumbList === null) {
            return null;
        }

        $items = $breadcrumbList->itemListElement->getValues();
        if (count($items) < 1) {
            return null;
        }

        try {
            //Build our category from the breadcrumb items
            $categories = [];
            foreach ($items as $item) {
                if (isset($item->name)) {
                    $categories[] = trim($item->name->toString());
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return implode(' -> ', $categories);
    }

    private function productToPart(Product $product, string $url, Crawler $dom, ?BreadcrumbList $categoryBreadcrumb): PartDetailDTO
    {
        $notes = $product->description->toString() ?? "";
        if ($product->disambiguatingDescription !== null) {
            if (!empty($notes)) {
                $notes .= "\n\n";
            }
            $notes .= $product->disambiguatingDescription->toString();
        }


        //Extract vendor infos
        $vendor_infos = null;
        $offer = $product->offers->getFirstValue();
        if ($offer !== null) {
            $prices = [];
            if ($offer->price->toString() !== null) {
                $prices = [new PriceDTO(
                    minimum_discount_amount: 1,
                    price: $offer->price->toString(),
                    currency_iso_code: $offer->priceCurrency?->toString()
                )];
            } else { //Check for nested offers (like IKEA does it)
                $offer2 = $offer->offers->getFirstValue();
                if ($offer2 !== null && $offer2->price->toString() !== null) {
                    $prices = [
                        new PriceDTO(
                            minimum_discount_amount: 1,
                            price: $offer2->price->toString(),
                            currency_iso_code: $offer2->priceCurrency?->toString()
                        )
                    ];
                }
            }

            $vendor_infos = [new PurchaseInfoDTO(
                distributor_name: $this->extractShopName($url),
                order_number: $product->sku?->toString() ?? $product->identifier?->toString() ?? 'Unknown',
                prices: $prices,
                product_url: $offer->url?->toString() ?? $url,
            )];
        }

        //Extract image:
        $image = null;
        if ($product->image !== null) {
            $imageObj = $product->image->getFirstValue();
            if (is_string($imageObj)) {
                $image = $imageObj;
            } else if ($imageObj instanceof ImageObject) {
                $image = $imageObj->contentUrl?->toString() ?? $imageObj->url?->toString();
            }
        }

        //Extract parameters from additionalProperty
        $parameters = [];
        foreach ($product->additionalProperty->getValues() as $property) {
            if ($property instanceof PropertyValue) { //TODO: Handle minValue and maxValue
                if ($property->unitText->toString() !== null) {
                    $parameters[] = ParameterDTO::parseValueField(
                        name: $property->name->toString() ?? 'Unknown',
                        value: $property->value->toString() ?? '',
                        unit: $property->unitText->toString()
                    );
                } else {
                    $parameters[] = ParameterDTO::parseValueIncludingUnit(
                        name: $property->name->toString() ?? 'Unknown',
                        value: $property->value->toString() ?? ''
                    );
                }
            }
        }

        //Try to extract weight
        $mass = null;
        if (($weight = $product?->weight->getFirstValue()) instanceof QuantitativeValue) {
            $mass = $weight->value->toString();
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $url,
            name: $product->name?->toString() ?? $product->alternateName?->toString() ?? $product?->mpn->toString() ?? 'Unknown Name',
            description: $this->getMetaContent($dom, 'og:description') ?? $this->getMetaContent($dom, 'description') ?? '',
            category: $this->breadcrumbToCategory($categoryBreadcrumb) ?? $product->category?->toString(),
            manufacturer: self::propertyOrString($product->manufacturer) ?? self::propertyOrString($product->brand),
            mpn: $product->mpn?->toString(),
            preview_image_url: $image,
            provider_url: $url,
            notes: $notes,
            parameters: $parameters,
            vendor_infos: $vendor_infos,
            mass: $mass
        );
    }

    private static function propertyOrString(SchemaTypeList|Thing|string|null $value, string $property = "name"): ?string
    {
        if ($value instanceof SchemaTypeList) {
            $value = $value->getFirstValue();
        }
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Thing) {
            return $value->$property?->toString();
        }
        return null;
    }


    /**
     * Gets the content of a meta tag by its name or property attribute, or null if not found
     * @param  Crawler  $dom
     * @param  string  $name
     * @return string|null
     */
    private function getMetaContent(Crawler $dom, string $name): ?string
    {
        $meta = $dom->filter('meta[property="'.$name.'"]');
        if ($meta->count() > 0) {
            return $meta->attr('content');
        }

        //Try name attribute
        $meta = $dom->filter('meta[name="'.$name.'"]');
        if ($meta->count() > 0) {
            return $meta->attr('content');
        }

        return null;
    }

    /**
     * Delegates the URL to another provider if possible, otherwise return null
     * @param  string  $url
     * @return SearchResultDTO|null
     */
    private function delegateToOtherProvider(string $url): ?SearchResultDTO
    {
        //Extract domain from url:
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null) {
            return null;
        }

        $provider = $this->providerRegistry->getProviderHandlingDomain($host);

        if ($provider !== null && $provider->isActive() && $provider->getProviderKey() !== $this->getProviderKey()) {
            try {
                $id = $provider->getIDFromURL($url);
                if ($id !== null) {
                    $results = $this->infoRetriever->searchByKeyword($id, [$provider]);
                    if (count($results) > 0) {
                        return $results[0];
                    }
                }
                return null;
            } catch (ProviderIDNotSupportedException $e) {
                //Ignore and continue
                return null;
            }
        }

        return null;
    }

    private function fixAndValidateURL(string $url): string
    {
        $originalUrl = $url;

        //Add scheme if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            //Remove any leading slashes
            $url = ltrim($url, '/');

            $url = 'https://'.$url;
        }

        //If this is not a valid URL with host, domain and path, throw an exception
        if (filter_var($url, FILTER_VALIDATE_URL) === false ||
            parse_url($url, PHP_URL_HOST) === null ||
            parse_url($url, PHP_URL_PATH) === null) {
            throw new ProviderIDNotSupportedException("The given ID is not a valid URL: ".$originalUrl);
        }

        return $url;
    }

    public function getDetails(string $id, bool $check_for_delegation = true): PartDetailDTO
    {
        $url = $this->fixAndValidateURL($id);

        if ($check_for_delegation) {
            //Before loading the page, try to delegate to another provider
            $delegatedPart = $this->delegateToOtherProvider($url);
            if ($delegatedPart !== null) {
                return $this->infoRetriever->getDetailsForSearchResult($delegatedPart);
            }
        }

        //Try to get the webpage content
        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();

        $dom = new Crawler($content);

        //Try to determine a canonical URL
        $canonicalURL = $url;
        if ($dom->filter('link[rel="canonical"]')->count() > 0) {
            $canonicalURL = $dom->filter('link[rel="canonical"]')->attr('href');
        } else if ($dom->filter('meta[property="og:url"]')->count() > 0) {
            $canonicalURL = $dom->filter('meta[property="og:url"]')->attr('content');
        }

        //If the canonical URL is relative, make it absolute
        if (parse_url($canonicalURL, PHP_URL_SCHEME) === null) {
            $parsedUrl = parse_url($url);
            $scheme = $parsedUrl['scheme'] ?? 'https';
            $host = $parsedUrl['host'] ?? '';
            $canonicalURL = $scheme.'://'.$host.$canonicalURL;
        }


        $schemaReader = SchemaReader::forAllFormats();
        $things = $schemaReader->readHtml($content, $canonicalURL);

        //Try to find a breadcrumb schema to extract the category
        $categoryBreadCrumbs = null;
        foreach ($things as $thing) {
            if ($thing instanceof BreadcrumbList) {
                $categoryBreadCrumbs = $thing;
                break;
            }
        }

        //Try to find a Product schema
        foreach ($things as $thing) {
            if ($thing instanceof Product) {
                return $this->productToPart($thing, $canonicalURL, $dom, $categoryBreadCrumbs);
            }
        }

        //If no JSON-LD data is found, try to extract basic data from meta tags
        $pageTitle = $dom->filter('title')->count() > 0 ? $dom->filter('title')->text() : 'Unknown';

        $prices = [];
        if ($price = $this->getMetaContent($dom, 'product:price:amount')) {
            $prices[] = new PriceDTO(
                minimum_discount_amount: 1,
                price: $price,
                currency_iso_code: $this->getMetaContent($dom, 'product:price:currency'),
            );
        } else {
            //Amazon fallback
            $amazonAmount = $dom->filter('input[type="hidden"][name*="amount"]');
            if ($amazonAmount->count() > 0) {
                $prices[] = new PriceDTO(
                    minimum_discount_amount: 1,
                    price: $amazonAmount->first()->attr('value'),
                    currency_iso_code: $dom->filter('input[type="hidden"][name*="currencyCode"]')->first()->attr('value'),
                );
            }
        }

        $vendor_infos = [new PurchaseInfoDTO(
            distributor_name: $this->extractShopName($canonicalURL),
            order_number: 'Unknown',
            prices: $prices,
            product_url: $canonicalURL,
        )];

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $canonicalURL,
            name: $this->getMetaContent($dom, 'og:title') ?? $pageTitle,
            description: $this->getMetaContent($dom, 'og:description') ?? $this->getMetaContent($dom, 'description') ?? '',
            manufacturer: $this->getMetaContent($dom, 'product:brand'),
            preview_image_url: $this->getMetaContent($dom, 'og:image'),
            provider_url: $canonicalURL,
            vendor_infos: $vendor_infos,
        );
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::PRICE
        ];
    }
}
