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


namespace App\Settings;

use App\Settings\SystemSettings\AttachmentsSettings;
use App\Settings\SystemSettings\CustomizationSettings;
use App\Settings\SystemSettings\HistorySettings;
use App\Settings\SystemSettings\PrivacySettings;
use Jbtronics\SettingsBundle\Settings\EmbeddedSettings;
use Jbtronics\SettingsBundle\Settings\Settings;

#[Settings]
class SystemSettings
{
    #[EmbeddedSettings()]
    public ?CustomizationSettings $customization = null;

    #[EmbeddedSettings()]
    public ?PrivacySettings $privacy = null;

    #[EmbeddedSettings()]
    public ?AttachmentsSettings $attachments = null;

    #[EmbeddedSettings()]
    public ?HistorySettings $history = null;
}