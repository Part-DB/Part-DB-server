<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *  Copyright (C) 2026 Rahul Singh (https://github.com/rahools)
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
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\AIExtractorSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIInfoExtractor implements InfoProviderInterface
{
    private const DISTRIBUTOR_NAME = 'AI Extracted';

    private readonly HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, private readonly AIExtractorSettings $settings)
    {
        $this->httpClient = $httpClient->withOptions([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; Part-DB AI-Extractor/1.0)',
            ],
        ]);
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'AI Information Extractor',
            'description' => 'Extract part info from any URL using OpenRouter LLM',
            'url' => 'https://openrouter.ai',
            'disabled_help' => 'Configure OpenRouter API key in settings',
            'settings_class' => AIExtractorSettings::class,
        ];
    }

    public function getProviderKey(): string
    {
        return 'ai_extractor';
    }

    public function isActive(): bool
    {
        return !empty($this->settings->apiKey) && $this->settings->enabled;
    }

    public function searchByKeyword(string $keyword): array
    {
        // Treat the keyword as a URL and return a single search result
        $url = $this->normalizeURL($keyword);

        try {
            $part = $this->getDetails($url);
            return [
                new SearchResultDTO(
                    provider_key: $this->getProviderKey(),
                    provider_id: $url,
                    name: $part->name,
                    description: $part->description,
                    category: $part->category,
                    manufacturer: $part->manufacturer,
                    mpn: $part->mpn,
                    preview_image_url: $part->preview_image_url,
                    manufacturing_status: $part->manufacturing_status,
                    provider_url: $part->provider_url,
                    footprint: $part->footprint,
                    gtin: $part->gtin,
                ),
            ];
        } catch (\Throwable $e) {
            // Return empty array on error
            return [];
        }
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $url = $this->normalizeURL($id);

        // Fetch HTML content
        $response = $this->httpClient->request('GET', $url);
        $html = $response->getContent();

        // Clean HTML
        $cleanedHtml = $this->cleanHTML($html);

        // Truncate to max content length
        $truncatedHtml = $this->truncateHTML($cleanedHtml, $this->settings->maxContentLength);

        // Call OpenRouter API
        $llmResponse = $this->callOpenRouterAPI($truncatedHtml, $url);

        // Parse JSON response
        $data = json_decode($llmResponse, true, 512, JSON_THROW_ON_ERROR);

        // Build and return PartDetailDTO
        return $this->buildPartDetailDTO($data, $url);
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
            ProviderCapabilities::PARAMETERS,
        ];
    }

    private function normalizeURL(string $url): string
    {
        // Add https:// if no protocol
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        // Validate URL
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException("Invalid URL: $url");
        }

        return $url;
    }

    private function cleanHTML(string $html): string
    {
        // Remove script tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // Remove style tags
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Remove nav tags
        $html = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html);

        // Remove footer tags
        $html = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html);

        // Remove header tags
        $html = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $html);

        // Remove HTML comments
        $html = preg_replace('/<!--(.*?)-->/is', '', $html);

        return $html;
    }

    private function truncateHTML(string $html, int $maxLength): string
    {
        if (strlen($html) <= $maxLength) {
            return $html;
        }

        // Truncate and find the last > or space to avoid cutting tags
        $truncated = substr($html, 0, $maxLength);

        // Find the last occurrence of > or space
        $lastPos = max(strrpos($truncated, '>'), strrpos($truncated, ' '));

        if ($lastPos !== false && $lastPos > $maxLength * 0.9) {
            $truncated = substr($truncated, 0, $lastPos + 1);
        }

        return $truncated;
    }

    private function callOpenRouterAPI(string $htmlContent, string $url): string
    {
        $systemPrompt = $this->buildSystemPrompt();

        // Define the tool/function for structured output
        $toolDefinition = [
            'type' => 'function',
            'function' => [
                'name' => 'extract_part_info',
                'description' => 'Extract electronic component information from a webpage',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Product name'],
                        'description' => ['type' => 'string', 'description' => 'Product description'],
                        'manufacturer' => ['type' => ['string', 'null'], 'description' => 'Manufacturer name'],
                        'mpn' => ['type' => ['string', 'null'], 'description' => 'Manufacturer Part Number'],
                        'category' => ['type' => ['string', 'null'], 'description' => 'Product category'],
                        'manufacturing_status' => ['type' => ['string', 'null'], 'enum' => ['active', 'obsolete', 'nrfnd', 'discontinued', null], 'description' => 'Manufacturing status'],
                        'footprint' => ['type' => ['string', 'null'], 'description' => 'Package/footprint type'],
                        'mass' => ['type' => ['number', 'null'], 'description' => 'Mass in grams'],
                        'parameters' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'value' => ['type' => 'string'],
                                    'unit' => ['type' => ['string', 'null']],
                                ],
                                'required' => ['name', 'value'],
                            ],
                        ],
                        'datasheets' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'url' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                ],
                                'required' => ['url'],
                            ],
                        ],
                        'images' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'url' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                ],
                                'required' => ['url'],
                            ],
                        ],
                        'vendor_infos' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'distributor_name' => ['type' => 'string'],
                                    'order_number' => ['type' => ['string', 'null']],
                                    'product_url' => ['type' => 'string'],
                                    'prices' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'minimum_quantity' => ['type' => 'integer'],
                                                'price' => ['type' => 'number'],
                                                'currency' => ['type' => 'string'],
                                            ],
                                            'required' => ['minimum_quantity', 'price', 'currency'],
                                        ],
                                    ],
                                ],
                                'required' => ['distributor_name', 'product_url'],
                            ],
                        ],
                        'manufacturer_product_url' => ['type' => ['string', 'null'], 'description' => 'Manufacturer product page URL'],
                    ],
                    'required' => ['name', 'description'],
                ],
            ],
        ];

        $payload = [
            'model' => $this->settings->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => "Extract part information from this webpage content:\n\nURL: $url\n\n$htmlContent",
                ],
            ],
            'tools' => [$toolDefinition],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'extract_part_info']],
            'max_tokens' => 4096,
            'temperature' => 0.1,
        ];

        $response = $this->httpClient->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->settings->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://github.com/Part-DB/Part-DB-server',
                'X-Title' => 'Part-DB AI Info Extractor',
            ],
            'json' => $payload,
        ]);

        $data = $response->toArray();

        $message = $data['choices'][0]['message'] ?? null;
        if ($message === null) {
            throw new \RuntimeException('No response message from LLM');
        }

        // Check if the model used the tool/function call
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                if ($toolCall['function']['name'] === 'extract_part_info') {
                    return $toolCall['function']['arguments'];
                }
            }
        }

        // Fallback to content if no tool call (some models might not support tool calling)
        $content = $message['content'] ?? throw new \RuntimeException('No response content from LLM');

        // Strip markdown code blocks if present (fallback for models without tool support)
        $content = preg_replace('/^```(?:json)?\s*\n?/i', '', $content);
        $content = preg_replace('/\n?```\s*$/i', '', $content);
        $content = trim($content);

        return $content;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert at extracting electronic component information from web pages. Extract structured data in JSON format.

Return ONLY a valid JSON object with this exact structure:
{
  "name": "string",
  "description": "string",
  "manufacturer": "string | null",
  "mpn": "string | null",
  "category": "string | null",
  "manufacturing_status": "active|obsolete|nrfnd|discontinued|null",
  "footprint": "string | null",
  "mass": "number | null (in grams)",
  "parameters": [{"name": "string", "value": "string", "unit": "string | null"}],
  "datasheets": [{"url": "string", "description": "string"}],
  "images": [{"url": "string", "description": "string"}],
  "vendor_infos": [{
    "distributor_name": "string",
    "order_number": "string | null",
    "product_url": "string",
    "prices": [{"minimum_quantity": int, "price": number, "currency": "string"}]
  }],
  "manufacturer_product_url": "string | null"
}

Rules:
- manufacturing_status: Use "active", "obsolete", "nrfnd" (not recommended for new designs), "discontinued", or null
- parameters: Extract technical specs like voltage, current, temperature, etc.
- prices: Extract pricing tiers with minimum_quantity, price, and currency code
- URLs must be absolute (include https://...)
- If information is not found, use null
- Return ONLY the JSON, no explanation text

For parameters, combine name, value, and unit. The unit should be separate if possible.
PROMPT;
    }

    private function buildPartDetailDTO(array $data, string $url): PartDetailDTO
    {
        // Map manufacturing status
        $manufacturingStatus = null;
        if (!empty($data['manufacturing_status'])) {
            $status = strtolower((string) $data['manufacturing_status']);
            $manufacturingStatus = match ($status) {
                'active' => ManufacturingStatus::ACTIVE,
                'obsolete', 'discontinued' => ManufacturingStatus::DISCONTINUED,
                'nrfnd', 'not recommended for new designs' => ManufacturingStatus::NRFND,
                'eol' => ManufacturingStatus::EOL,
                'announced' => ManufacturingStatus::ANNOUNCED,
                default => null,
            };
        }

        // Build parameters
        $parameters = null;
        if (!empty($data['parameters']) && is_array($data['parameters'])) {
            $parameters = [];
            foreach ($data['parameters'] as $p) {
                if (!empty($p['name'])) {
                    $value = $p['value'] ?? '';
                    $unit = $p['unit'] ?? null;
                    // Combine value and unit for parsing
                    $valueWithUnit = $unit ? $value . ' ' . $unit : $value;
                    $parameters[] = ParameterDTO::parseValueField(
                        name: $p['name'],
                        value: $valueWithUnit
                    );
                }
            }
        }

        // Build datasheets
        $datasheets = null;
        if (!empty($data['datasheets']) && is_array($data['datasheets'])) {
            $datasheets = [];
            foreach ($data['datasheets'] as $d) {
                if (!empty($d['url'])) {
                    $datasheets[] = new FileDTO(
                        url: $d['url'],
                        name: $d['description'] ?? 'Datasheet'
                    );
                }
            }
        }

        // Build images
        $images = null;
        if (!empty($data['images']) && is_array($data['images'])) {
            $images = [];
            foreach ($data['images'] as $i) {
                if (!empty($i['url'])) {
                    $images[] = new FileDTO(
                        url: $i['url'],
                        name: $i['description'] ?? 'Image'
                    );
                }
            }
        }

        // Build vendor infos
        $vendorInfos = null;
        if (!empty($data['vendor_infos']) && is_array($data['vendor_infos'])) {
            $vendorInfos = [];
            foreach ($data['vendor_infos'] as $v) {
                $prices = [];
                if (!empty($v['prices']) && is_array($v['prices'])) {
                    foreach ($v['prices'] as $p) {
                        $prices[] = new PriceDTO(
                            minimum_discount_amount: (int) ($p['minimum_quantity'] ?? 1),
                            price: (string) ($p['price'] ?? 0),
                            currency_iso_code: $p['currency'] ?? 'USD',
                            price_related_quantity: (int) ($p['minimum_quantity'] ?? 1),
                        );
                    }
                }

                $vendorInfos[] = new PurchaseInfoDTO(
                    distributor_name: $v['distributor_name'] ?? self::DISTRIBUTOR_NAME,
                    order_number: $v['order_number'] ?? 'Unknown',
                    prices: $prices,
                    product_url: $v['product_url'] ?? $url,
                );
            }
        }

        // Get preview image URL
        $previewImageUrl = null;
        if (!empty($data['images']) && is_array($data['images']) && !empty($data['images'][0]['url'])) {
            $previewImageUrl = $data['images'][0]['url'];
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $url,
            name: $data['name'] ?? 'Unknown',
            description: $data['description'] ?? '',
            category: $data['category'] ?? null,
            manufacturer: $data['manufacturer'] ?? null,
            mpn: $data['mpn'] ?? null,
            preview_image_url: $previewImageUrl,
            manufacturing_status: $manufacturingStatus,
            provider_url: $url,
            footprint: $data['footprint'] ?? null,
            mass: isset($data['mass']) && is_numeric($data['mass']) ? (float) $data['mass'] : null,
            notes: null,
            datasheets: $datasheets,
            images: $images,
            parameters: $parameters,
            vendor_infos: $vendorInfos,
            manufacturer_product_url: $data['manufacturer_product_url'] ?? null,
        );
    }
}
