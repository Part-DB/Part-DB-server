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
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(name: "search", label: new TM("settings.behavior.search"))]
#[SettingsIcon('fa-magnifying-glass')]
class SearchSettings
{
    /**
     * Whether to enable advanced search
     * @var bool
     */
    #[SettingsParameter(
        label: new TM("settings.behavior.search.enable_advanced_search"),
        description: new TM("settings.behavior.search.enable_advanced_search.help"),
        envVar: "bool:ENABLE_ADVANCED_SEARCH",
        envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $enableAdvancedSearch = false;

    /**
     * Defines the maximum number of tokens the keyword can be split into
     * @var int
     */
    #[SettingsParameter(
        label: new TM("settings.behavior.search.token_limit"),
        description: new TM("settings.behavior.search.token_limit.help"),
        envVar: "int:SEARCH_TOKEN_LIMIT",
        envVarMode: EnvVarMode::OVERWRITE,
        formOptions: ['attr' => ['min' => 2, 'max' => 5]],
    )]
    #[Assert\Range(min: 2, max: 5)]
    public int $searchTokenLimit = 3;

    /**
     * Whether to escape sql wildcards
     * @var bool
     */
    #[SettingsParameter(
        label: new TM("settings.behavior.search.escape_sql_wildcards"),
        description: new TM("settings.behavior.search.escape_sql_wildcards.help"),
        envVar: "bool:ESCAPE_SQL_WILDCARDS",
        envVarMode: EnvVarMode::OVERWRITE
    )]
    public bool $escapeSQLWildcards = true;
}
