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

namespace App\Tests\Services\InfoProviderSystem\DTOs;

use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use PHPUnit\Framework\TestCase;

class SearchResultDTOTest extends TestCase
{
    public function testPreviewImageURL(): void
    {
        //For null preview_image_url, the url and file dto should be null
        $searchResultDTO = new SearchResultDTO(
            'provider_key',
            'provider_id',
            'name',
            'description'
        );
        $this->assertNull($searchResultDTO->preview_image_url);
        $this->assertNull($searchResultDTO->preview_image_file);

        //If a value is passed then the url and file dto should be the same
        $searchResultDTO = new SearchResultDTO(
            'provider_key',
            'provider_id',
            'name',
            'description',
            preview_image_url: 'https://invalid.com/preview_image_url.jpg'
        );
        $this->assertEquals('https://invalid.com/preview_image_url.jpg', $searchResultDTO->preview_image_url);
        $this->assertEquals('https://invalid.com/preview_image_url.jpg', $searchResultDTO->preview_image_file->url);

        //Invalid url characters should be replaced with their URL encoded version (similar to FileDTO)
        $searchResultDTO = new SearchResultDTO(
            'provider_key',
            'provider_id',
            'name',
            'description',
            preview_image_url: 'https://invalid.com/preview_image^url.jpg?param1=1&param2=2'
        );
        $this->assertEquals('https://invalid.com/preview_image%5Eurl.jpg?param1=1&param2=2', $searchResultDTO->preview_image_url);
        $this->assertEquals('https://invalid.com/preview_image%5Eurl.jpg?param1=1&param2=2', $searchResultDTO->preview_image_file->url);
    }
}
