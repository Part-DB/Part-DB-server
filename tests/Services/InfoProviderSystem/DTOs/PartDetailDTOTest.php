<?php

declare(strict_types=1);

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

use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use PHPUnit\Framework\TestCase;

final class PartDetailDTOTest extends TestCase
{
    private static function dtoWithFiles(?array $datasheets, ?array $images): PartDetailDTO
    {
        return new PartDetailDTO(
            provider_key: 'test',
            provider_id: '1234',
            name: 'Test part',
            description: 'A part',
            datasheets: $datasheets,
            images: $images,
        );
    }

    public function testNoFilesGivesEmptyList(): void
    {
        $this->assertSame([], self::dtoWithFiles(null, null)->getNonDownloadableFileUrls());
    }

    public function testOnlyDownloadableFilesGivesEmptyList(): void
    {
        $dto = self::dtoWithFiles(
            [new FileDTO('https://example.com/datasheet.pdf')],
            [new FileDTO('https://example.com/image.png')]
        );

        $this->assertSame([], $dto->getNonDownloadableFileUrls());
    }

    public function testNonDownloadableFilesAreCollectedFromDatasheetsAndImages(): void
    {
        $dto = self::dtoWithFiles(
            [
                new FileDTO('https://example.com/datasheet.pdf'),
                new FileDTO('https://example.com/redirect?id=1', downloadable: false),
            ],
            [new FileDTO('https://example.com/redirect?id=2', downloadable: false)]
        );

        $this->assertSame([
            'https://example.com/redirect?id=1',
            'https://example.com/redirect?id=2',
        ], $dto->getNonDownloadableFileUrls());
    }

    public function testDuplicateUrlsAreReturnedOnce(): void
    {
        //The same file can be listed as datasheet and image, but the form only needs the URL once
        $dto = self::dtoWithFiles(
            [new FileDTO('https://example.com/redirect?id=1', downloadable: false)],
            [new FileDTO('https://example.com/redirect?id=1', downloadable: false)]
        );

        $this->assertSame(['https://example.com/redirect?id=1'], $dto->getNonDownloadableFileUrls());
    }
}
