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
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints\Language;

#[Settings(name: "ai_extractor", label: new TM("settings.ips.ai_extractor"), description: new TM("settings.ips.ai_extractor.description"))]
#[SettingsIcon("fa-plug")]
class AIExtractorSettings
{
    private const MODEL_SELECTOR_LABEL = 'ai_extractor';

    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.ai_platform"),
        formType: AiPlatformChoiceType::class, formOptions: ['platform_selector_label' => self::MODEL_SELECTOR_LABEL],
    )]
    public ?AIPlatforms $platform = null;

    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.model"), description: new TM("settings.ips.ai_extractor.model.help"),
        formType: AiModelsType::class, formOptions: ['platform_selector' => self::MODEL_SELECTOR_LABEL, 'filter_capability' => Capability::OUTPUT_STRUCTURED],
    )]
    public ?string $model = null;

    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.max_content_length"),
        description: new TM("settings.ips.ai_extractor.max_content_length.description"),
    )]
    public int $maxContentLength = 50000;

    #[Language]
    #[SettingsParameter(label: new TM("settings.ips.ai_extractor.output_language"), description: new TM("settings.ips.ai_extractor.output_language.description"),
        formType: LanguageType::class,
    )]
    public ?string $outputLanguage = null;
}
