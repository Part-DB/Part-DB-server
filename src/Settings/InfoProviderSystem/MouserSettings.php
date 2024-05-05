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

use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings]
class MouserSettings
{
    #[SettingsParameter(envVar: "PROVIDER_MOUSER_KEY")]
    public ?string $apiKey = null;

    /** @var int The number of results to get from Mouser while searching (please note that this value is max 50) */
    #[SettingsParameter(envVar: "int:PROVIDER_MOUSER_SEARCH_LIMIT")]
    #[Assert\Range(min: 1, max: 50)]
    public int $searchLimit = 50;

    /** @var MouserSearchOptions Filter search results by RoHS compliance and stock availability */
    #[SettingsParameter(envVar: "PROVIDER_MOUSER_SEARCH_OPTION", envVarMapper: [self::class, "mapSearchOptionEnvVar"])]
    public MouserSearchOptions $searchOption = MouserSearchOptions::NONE;

    /** @var bool It is recommended to leave this set to 'true'. The option is not really documented by Mouser:
     * Used when searching for keywords in the language specified when you signed up for Search API. */
    #[SettingsParameter(envVar: "bool:PROVIDER_MOUSER_SEARCH_WITH_SIGNUP_LANGUAGE")]
    public bool $searchWithSignUpLanguage = true;

    public static function mapSearchOptionEnvVar(?string $value): MouserSearchOptions
    {
        if (!$value) {
            return MouserSearchOptions::NONE;
        }

        return MouserSearchOptions::tryFrom($value) ?? MouserSearchOptions::NONE;
    }

}