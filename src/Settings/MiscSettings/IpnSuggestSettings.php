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


namespace App\Settings\MiscSettings;

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.misc.ipn_suggest"))]
#[SettingsIcon("fa-list")]
class IpnSuggestSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.misc.ipn_suggest.autoAppendSuffix"),
        envVar: "bool:IPN_AUTO_APPEND_SUFFIX", envVarMode: EnvVarMode::OVERWRITE,
    )]
    public bool $autoAppendSuffix = true;

    #[SettingsParameter(label: new TM("settings.misc.ipn_suggest.suggestPartDigits"),
        description: new TM("settings.misc.ipn_suggest.suggestPartDigits.help"),
        formOptions: ['attr' => ['min' => 1, 'max' => 100]],
        envVar: "int:IPN_SUGGEST_PART_DIGITS", envVarMode: EnvVarMode::OVERWRITE
    )]
    #[Assert\Range(min: 1, max: 6)]
    public int $suggestPartDigits = 4;

    #[SettingsParameter(
        label: new TM("settings.misc.ipn_suggest.useDuplicateDescription"),
        envVar: "bool:IPN_USE_DUPLICATE_DESCRIPTION", envVarMode: EnvVarMode::OVERWRITE,
    )]
    public bool $useDuplicateDescription = false;
}
