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
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Translation\StaticMessage;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(name: 'ai_ollama', label: new TM("settings.ai.ollama"))]
#[SettingsIcon("fa-robot")]
class OllamaSettings implements AIPlatformSettingsInterface
{
    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ai.ollama.endpoint"),
        formType: UrlType::class,
        formOptions: ["attr" => ["placeholder" => new StaticMessage("http://localhost:11434")]],
        envVar: "AI_OLLAMA_ENDPOINT", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $endpoint = null;

    #[SettingsParameter(label: new TM("settings.ai.ollama.apiKey"),
        formType: APIKeyType::class,
        envVar: "AI_OLLAMA_API_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    public function isAIPlatformEnabled(): bool
    {
        return $this->endpoint !== null && $this->endpoint !== "";
    }
}
