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

use App\Exceptions\ProviderIDNotSupportedException;
use App\Services\AI\AIPlatformRegistry;
use App\Services\AI\AIPlatforms;
use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Settings\InfoProviderSystem\AIExtractorSettings;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AIInfoExtractor implements InfoProviderInterface
{
    use FixAndValidateUrlTrait;

    private const DISTRIBUTOR_NAME = 'AI Extracted';

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly AIExtractorSettings $settings,
        private readonly AIPlatformRegistry $AIPlatformRegistry,
        private readonly DTOJsonSchemaConverter $jsonSchemaConverter,
    ) {
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
        return true;
        //return !empty($this->settings->apiKey) && $this->settings->enabled;
    }

    public function searchByKeyword(string $keyword): array
    {
        try {
            return [
                $this->getDetails($keyword)
            ]; } catch (ProviderIDNotSupportedException $e) {
            return [];
        }
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $url = $this->fixAndValidateURL($id);

        // Fetch HTML content
        $response = $this->httpClient->request('GET', $url);
        $html = $response->getContent();

        // Clean HTML
        $cleanedHtml = $this->cleanHTML($html);

        // Truncate to max content length
        $truncatedHtml = $this->truncateHTML($cleanedHtml, $this->settings->maxContentLength);

        // Call LLM
        $llmResponse = $this->callLLM($truncatedHtml, $url);

        // Build and return PartDetailDTO
        return $this->jsonSchemaConverter->jsonToDTO($llmResponse, $this->getProviderKey(), $url, $url, self::DISTRIBUTOR_NAME);
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

    private function callLLM(string $htmlContent, string $url): array
    {
        $input = new MessageBag(
            Message::forSystem($this->buildSystemPrompt()),
            Message::ofUser("Extract part information from this webpage content:\n\nURL: $url\n\n$htmlContent")
        );

        try {
            $aiPlatform = $this->AIPlatformRegistry->getPlatform(AIPlatforms::OPENROUTER);

            //'openai/gpt-5-mini'
            $result = $aiPlatform->invoke('openrouter/auto', $input, [
                'response_format' => [
                    'type' => 'json_schema',
                        'json_schema' => $this->jsonSchemaConverter->getJSONSchema(),
                ]
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('LLM invocation failed: '.$e->getMessage(), previous: $e);
        }

        return $result->getResult()->getContent();
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

}
