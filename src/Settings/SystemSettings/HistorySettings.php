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

use App\Form\History\EnforceEventCommentTypesType;
use App\Services\LogSystem\EventCommentType;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\EnumType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(label: new TM("settings.system.history"))]
class HistorySettings
{
    #[SettingsParameter(
        label: new TM("settings.system.history.saveChangedFields"),
        envVar: "bool:HISTORY_SAVE_CHANGED_FIELDS", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $saveChangedFields = true;

    #[SettingsParameter(
        label: new TM("settings.system.history.saveOldData"),
        envVar: "bool:HISTORY_SAVE_CHANGED_DATA", envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $saveOldData = true;

    #[SettingsParameter(
        label: new TM("settings.system.history.saveNewData"),
        envVar: "bool:HISTORY_SAVE_NEW_DATA", envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $saveNewData = true;

    #[SettingsParameter(
        label: new TM("settings.system.history.saveRemovedData"),
        envVar: "bool:HISTORY_SAVE_REMOVED_DATA", envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $saveRemovedData = true;

    /** @var EventCommentType[] */
    #[SettingsParameter(
        type: ArrayType::class,
        label: new TM("settings.system.history.enforceComments"),
        description: new TM("settings.system.history.enforceComments.description"),
        options: ['type' => EnumType::class, 'nullable' => false, 'options' => ['class' => EventCommentType::class]],
        formType: EnforceEventCommentTypesType::class,
        envVar: "ENFORCE_CHANGE_COMMENTS_FOR", envVarMode: EnvVarMode::OVERWRITE, envVarMapper: [self::class, 'mapEnforceComments']
    )]
    public array $enforceComments = [];

    public static function mapEnforceComments(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $explode = explode(',', $value);
        return array_map(fn(string $type) => EventCommentType::from($type), $explode);
    }
}