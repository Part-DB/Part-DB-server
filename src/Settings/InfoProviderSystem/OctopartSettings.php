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
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.octopart"))]
#[SettingsIcon("fa-plug")]
class OctopartSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.ips.digikey.client_id"),
        formType: APIKeyType::class,
        envVar: "PROVIDER_OCTOPART_CLIENT_ID", envVarMode: EnvVarMode::OVERWRITE,
    )]
    public ?string $clientId = null;

    #[SettingsParameter(
        label: new TM("settings.ips.digikey.secret"),
        formType: APIKeyType::class,
        envVar: "PROVIDER_OCTOPART_SECRET", envVarMode: EnvVarMode::OVERWRITE
    )]
    public ?string $secret = null;

    #[SettingsParameter(label: new TM("settings.ips.tme.currency"), formType: CurrencyType::class,
        formOptions: ["preferred_choices" => ["EUR", "USD", "CHF", "GBP"]],
        envVar: "PROVIDER_OCTOPART_CURRENCY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Currency()]
    public string $currency = "EUR";

    #[SettingsParameter(label: new TM("settings.ips.tme.country"), formType: CountryType::class,
        envVar: "PROVIDER_OCTOPART_COUNTRY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Country]
    public string $country = "DE";

    #[SettingsParameter(label: new TM("settings.ips.octopart.searchLimit"), description: new TM("settings.ips.octopart.searchLimit.help"),
        formType: NumberType::class, formOptions: ["attr" => ["min" => 1, "max" => 100]],
        envVar: "int:PROVIDER_OCTOPART_SEARCH_LIMIT", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Range(min: 1, max: 100)]
    public int $searchLimit = 10;

    #[SettingsParameter(label: new TM("settings.ips.octopart.onlyAuthorizedSellers"),
        description: new TM("settings.ips.octopart.onlyAuthorizedSellers.help"),
        envVar: "bool:PROVIDER_OCTOPART_ONLY_AUTHORIZED_SELLERS", envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $onlyAuthorizedSellers = true;

}
