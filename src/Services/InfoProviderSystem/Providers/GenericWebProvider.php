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
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use PhpOffice\PhpSpreadsheet\Calculation\Financial\Securities\Price;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GenericWebProvider implements InfoProviderInterface
{

    public const DISTRIBUTOR_NAME = 'Website';

    private readonly HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
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
            'description' => 'Tries to extract a part from a given product',
            //'url' => 'https://example.com',
            'disabled_help' => 'Enable in settings to use this provider'
        ];
    }

    public function getProviderKey(): string
    {
        return 'generic_web';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function searchByKeyword(string $keyword): array
    {
        return [
            $this->getDetails($keyword)
        ];
    }

    private function extractShopName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null) {
            return self::DISTRIBUTOR_NAME;
        }
        return $host;
    }

    private function productJsonLdToPart(array $jsonLd, string $url, Crawler $dom): PartDetailDTO
    {
        $notes = $jsonLd['description'] ?? "";
        if (isset($jsonLd['disambiguatingDescription'])) {
            if (!empty($notes)) {
                $notes .= "\n\n";
            }
            $notes .= $jsonLd['disambiguatingDescription'];
        }

        $vendor_infos = null;
        if (isset($jsonLd['offers'])) {

            if (array_is_list($jsonLd['offers'])) {
                $offer = $jsonLd['offers'][0];
            } else {
                $offer = $jsonLd['offers'];
            }

            //Make $jsonLd['url'] absolute if it's relative
            if (isset($jsonLd['url']) && parse_url($jsonLd['url'], PHP_URL_SCHEME) === null) {
                $parsedUrl = parse_url($url);
                $scheme = $parsedUrl['scheme'] ?? 'https';
                $host = $parsedUrl['host'] ?? '';
                $jsonLd['url'] = $scheme.'://'.$host.$jsonLd['url'];
            }

            $vendor_infos = [new PurchaseInfoDTO(
                distributor_name: $this->extractShopName($url),
                order_number: (string) ($jsonLd['sku'] ?? $jsonLd['@id'] ?? $jsonLd['gtin'] ?? 'Unknown'),
                prices: [new PriceDTO(minimum_discount_amount: 1, price: (string) $offer['price'], currency_iso_code: $offer['priceCurrency'] ?? null)],
                product_url: $jsonLd['url'] ?? $url,
        )];
        }

        $image = null;
        if (isset($jsonLd['image'])) {
            if (is_array($jsonLd['image'])) {
                $image = $jsonLd['image'][0] ?? null;
            } elseif (is_string($jsonLd['image'])) {
                $image = $jsonLd['image'];
            }
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $url,
            name: $jsonLd ['name'] ?? 'Unknown Name',
            description: $this->getMetaContent($dom, 'og:description') ?? $this->getMetaContent($dom, 'description') ?? '',
            category: isset($jsonLd['category']) && is_string($jsonLd['category']) ? $jsonLd['category'] : null,
            manufacturer: $jsonLd['manufacturer']['name'] ?? $jsonLd['brand']['name'] ?? null,
            mpn: $jsonLd['mpn'] ?? null,
            preview_image_url:  $image,
            provider_url: $url,
            notes: $notes,
            vendor_infos: $vendor_infos,
            mass: isset($jsonLd['weight']['value']) ? (float)$jsonLd['weight']['value'] : null,
        );
    }

    /**
     * Decodes JSON in a forgiving way, trying to fix common issues.
     * @param  string  $json
     * @return array
     * @throws \JsonException
     */
    private function json_decode_forgiving(string $json): array
    {
        //Sanitize common issues
        $json = preg_replace("/[\r\n]+/", " ", $json);
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

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

    public function getDetails(string $id): PartDetailDTO
    {
        //Add scheme if missing
        if (!preg_match('/^https?:\/\//', $id)) {
            //Remove any leading slashes
            $id = ltrim($id, '/');

            $id = 'https://'.$id;
        }

        $url = $id;

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

        //Try to find json-ld data in the head
        $jsonLdNodes = $dom->filter('script[type="application/ld+json"]');
        foreach ($jsonLdNodes as $node) {
            $jsonLd = $this->json_decode_forgiving($node->textContent);
            //If the content of json-ld is an array, try to find a product inside
            if (!array_is_list($jsonLd)) {
                $jsonLd = [$jsonLd];
            }
            foreach ($jsonLd as $item) {
                if (isset($item['@type']) && $item['@type'] === 'Product') {
                    return $this->productJsonLdToPart($item, $canonicalURL, $dom);
                }
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
