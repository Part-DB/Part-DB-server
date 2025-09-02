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


namespace App\Settings\BehaviorSettings;

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(name: "part_info", label: new TM("settings.behavior.part_info"))]
#[SettingsIcon('fa-circle-info')]
class PartInfoSettings
{
    /**
     * Whether to show the part image overlays in the part info view
     * @var bool
     */
    #[SettingsParameter(label: new TM("settings.behavior.part_info.show_part_image_overlay"), description: new TM("settings.behavior.part_info.show_part_image_overlay.help"),
    envVar: "bool:SHOW_PART_IMAGE_OVERLAY", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $showPartImageOverlay = true;
}