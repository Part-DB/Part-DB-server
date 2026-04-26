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


namespace App\Settings\InfoProviderSystem;

use App\Form\Settings\AiModelsType;
use App\Form\Settings\AiPlatformChoiceType;
use App\Services\AI\AIPlatforms;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\AI\Platform\Capability;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(name: "ai_extractor", label: new TM("settings.ips.ai_extractor"), description: new TM("settings.ips.ai_extractor.description"))]
#[SettingsIcon("fa-robot")]
class AIExtractorSettings
{
    private const MODEL_SELECTOR_LABEL = 'ai_extractor';

    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.ai_platform"), description: new TM("settings.ips.ai_extractor.ai_platform.help"),
        formType: AiPlatformChoiceType::class, formOptions: ['platform_selector_label' => self::MODEL_SELECTOR_LABEL],
        envVar: "string:PROVIDER_AI_EXTRACTOR_API_KEY", envVarMode: EnvVarMode::OVERWRITE
    )]
    public ?AIPlatforms $platform = null;

    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.model"), description: new TM("settings.ips.ai_extractor.model.description"),
        formType: AiModelsType::class, formOptions: ['platform_selector' => self::MODEL_SELECTOR_LABEL, 'filter_capability' => Capability::OUTPUT_STRUCTURED],
        envVar: "string:PROVIDER_AI_EXTRACTOR_MODEL", envVarMode: EnvVarMode::OVERWRITE
    )]
    public string $model = 'z-ai/glm-4.7';

    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.enabled"), description: new TM("settings.ips.ai_extractor.enabled.description"),
        envVar: "bool:PROVIDER_AI_EXTRACTOR_ENABLED", envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $enabled = false;

    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.max_content_length"), description: new TM("settings.ips.ai_extractor.max_content_length.description"),
        envVar: "int:PROVIDER_AI_EXTRACTOR_MAX_CONTENT_LENGTH", envVarMode: EnvVarMode::OVERWRITE
    )]
    public int $maxContentLength = 50000;
}
