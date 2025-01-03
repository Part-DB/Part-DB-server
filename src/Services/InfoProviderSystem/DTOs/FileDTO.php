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


namespace App\Services\InfoProviderSystem\DTOs;

/**
 * This DTO represents a file that can be downloaded from a URL.
 * This could be a datasheet, a 3D model, a picture or similar.
 * @see \App\Tests\Services\InfoProviderSystem\DTOs\FileDTOTest
 */
class FileDTO
{
    /**
     * @var string The URL where to get this file
     */
    public readonly string $url;

    /**
     * @param  string  $url The URL where to get this file
     * @param  string|null  $name Optionally the name of this file
     */
    public function __construct(
        string $url,
        public readonly ?string $name = null,
    ) {
        //Find all occurrences of non URL safe characters and replace them with their URL encoded version.
        //We only want to replace characters which can not have a valid meaning in a URL (what would break the URL).
        //Digikey provided some wrong URLs with a ^ in them, which is not a valid URL character. (https://github.com/Part-DB/Part-DB-server/issues/521)
        $this->url = preg_replace_callback('/[^a-zA-Z0-9_\-.$+!*();\/?:@=&#%]/', static fn($matches) => rawurlencode($matches[0]), $url);
    }


}