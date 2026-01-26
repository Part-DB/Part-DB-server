<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

use App\Form\Type\APIKeyType;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.conrad"), description: new TM("settings.ips.conrad.help"))]
#[SettingsIcon("fa-plug")]
class ConradSettings
{
    use SettingsTrait;

    public const SUPPORTED_LANGUAGE = ["en", "de", "fr", "nl", "hu", "it", "pl", "cs", "da", "hr", "sv", "sk", "sl"];
    public const SUPPORTED_COUNTRIES = ["DE", "CH", "NL", "AT", "HU", "FR", "IT", "PL", "CZ", "BE", "DK", "HR", "SE", "SK", "SI", "GB", "US"];

    #[SettingsParameter(label: new TM("settings.ips.mouser.apiKey"), description: new TM("settings.ips.mouser.apiKey.help"),
        formType: APIKeyType::class,
        formOptions: ["help_html" => true], envVar: "PROVIDER_CONRAD_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    #[SettingsParameter(label: new TM("settings.ips.tme.language"), formType: LanguageType::class, formOptions: ["preferred_choices" => self::SUPPORTED_LANGUAGE],
        envVar: "PROVIDER_CONRAD_LANGUAGE", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Language()]
    #[Assert\Choice(choices: self::SUPPORTED_LANGUAGE)]
    public string $language = "en";

    #[SettingsParameter(label: new TM("settings.ips.tme.country"), formType: CountryType::class, formOptions: ["preferred_choices" => self::SUPPORTED_COUNTRIES],
        envVar: "PROVIDER_CONRAD_COUNTRY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Country]
    #[Assert\Choice(choices: self::SUPPORTED_COUNTRIES)]
    public string $country = "COM";

    #[SettingsParameter(label: new TM("settings.ips.reichelt.include_vat"),
        envVar: "bool:PROVIDER_CONRAD_INCLUDE_VAT", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $includeVAT = true;
}