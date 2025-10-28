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

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(label: new TM("settings.system.privacy"))]
#[SettingsIcon("fa-location-pin-lock")]
class PrivacySettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.system.privacy.checkForUpdates"),
        description: new TM("settings.system.privacy.checkForUpdates.description"),
        envVar: 'bool:CHECK_FOR_UPDATES', envVarMode: EnvVarMode::OVERWRITE)]
    public bool $checkForUpdates = true;

    /**
     * @var bool Use gravatars for user avatars, when user has no own avatar defined
     */
    #[SettingsParameter(
        label: new TM("settings.system.privacy.useGravatar"),
        description: new TM("settings.system.privacy.useGravatar.description"),
        envVar: 'bool:USE_GRAVATAR', envVarMode: EnvVarMode::OVERWRITE)]
    public bool $useGravatar = false;
}