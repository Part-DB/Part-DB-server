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

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.reichelt"), description: new TM("settings.ips.reichelt.help"))]
#[SettingsIcon("fa-plug")]
class ReicheltSettings
{
    use SettingsTrait;

    public const SUPPORTED_LANGUAGE = ["en", "de", "fr", "nl", "pl", "it", "es"];

    #[SettingsParameter(label: new TM("settings.ips.lcsc.enabled"), envVar: "bool:PROVIDER_REICHELT_ENABLED")]
    public bool $enabled = false;

    #[SettingsParameter(label: new TM("settings.ips.tme.currency"), formType: CurrencyType::class, formOptions: ["preferred_choices" => ["EUR"]], envVar: "PROVIDER_REICHELT_CURRENCY")]
    public string $currency = "EUR";

    #[SettingsParameter(label: new TM("settings.ips.tme.language"), formType: LanguageType::class, formOptions: ["preferred_choices" => self::SUPPORTED_LANGUAGE], envVar: "PROVIDER_REICHELT_LANGUAGE")]
    #[Assert\Language()]
    #[Assert\Choice(choices: self::SUPPORTED_LANGUAGE)]
    public string $language = "en";

    #[SettingsParameter(label: new TM("settings.ips.tme.country"), envVar: "PROVIDER_REICHELT_COUNTRY", formType: CountryType::class, formOptions: ["preferred_choices" => ["DE", "PL", "GB", "FR"]])]
    #[Assert\Country]
    public string $country = "DE";

    #[SettingsParameter(label: new TM("settings.ips.reichelt.include_vat"), envVar: "bool:PROVIDER_REICHELT_INCLUDE_VAT")]
    public bool $includeVAT = true;

}