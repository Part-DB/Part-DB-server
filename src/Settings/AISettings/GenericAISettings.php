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


namespace App\Settings\AISettings;

use App\Form\Type\APIKeyType;
use App\Services\AI\AIPlatformSettingsInterface;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Translation\StaticMessage;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(name: 'ai_generic', label: new TM("settings.ai.generic"), description: "settings.ai.generic.help")]
#[SettingsIcon("fa-robot")]
class GenericAISettings implements AIPlatformSettingsInterface
{
    use SettingsTrait;

    /** The completions endpoint path used by the symfony/ai-generic-platform bridge when none is configured */
    public const DEFAULT_COMPLETIONS_PATH = '/v1/chat/completions';

    /** The embeddings endpoint path used by the symfony/ai-generic-platform bridge when none is configured */
    public const DEFAULT_EMBEDDINGS_PATH = '/v1/embeddings';

    #[SettingsParameter(label: new TM("settings.ai.generic.baseUrl"),
        formType: UrlType::class,
        formOptions: ["attr" => ["placeholder" => new StaticMessage("https://api.openai.com/")]],
        envVar: "AI_GENERIC_BASE_URL", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $baseUrl = null;

    #[SettingsParameter(label: new TM("settings.ai.generic.apiKey"),
        formType: APIKeyType::class,
        formOptions: ["help_html" => true],
        envVar: "AI_GENERIC_API_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    #[SettingsParameter(label: new TM("settings.ai.generic.completionsPath"),
        description: new TM("settings.ai.generic.completionsPath.help"),
        formType: TextType::class,
        formOptions: ["attr" => ["placeholder" => new StaticMessage(self::DEFAULT_COMPLETIONS_PATH)]],
        envVar: "AI_GENERIC_COMPLETIONS_PATH", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $completionsPath = null;

    #[SettingsParameter(label: new TM("settings.ai.generic.embeddingsPath"),
        description: new TM("settings.ai.generic.embeddingsPath.help"),
        formType: TextType::class,
        formOptions: ["attr" => ["placeholder" => new StaticMessage(self::DEFAULT_EMBEDDINGS_PATH)]],
        envVar: "AI_GENERIC_EMBEDDINGS_PATH", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $embeddingsPath = null;

    #[SettingsParameter(label: new TM("settings.ai.timeout"),
        description: new TM("settings.ai.timeout.help"),
        formType: NumberType::class,
        formOptions: ["scale" => 0, "attr" => ["min" => 1]],
        envVar: "int:AI_GENERIC_TIMEOUT", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Range(min: 1, max: AISettings::TIMEOUT_LIMIT)]
    public int $timeout = 90;

    public function isAIPlatformEnabled(): bool
    {
        return $this->baseUrl !== null && $this->baseUrl !== "";
    }
}
