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

use App\Form\Type\APIKeyType;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.trustedparts"), description: new TM("settings.ips.trustedparts.help"))]
#[SettingsIcon("fa-plug")]
class TrustedPartsSettings
{
    use SettingsTrait;

    /** @var string[] The languages which are supported by the TrustedParts API for specification translations */
    public const SUPPORTED_LANGUAGES = ["en", "de", "es", "fr", "it", "pt", "ja"];

    #[SettingsParameter(label: new TM("settings.ips.trustedparts.companyId"),
        description: new TM("settings.ips.trustedparts.companyId.help"),
        formType: APIKeyType::class, formOptions: ["help_html" => true],
        envVar: "PROVIDER_TRUSTEDPARTS_COMPANY_ID", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $companyId = null;

    #[SettingsParameter(label: new TM("settings.ips.mouser.apiKey"),
        description: new TM("settings.ips.trustedparts.apiKey.help"),
        formType: APIKeyType::class, formOptions: ["help_html" => true],
        envVar: "PROVIDER_TRUSTEDPARTS_API_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    #[SettingsParameter(label: new TM("settings.ips.tme.currency"), formType: CurrencyType::class,
        formOptions: ["preferred_choices" => ["EUR", "USD", "GBP", "CHF"]],
        envVar: "PROVIDER_TRUSTEDPARTS_CURRENCY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Currency]
    public string $currency = "EUR";

    #[SettingsParameter(label: new TM("settings.ips.tme.country"), formType: CountryType::class,
        formOptions: ["preferred_choices" => ["DE", "GB", "FR", "US"]],
        envVar: "PROVIDER_TRUSTEDPARTS_COUNTRY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Country]
    public string $country = "DE";

    #[SettingsParameter(label: new TM("settings.ips.tme.language"), formType: LanguageType::class,
        formOptions: ["preferred_choices" => self::SUPPORTED_LANGUAGES],
        envVar: "PROVIDER_TRUSTEDPARTS_LANGUAGE", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Language]
    #[Assert\Choice(choices: self::SUPPORTED_LANGUAGES)]
    public string $language = "en";

    /** @var int The maximum number of results which are returned by a keyword search */
    #[SettingsParameter(label: new TM("settings.ips.trustedparts.searchLimit"),
        description: new TM("settings.ips.trustedparts.searchLimit.help"),
        formType: NumberType::class, formOptions: ["attr" => ["min" => 1, "max" => 100]],
        envVar: "int:PROVIDER_TRUSTEDPARTS_SEARCH_LIMIT", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Range(min: 1, max: 100)]
    public int $searchLimit = 25;

    #[SettingsParameter(label: new TM("settings.ips.trustedparts.inStockOnly"),
        description: new TM("settings.ips.trustedparts.inStockOnly.help"),
        envVar: "bool:PROVIDER_TRUSTEDPARTS_IN_STOCK_ONLY", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $inStockOnly = false;

    #[SettingsParameter(label: new TM("settings.ips.trustedparts.useCachedData"),
        description: new TM("settings.ips.trustedparts.useCachedData.help"),
        envVar: "bool:PROVIDER_TRUSTEDPARTS_USE_CACHED_DATA", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $useCachedData = false;
}
