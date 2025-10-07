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


namespace App\Settings\SystemSettings;

use App\Form\Type\LocaleSelectType;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.system.localization"))]
#[SettingsIcon("fa-globe")]
class LocalizationSettings
{
    use SettingsTrait;

    #[Assert\Locale()]
    #[Assert\NotBlank()]
    #[SettingsParameter(label: new TM("settings.system.localization.locale"), formType: LocaleSelectType::class,
    envVar: "string:DEFAULT_LANG", envVarMode: EnvVarMode::OVERWRITE)]
    public string $locale = 'en';

    #[Assert\Timezone()]
    #[Assert\NotBlank()]
    #[SettingsParameter(label: new TM("settings.system.localization.timezone"), formType: TimezoneType::class,
        envVar: "string:DEFAULT_TIMEZONE", envVarMode: EnvVarMode::OVERWRITE)]
    public string $timezone = 'Europe/Berlin';

    #[Assert\Currency()]
    #[Assert\NotBlank()]
    #[SettingsParameter(label: new TM("settings.system.localization.base_currency"),
        description: new TM("settings.system.localization.base_currency_description"),
        formType: CurrencyType::class, formOptions: ['preferred_choices' => ['EUR', 'USD', 'GBP', "JPY", "CNY"], 'help_html' => true],
        envVar: "string:BASE_CURRENCY", envVarMode: EnvVarMode::OVERWRITE
    )]
    public string $baseCurrency = 'EUR';
}