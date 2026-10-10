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
use App\Services\InfoProviderSystem\AIPartInfoExtractor;
use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\UploadedDocument;
use App\Services\InfoProviderSystem\UploadedDocumentStorage;
use App\Settings\InfoProviderSystem\AIFileExtractorSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

use function Symfony\Component\String\u;

/**
 * Extracts part information from an uploaded document (like a PDF datasheet) using an AI model.
 * The ID of a part is the token of the document in the UploadedDocumentStorage, so a part can only be retrieved
 * as long as its document is stored there (or the result is still cached).
 */
final class AIDocumentProvider implements InfoProviderInterface
{
    public const PROVIDER_KEY = 'ai_document';

    private const DISTRIBUTOR_NAME = 'Document';

    public function __construct(
        private readonly AIFileExtractorSettings $settings,
        private readonly AIPartInfoExtractor $extractor,
        private readonly DTOJsonSchemaConverter $jsonSchemaConverter,
        private readonly UploadedDocumentStorage $documentStorage,
        private readonly CacheItemPoolInterface $partInfoCache,
    ) {
    }

    public function getProviderInfo(): ProviderInfoDTO
    {
        return new ProviderInfoDTO(
            key: self::PROVIDER_KEY,
            name: 'AI File Extractor',
            description: 'Extract part info from uploaded files (like PDF datasheets) using LLM',
            disabledHelp: 'Configure AI settings',
            settingsClass: AIFileExtractorSettings::class,
            capabilities: [
                ProviderCapabilities::BASIC,
                ProviderCapabilities::FOOTPRINT,
                ProviderCapabilities::PARAMETERS,
            ],
            expensive: true,
            slow: true,
        );
    }

    public function isActive(): bool
    {
        return $this->settings->isConfigured();
    }

    public function searchByKeyword(string $keyword, array $options = []): array
    {
        //There is nothing to search for, documents are only found by the token they got on upload
        if ($this->documentStorage->retrieve($keyword) === null) {
            return [];
        }

        return [$this->getDetails($keyword, $options)];
    }

    public function getDetails(string $id, array $options = []): PartDetailDTO
    {
        //The result is cached by this provider itself, as the AI call is slow and costly, and the PartInfoRetriever
        //uses different cache entries for the different option sets of the upload and the part creation page
        $cacheKey = 'ai_document_'.hash('xxh3', $id);

        if ($options[self::OPTION_NO_CACHE] ?? false) {
            $this->partInfoCache->deleteItem($cacheKey);
        }

        $cacheItem = $this->partInfoCache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $document = $this->documentStorage->retrieve($id)
            ?? throw new ProviderIDNotSupportedException(sprintf('The document with ID %s is not available (anymore). Please upload it again.', $id));

        $result = $this->jsonSchemaConverter->jsonToDTO($this->callLLM($document), self::PROVIDER_KEY, $id,
            distributorNameFallback: self::DISTRIBUTOR_NAME);

        $cacheItem->set($result);
        $cacheItem->expiresAfter(3600 * 2);
        $this->partInfoCache->save($cacheItem);

        return $result;
    }

    private function callLLM(UploadedDocument $document): array
    {
        $input = new MessageBag(
            Message::forSystem($this->buildSystemPrompt($document->isFileSentToModel())),
            $document->isFileSentToModel() ? $this->createFileMessage($document) : $this->createTextMessage($document),
        );

        if ($document->context !== null && trim($document->context) !== '') {
            $input->add(Message::ofUser("Additional context given by the user, which has priority over the rules above:\n\n".trim($document->context)));
        }

        return $this->extractor->extract($input, $this->settings);
    }

    private function createTextMessage(UploadedDocument $document): UserMessage
    {
        $text = u($document->textContent)->truncate($this->settings->maxContentLength, '... [truncated]')->toString();

        return Message::ofUser("Extract part information from the text of this document:\n\nFilename: {$document->filename}\n\n$text");
    }

    /**
     * Creates the message containing the file itself, for files without extracted text (like images and scanned documents)
     */
    private function createFileMessage(UploadedDocument $document): UserMessage
    {
        $path = $this->documentStorage->retrieveFilePath($document->token)
            ?? throw new ProviderIDNotSupportedException(sprintf('The file of the document with ID %s is not available (anymore). Please upload it again.', $document->token));
        $mimeType = $document->fileMimeType ?? throw new \RuntimeException('The type of the uploaded file is unknown.');

        //The content is only loaded, when the request is built
        $data = static fn(): string => file_get_contents($path)
            ?: throw new \RuntimeException('The uploaded file could not be read.');

        if (str_starts_with($mimeType, 'image/')) {
            return Message::ofUser("Extract part information from this image:\n\nFilename: {$document->filename}", new Image($data, $mimeType, $path));
        }

        return Message::ofUser("Extract part information from this document:\n\nFilename: {$document->filename}", new Document($data, $mimeType, $path));
    }

    /**
     * @param  bool  $fileSent  Whether the file itself is sent to the model, instead of its extracted text
     */
    private function buildSystemPrompt(bool $fileSent): string
    {
        $source = $fileSent
            ? <<<'SOURCE'
You are an expert at extracting electronic component information from documents. Extract structured data in JSON format, from the attached file.
The file is usually a datasheet of a part, but could also be a product brief, a catalog page, a distributor offer, a photo of a part or its label, or similar. It might be a scanned document, so read the text from it.
SOURCE
            : <<<'SOURCE'
You are an expert at extracting electronic component information from documents. Extract structured data in JSON format, from the text extracted from a document.
The document is usually a datasheet of a part, but could also be a product brief, a catalog page, a distributor offer or similar. The text was extracted automatically, so tables might have lost their layout.
SOURCE;

        $tmp = $source . <<<'PROMPT'


Rules:
- Describe the single part the document is about. If the document covers a family of parts (e.g. multiple variants or package options), describe the first or most generic one and put the differences of the variants into the notes field.
- The user might give additional context, like the exact part number to extract. Follow it: if a part number is given, describe exactly this variant (with its specific parameters and package) and use the part number as mpn.
- manufacturing_status: Use "active", "obsolete", "nrfnd" (not recommended for new designs), "discontinued", or "unknown". Use "unknown" unless the document states it explicitly.
- parameters: Extract technical specs like voltage, current, temperature, etc. and put them into the fields according to the JSON schema. Include units if available. Prefer the values from the absolute maximum ratings and electrical characteristics tables.
- footprint: Use the package name (e.g. "SOT-23", "TQFP-32") if the document mentions it.
- prices / vendor_infos: Only fill these if the document contains concrete order numbers or prices of a distributor (e.g. an offer or an invoice). Otherwise leave them empty.
- URLs: Only include URLs that literally appear in the document, never make up URLs. If there are none, leave images and datasheets empty.
- The document itself is handled elsewhere: it is attached to the part automatically. Do not include it in your response, so do not add it to datasheets or images, also not via a URL printed in the document that points to this same document (like the download link of this datasheet).
- If information is not found, use an empty string for texts and null for numbers
- Try to avoid duplicating parameters, if the same parameter is mentioned multiple times, or if it is already used in another field.
- Include a short summary of the features and applications into the notes field. The notes field can be formatted with Markdown (e.g. lists, tables, bold text).

PROMPT;

        return $this->extractor->withConfiguredInstructions($tmp, 'document', $this->settings);
    }
}
