<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(label: new TM("settings.ips.tme"))]
#[SettingsIcon("fa-plug")]
class TMESettings
{
    use SettingsTrait;

    private const SUPPORTED_CURRENCIES = ["EUR", "USD", "PLN", "GBP"];

    #[SettingsParameter(label: new TM("settings.ips.tme.token"),
        description: new TM("settings.ips.tme.token.help"), formOptions: ["help_html" => true],
        envVar: "PROVIDER_TME_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiToken = null;

    #[SettingsParameter(label: new TM("settings.ips.tme.secret"),
        envVar: "PROVIDER_TME_SECRET", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiSecret = null;

    #[SettingsParameter(label: new TM("settings.ips.tme.currency"), formType: CurrencyType::class, formOptions: ["preferred_choices" => self::SUPPORTED_CURRENCIES],
        envVar: "PROVIDER_TME_CURRENCY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Choice(choices: self::SUPPORTED_CURRENCIES)]
    public string $currency = "EUR";

    #[SettingsParameter(label: new TM("settings.ips.tme.language"), formType: LanguageType::class, formOptions: ["preferred_choices" => ["en", "de", "fr", "pl"]],
        envVar: "PROVIDER_TME_LANGUAGE", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Language]
    public string $language = "en";

    #[SettingsParameter(label: new TM("settings.ips.tme.country"), formType: CountryType::class, formOptions: ["preferred_choices" => ["DE", "PL", "GB", "FR"]],
        envVar: "PROVIDER_TME_COUNTRY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Country]
    public string $country = "DE";

    #[SettingsParameter(label: new TM("settings.ips.tme.grossPrices"),
        envVar: "bool:PROVIDER_TME_GET_GROSS_PRICES", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $grossPrices = true;
}