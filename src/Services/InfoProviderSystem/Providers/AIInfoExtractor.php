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
use App\Helpers\RandomizeUseragentHttpClient;
use App\Services\AI\AIPlatformRegistry;
use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Settings\InfoProviderSystem\AIExtractorSettings;
use Brick\Schema\SchemaReader;
use Jkphl\Micrometa;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Intl\Languages;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\String\u;


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
        //Use NoPrivateNetworkHttpClient to prevent SSRF vulnerabilities, and RandomizeUseragentHttpClient to make it harder for servers to block us
        $this->httpClient = (new RandomizeUseragentHttpClient(new NoPrivateNetworkHttpClient($httpClient)))->withOptions(
            [
                'timeout' => 15,
            ]
        );
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'AI Information Extractor',
            'description' => 'Extract part info from any URL using OpenRouter LLM',
            //'url' => 'https://openrouter.ai',
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
        return $this->settings->platform !== null && $this->settings->model !== null && $this->settings->model !== '';
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
        /*$cleanedHtml = $this->cleanHTML($html);

        // Truncate to max content length
        $truncatedHtml = $this->truncateHTML($cleanedHtml, $this->settings->maxContentLength);*/

        //Convert html to markdown, to provide a cleaner input to the LLM.
        $markdown = $this->htmlToMarkdown($html);
        //Truncate markdown to max content length, if needed
        $markdown = u($markdown)->truncate($this->settings->maxContentLength, '... [truncated]')->toString();

        //Extract structured data using traditional methods, to provide additional context to the LLM. This can help improve accuracy, especially for technical specifications that might be in tables or specific formats.
        $structuredData = $this->extractStructuredData($html, $url);

        // Call LLM
        $llmResponse = $this->callLLM($markdown, $url, $structuredData);

        // Build and return PartDetailDTO
        $result = $this->jsonSchemaConverter->jsonToDTO($llmResponse, $this->getProviderKey(), $url, $url, self::DISTRIBUTOR_NAME);

        return $result;
    }

    /**
     * Extracts structured data from the HTML using microformats.
     * @param  string  $html
     * @param  string  $url
     * @return string JSON encoded structured data
     */
    private function extractStructuredData(string $html, string $url): string
    {
        $micrometa = new Micrometa\Ports\Parser();
        $items = $micrometa($url, $html);

        return json_encode($items->toObject(), JSON_THROW_ON_ERROR);
    }

    private function htmlToMarkdown(string $html): string
    {
        //Extract only the main content of the page to avoid overwhelming the LLM with irrelevant information.
        $crawler = new Crawler($html);
        $mainContent = $crawler->filter('main, article, #content');

        // If we found a specific content area, get its HTML; otherwise, use the whole body.
        //Concat the html of all matched nodes, to provide more context to the LLM, especially for pages that use multiple sections for product info.
        if ($mainContent->count() > 0) {
            $htmlToConvert = '';
            foreach ($mainContent as $node) {
                $htmlToConvert .= $node->ownerDocument->saveHTML($node);
                $htmlToConvert .= "\n\n"; // Add some spacing between sections
            }
        } else {
            //Use the whole body content, as it might contain relevant information, especially for simpler pages that don't have a clear main/content section.
            $htmlToConvert = $html;
        }


        //Concert to markdown
        $converter = new HtmlConverter([
            'strip_tags' => true,      // Removes tags that aren't Markdown-compatible (like <div>)
            'hard_break' => true,      // Preserves line breaks
            'remove_nodes' => 'nav footer script style' // Extra safety layer
        ]);

        return $converter->convert($htmlToConvert);
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

    private function callLLM(string $htmlContent, string $url, ?string $structuredData = null): array
    {
        $input = new MessageBag(
            Message::forSystem($this->buildSystemPrompt()),
            Message::ofUser("Extract part information from this webpage content:\n\nURL: $url\n\n$htmlContent")
        );

        if ($structuredData) {
            $input->add(Message::ofUser("Following data was extracted using traditional methods, but might be incomplete or inaccurate.
             Enrich it with the actual website data:\n\n".$structuredData));
        }

        try {
            $aiPlatform = $this->AIPlatformRegistry->getPlatform($this->settings->platform ?? throw new \RuntimeException('No AI platform selected') );

            //'openai/gpt-5-mini'
            $result = $aiPlatform->invoke($this->settings->model ?? throw new \RuntimeException('No model selected'), $input, [
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
        $tmp = <<<'PROMPT'
You are an expert at extracting electronic component information from web pages. Extract structured data in JSON format, from markdown extracted from a product page.
Focus on the main content of the page, such as product descriptions, specifications, and tables. Ignore navigation menus, footers, and sidebars.

Rules:
- manufacturing_status: Use "active", "obsolete", "nrfnd" (not recommended for new designs), "discontinued", or null
- parameters: Extract technical specs like voltage, current, temperature, etc. and put them into the fields according to the JSON schema. Include units if available.
- prices: Extract pricing tiers with minimum_quantity, price, and currency code
- URLs must be absolute (include https://...)
- If information is not found, use null
- Try to avoid duplicating parameters, if the same parameter is mentioned multiple times, or if it is already used in another field.
- Include only the 1 to 3 most relevant images, such as the main product image or important diagrams. Ignore decorative images, logos, or icons.
PROMPT;

        if ($this->settings->outputLanguage === null) {
            $tmp .= "\n\nProvide the response in the same language of the webpage.";
        } else {
            $tmp .= "\n\nThe response must be in ". Languages::getName($this->settings->outputLanguage, 'en') ." language. Translate texts if needed.";
        }

        if ($this->settings->additionalInstructions) {
            $tmp .= "\n\nAdditional instructions:\n" . $this->settings->additionalInstructions;
        }

        return $tmp;
    }

}
