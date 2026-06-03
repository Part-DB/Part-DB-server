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
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(name: 'ai_openrouter', label: new TM("settings.ai.openrouter"), description: "settings.ai.openrouter.help")]
#[SettingsIcon("fa-robot")]
class OpenRouterSettings implements AIPlatformSettingsInterface
{
    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ips.element14.apiKey"),
        formType: APIKeyType::class,
        formOptions: ["help_html" => true], envVar: "AI_OPENROUTER_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    public function isAIPlatformEnabled(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== "";
    }
}
