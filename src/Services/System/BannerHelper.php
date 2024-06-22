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


namespace App\Services\System;

/**
 * Helper service to retrieve the banner of this Part-DB installation
 */
class BannerHelper
{
    public function __construct(private readonly string $project_dir, private readonly string $partdb_banner)
    {

    }

    /**
     * Retrieves the banner from either the env variable or the banner.md file.
     * @return string
     */
    public function getBanner(): string
    {
        $banner = $this->partdb_banner;
        if (!is_string($banner)) {
            throw new \RuntimeException('The parameter "partdb.banner" must be a string.');
        }
        if ($banner === '') {
            $banner_path = $this->project_dir
                .DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'banner.md';

            $tmp = file_get_contents($banner_path);
            if (false === $tmp) {
                throw new \RuntimeException('The banner file could not be read.');
            }
            $banner = $tmp;
        }

        return $banner;
    }
}