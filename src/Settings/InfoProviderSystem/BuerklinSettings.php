<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
 *  Copyright (C) 2025 Marc Kreidler (https://github.com/mkne)
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
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.buerklin"), description: new TM("settings.ips.buerklin.help"))]
#[SettingsIcon("fa-plug")]
class BuerklinSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.ips.digikey.client_id"),
        formType: APIKeyType::class,
        envVar: "PROVIDER_BUERKLIN_CLIENT_ID", envVarMode: EnvVarMode::OVERWRITE
    )]
    public ?string $clientId = null;

    #[SettingsParameter(
        label: new TM("settings.ips.digikey.secret"),
        formType: APIKeyType::class,
        envVar: "PROVIDER_BUERKLIN_SECRET", envVarMode: EnvVarMode::OVERWRITE
    )]
    public ?string $secret = null;

        #[SettingsParameter(
        label: new TM("settings.ips.buerklin.username"),
        formType: APIKeyType::class,
        envVar: "PROVIDER_BUERKLIN_USER", envVarMode: EnvVarMode::OVERWRITE
    )]
    public ?string $username = null;

        #[SettingsParameter(
        label: new TM("user.edit.password"),
        formType: APIKeyType::class,
        envVar: "PROVIDER_BUERKLIN_PASSWORD", envVarMode: EnvVarMode::OVERWRITE
    )]
    public ?string $password = null;

    #[SettingsParameter(label: new TM("settings.ips.tme.currency"), formType: CurrencyType::class,
        formOptions: ["preferred_choices" => ["EUR"]],
        envVar: "PROVIDER_BUERKLIN_CURRENCY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Currency()]
    public string $currency = "EUR";

    #[SettingsParameter(label: new TM("settings.ips.tme.language"), formType: LanguageType::class,
                        formOptions: ["preferred_choices" => ["en", "de"]],
        envVar: "PROVIDER_BUERKLIN_LANGUAGE", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Language]
    public string $language = "en";
}
