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

    public function __construct(private readonly HttpClientInterface $httpClient)
    {

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

    private function productJsonLdToPart(array $jsonLd, string $url): PartDetailDTO
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
            $vendor_infos = [new PurchaseInfoDTO(
                distributor_name: $this->extractShopName($url),
                order_number: $jsonLd['sku'] ?? $jsonLd['@id'] ?? $jsonLd['gtin'] ?? 'Unknown',
                prices: [new PriceDTO(minimum_discount_amount: 1, price: (string) $jsonLd['offers']['price'], currency_iso_code: $jsonLd['offers']['priceCurrency'] ?? null)],
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
            description: '',
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

    public function getDetails(string $id): PartDetailDTO
    {
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

        //Try to find json-ld data in the head
        $jsonLdNodes = $dom->filter('head script[type="application/ld+json"]');
        foreach ($jsonLdNodes as $node) {
            $jsonLd = $this->json_decode_forgiving($node->textContent);
            if (isset($jsonLd['@type']) && $jsonLd['@type'] === 'Product') { //If we find a product use that data
                return $this->productJsonLdToPart($jsonLd, $canonicalURL);
            }
        }

        

        return null;
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
