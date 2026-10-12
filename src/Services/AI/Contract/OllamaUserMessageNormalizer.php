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

use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes user messages containing images to the format of the Ollama chat API, which expects the images as a list
 * of base64 encoded strings next to the text, instead of content parts like the OpenAI API. Messages with only text
 * are left to the default normalizer.
 */
#[Exclude] //Only used by the AI platform contracts, not by the serializer of the application
final class OllamaUserMessageNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!$data instanceof UserMessage) {
            return false;
        }

        foreach ($data->getContent() as $content) {
            if (!$content instanceof Text) {
                return true;
            }
        }

        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            //Not cacheable, as it depends on the content of the message
            UserMessage::class => false,
        ];
    }

    /**
     * @param UserMessage $data
     * @return array{role: string, content: string, images: list<string>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $texts = [];
        $images = [];

        foreach ($data->getContent() as $content) {
            if ($content instanceof Text) {
                $texts[] = $content->getText();
            } elseif ($content instanceof Image) {
                $images[] = $content->asBase64();
            } else {
                throw new \InvalidArgumentException(sprintf('Ollama does not support %s content in messages. Only text and images can be sent to Ollama models.',
                    (new \ReflectionClass($content))->getShortName()));
            }
        }

        return [
            'role' => $data->getRole()->value,
            'content' => implode("\n\n", $texts),
            'images' => $images,
        ];
    }
}
