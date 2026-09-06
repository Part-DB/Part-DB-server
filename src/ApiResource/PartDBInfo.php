<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\ApiResource;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\State\PartDBInfoProvider;

/**
 * This class is used to provide various information about the system.
 */
#[ApiResource(
    uriTemplate: '/info.{_format}',
    description: 'Basic information about Part-DB like version, title, etc.',
    operations: [new Get(openapi: new Operation(summary: 'Get basic information about the installed Part-DB instance.'))],
    provider: PartDBInfoProvider::class
)]
#[ApiFilter(PropertyFilter::class)]
readonly class PartDBInfo
{
    public function __construct(
        /** The installed Part-DB version */
        public string $version,
        /** The Git branch name of the Part-DB version (or null, if not installed via git) */
        public string|null $git_branch,
        /** The Git branch commit of the Part-DB version (or null, if not installed via git) */
        public string|null $git_commit,
        /** The name of this Part-DB instance */
        public string $title,
        /** The banner, shown on homepage (markdown encoded) */
        public string $banner,
        /** The configured default URI for Part-DB */
        public string $default_uri,
        /** The global timezone of this Part-DB */
        public string $global_timezone,
        /** The base currency of Part-DB, used as internal representation of monetary values */
        public string $base_currency,
        /** The configured default language of Part-DB */
        public string $global_locale,
    ) {

    }
}
