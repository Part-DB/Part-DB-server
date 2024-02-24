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

use App\Services\InfoProviderSystem\DTOs\FileDTO;
use PHPUnit\Framework\TestCase;

class FileDTOTest extends TestCase
{


    public static function escapingDataProvider(): array
    {
        return [
            //Normal URLs must be unchanged, even if they contain special characters
            ["https://localhost:8000/en/part/1335/edit#attachments", "https://localhost:8000/en/part/1335/edit#attachments"],
            ["https://localhost:8000/en/part/1335/edit?test=%20%20&sfee_aswer=test-223!*()", "https://localhost:8000/en/part/1335/edit?test=%20%20&sfee_aswer=test-223!*()"],

            //Remaining URL unsafe characters must be escaped
            ["test%5Ese", "test^se"],
            ["test+se", "test se"],
            ["test%7Cse", "test|se"],
        ];
    }

    /**
     * @dataProvider escapingDataProvider
     */
    public function testURLEscaping(string $expected, string $input): void
    {
        $fileDTO = new FileDTO( $input);
        self::assertSame($expected, $fileDTO->url);
    }
}
