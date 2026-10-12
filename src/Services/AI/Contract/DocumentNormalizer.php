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

namespace App\Services\AI\Contract;

use Symfony\AI\Platform\Message\Content\Document;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes documents (like PDF files) to the file content part of the OpenAI compatible chat completions API, which
 * is also used by OpenRouter. The platform bridges have no normalizer for documents, see AIPlatformContractFactory.
 */
#[Exclude] //Only used by the AI platform contracts, not by the serializer of the application
final class DocumentNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Document;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Document::class => true,
        ];
    }

    /**
     * @param Document $data
     * @return array{type: 'file', file: array{filename: string, file_data: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'file',
            'file' => [
                'filename' => $this->getFilename($data),
                'file_data' => $data->asDataUrl(),
            ],
        ];
    }

    /**
     * The APIs require a filename and use its extension to determine how to handle the file.
     */
    private function getFilename(Document $document): string
    {
        $filename = $document->getFilename() ?? 'document';

        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.' . (MimeTypes::getDefault()->getExtensions($document->getFormat())[0] ?? 'pdf');
        }

        return $filename;
    }
}
