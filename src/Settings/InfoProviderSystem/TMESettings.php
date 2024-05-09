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

use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: "TME settings", description: "Settings for the TME API")]
class TMESettings
{
    use SettingsTrait;

    private const SUPPORTED_CURRENCIES = ["EUR", "USD", "PLN", "GBP"];

    #[SettingsParameter(envVar: "PROVIDER_TME_KEY")]
    public ?string $apiToken = null;

    #[SettingsParameter(envVar: "PROVIDER_TME_SECRET")]
    public ?string $apiSecret = null;

    #[SettingsParameter(formType: CurrencyType::class, formOptions: ["preferred_choices" => self::SUPPORTED_CURRENCIES], envVar: "PROVIDER_TME_CURRENCY")]
    #[Assert\Choice(choices: self::SUPPORTED_CURRENCIES)]
    public string $currency = "EUR";

    #[SettingsParameter(formType: LanguageType::class, formOptions: ["preferred_choices" => ["en", "de", "fr", "pl"]], envVar: "PROVIDER_TME_LANGUAGE")]
    #[Assert\Language]
    public string $language = "en";

    #[SettingsParameter(envVar: "PROVIDER_TME_COUNTRY", formType: CountryType::class, formOptions: ["preferred_choices" => ["DE", "PL", "GB", "FR"]])]
    #[Assert\Country]
    public string $country = "DE";

    #[SettingsParameter(envVar: "bool:PROVIDER_TME_GET_GROSS_PRICES")]
    public bool $grossPrices = true;
}