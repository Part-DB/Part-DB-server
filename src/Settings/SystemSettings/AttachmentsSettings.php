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

use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.system.attachments"))]
class AttachmentsSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.system.attachments.maxFileSize"),
        description: new TM("settings.system.attachments.maxFileSize.help"),
        envVar: "MAX_ATTACHMENT_FILE_SIZE", envVarMode: EnvVarMode::OVERWRITE
    )]
    #[Assert\Regex("/^([1-9][0-9]*)([KMG])?$/", message: "validator.fileSize.invalidFormat")]
    public string $maxFileSize = '100M';

    #[SettingsParameter(
        label: new TM("settings.system.attachments.allowDownloads"),
        description: new TM("settings.system.attachments.allowDownloads.help"),
        formOptions: ['help_html' => true],
        envVar: "bool:ALLOW_ATTACHMENT_DOWNLOADS", envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $allowDownloads = false;

    #[SettingsParameter(
        label: new TM("settings.system.attachments.downloadByDefault"),
        envVar: "bool:ATTACHMENT_DOWNLOAD_BY_DEFAULT", envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $downloadByDefault = false;
}