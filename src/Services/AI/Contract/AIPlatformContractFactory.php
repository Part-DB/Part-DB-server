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

use Symfony\AI\Platform\Bridge\Ollama\Contract\OllamaContract;
use Symfony\AI\Platform\Contract;

/**
 * Creates the contracts (the conversion of messages to API requests) for the AI platforms, which add support for
 * content the platform bridges can not send by themselves. They are injected into the platforms by the
 * AIPlatformContractPass.
 */
final class AIPlatformContractFactory
{
    /**
     * For platforms using the OpenAI compatible chat completions API (OpenRouter, LM Studio and the generic platform).
     * Images are already supported by the default contract, documents like PDF files are added.
     */
    public static function createOpenAICompatible(): Contract
    {
        return Contract::create([
            new DocumentNormalizer(),
        ]);
    }

    /**
     * For Ollama, whose API expects images in another format than the OpenAI API. Ollama does not support documents.
     */
    public static function createOllama(): Contract
    {
        return OllamaContract::create([
            new OllamaUserMessageNormalizer(),
        ]);
    }
}
