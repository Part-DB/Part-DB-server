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
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(label: new TM("settings.ips.oemsecrets"))]
#[SettingsIcon("fa-plug")]
class OEMSecretsSettings
{
    use SettingsTrait;

    public const SUPPORTED_CURRENCIES = ["AUD", "CAD", "CHF", "CNY", "DKK", "EUR", "GBP", "HKD", "ILS", "INR", "JPY", "KRW", "NOK",
                       "NZD", "RUB", "SEK", "SGD", "TWD", "USD"];

    #[SettingsParameter(label: new TM("settings.ips.element14.apiKey"),
        envVar: "PROVIDER_OEMSECRETS_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    #[Assert\Country]
    #[SettingsParameter(label: new TM("settings.ips.tme.country"), formType: CountryType::class, formOptions: ["preferred_choices" => ["DE", "PL", "GB", "FR", "US"]],
        envVar: "PROVIDER_OEMSECRETS_COUNTRY_CODE", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $country = "DE";

    #[SettingsParameter(label: new TM("settings.ips.tme.currency"), formType: CurrencyType::class, formOptions: ["preferred_choices" => self::SUPPORTED_CURRENCIES],
        envVar: "PROVIDER_OEMSECRETS_CURRENCY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Choice(choices: self::SUPPORTED_CURRENCIES)]
    public string $currency = "EUR";

    /**
     * @var bool If this is enabled, distributors with zero prices
     * will be discarded from the creation of a new part
     */
    #[SettingsParameter(label: new TM("settings.ips.oemsecrets.keepZeroPrices"), description: new TM("settings.ips.oemsecrets.keepZeroPrices.help"),
        envVar: "bool:PROVIDER_OEMSECRETS_ZERO_PRICE", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $keepZeroPrices = false;

    /**
     * @var bool If set to 1 the parameters for the part are generated
     * # from the description transforming unstructured descriptions into structured parameters;
     * # each parameter in description should have the form: "...;name1:value1;name2:value2"
     */
    #[SettingsParameter(label: new TM("settings.ips.oemsecrets.parseParams"), description: new TM("settings.ips.oemsecrets.parseParams.help"),
        envVar: "bool:PROVIDER_OEMSECRETS_SET_PARAM", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $parseParams = true;

    #[SettingsParameter(label: new TM("settings.ips.oemsecrets.sortMode"), envVar: "PROVIDER_OEMSECRETS_SORT_CRITERIA", envVarMapper: [self::class, "mapSortModeEnvVar"])]
    public OEMSecretsSortMode $sortMode = OEMSecretsSortMode::COMPLETENESS;


    public static function mapSortModeEnvVar(?string $value): OEMSecretsSortMode
    {
        if (!$value) {
            return OEMSecretsSortMode::NONE;
        }

        return OEMSecretsSortMode::tryFrom($value) ?? OEMSecretsSortMode::NONE;
    }
}