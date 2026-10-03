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


namespace App\Services\InfoProviderSystem;

use App\Services\AI\AIPlatformRegistry;
use App\Settings\InfoProviderSystem\AIExtractorSettings;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\Intl\Languages;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Lets the configured AI model extract part information as structured data (matching the schema of the
 * DTOJsonSchemaConverter) from some input. Shared by all AI based info providers, so that they use the same
 * platform, model, output language and additional instructions, and report failures the same way.
 */
final class AIPartInfoExtractor
{
    /** @var int How much of a failed provider response is quoted in the error message */
    private const MAX_REPORTED_RESPONSE_LENGTH = 500;

    public function __construct(
        private readonly AIExtractorSettings $settings,
        private readonly AIPlatformRegistry $AIPlatformRegistry,
        private readonly DTOJsonSchemaConverter $jsonSchemaConverter,
    ) {
    }

    /**
     * Checks if an AI platform and a model are configured, so that extract() can be used.
     */
    public function isConfigured(): bool
    {
        return $this->settings->platform !== null && $this->settings->model !== null && $this->settings->model !== '';
    }

    /**
     * Returns the maximum number of characters of content, which should be passed to the model.
     */
    public function getMaxContentLength(): int
    {
        return $this->settings->maxContentLength;
    }

    /**
     * Invokes the configured model with the given messages and returns the structured part data it extracted.
     * @param  MessageBag  $input  The messages to send, including the system prompt
     * @return array The extracted data, as described by DTOJsonSchemaConverter::getJSONSchema()
     * @throws \RuntimeException If the invocation failed
     */
    public function extract(MessageBag $input): array
    {
        try {
            $aiPlatform = $this->AIPlatformRegistry->getPlatform($this->settings->platform ?? throw new \RuntimeException('No AI platform selected') );

            // AI inference can take much longer than PHP's default max_execution_time (typically 30s).
            // The HTTP client timeout already enforces the configured limit; disable PHP's constraint here.
            set_time_limit(0);

            $result = $aiPlatform->invoke($this->settings->model ?? throw new \RuntimeException('No model selected'), $input, [
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $this->jsonSchemaConverter->getJSONSchema(),
                ]
            ]);
            //The platform returns a deferred result: the request is only really carried out (and the answer
            //converted) when the result is read. Reading it outside this try would let a provider error - a
            //rejected model, an exhausted quota, an invalid key - escape as an unhandled exception, which ends
            //the whole request with a 500 instead of the error message this catch was written for.
            return $result->getResult()->getContent();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'LLM invocation failed: '.$e->getMessage().$this->describeProviderResponse($result ?? null),
                previous: $e
            );
        }
    }

    /**
     * Appends the instructions configured by the user (output language and additional instructions) to the given
     * system prompt.
     * @param  string  $systemPrompt  The task specific part of the system prompt
     * @param  string  $sourceName  How the source of the information is called in the prompt (e.g. "webpage")
     */
    public function withConfiguredInstructions(string $systemPrompt, string $sourceName): string
    {
        if ($this->settings->outputLanguage === null) {
            $systemPrompt .= "\n\nProvide the response in the same language of the $sourceName.";
        } else {
            $systemPrompt .= "\n\nThe response must be in ". Languages::getName($this->settings->outputLanguage, 'en') ." language. Translate texts if needed.";
        }

        if ($this->settings->additionalInstructions) {
            $systemPrompt .= "\n\nAdditional instructions:\n" . $this->settings->additionalInstructions;
        }

        return $systemPrompt;
    }

    /**
     * Describes what the provider actually answered, for the message of a failed invocation.
     *
     * The exceptions of the platform only carry what its converter made of the answer, and that can be as
     * unhelpful as "Provider returned error" - the wording a gateway like OpenRouter uses when the model
     * provider behind it refused, with the reason in a field the converter drops. The raw response is still
     * around at this point, so the status code and the beginning of the body are taken from there: without
     * them, an administrator has nothing to act on.
     *
     * @return string The description, or an empty string if the response is not available
     */
    private function describeProviderResponse(?DeferredResult $result): string
    {
        if (!$result instanceof DeferredResult) {
            return '';
        }

        try {
            $response = $result->getRawResult()->getObject();

            if (!$response instanceof ResponseInterface) {
                return '';
            }

            //false: the body of an error response is wanted here, not another exception
            $body = trim($response->getContent(false));

            return sprintf(' (provider answered HTTP %d: %s)', $response->getStatusCode(),
                mb_strlen($body) > self::MAX_REPORTED_RESPONSE_LENGTH
                    ? mb_substr($body, 0, self::MAX_REPORTED_RESPONSE_LENGTH).'...'
                    : $body);
        } catch (\Throwable) {
            //Whatever went wrong while describing the failure must not replace the failure itself
            return '';
        }
    }
}
