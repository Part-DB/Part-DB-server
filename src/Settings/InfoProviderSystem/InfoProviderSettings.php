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

use Jbtronics\SettingsBundle\Settings\EmbeddedSettings;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;

#[Settings()]
class InfoProviderSettings
{
    use SettingsTrait;

    #[EmbeddedSettings]
    public ?InfoProviderGeneralSettings $general = null;

    #[EmbeddedSettings]
    public ?DigikeySettings $digikey = null;

    #[EmbeddedSettings]
    public ?MouserSettings $mouser = null;

    #[EmbeddedSettings]
    public ?TMESettings $tme = null;

    #[EmbeddedSettings]
    public ?Element14Settings $element14 = null;

    #[EmbeddedSettings]
    public ?OctopartSettings $octopartSettings = null;

    #[EmbeddedSettings]
    public ?LCSCSettings $lcsc = null;

    #[EmbeddedSettings]
    public ?OEMSecretsSettings $oemsecrets = null;

    #[EmbeddedSettings]
    public ?ReicheltSettings $reichelt = null;

    #[EmbeddedSettings]
    public ?PollinSettings $pollin = null;
}
