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

namespace App\Tests\Services\InfoProviderSystem\DTOs;

use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO;
use PHPUnit\Framework\TestCase;

class BulkSearchPartResultsDTOTest extends TestCase
{

    public function testHasErrors(): void
    {
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [], []);
        $this->assertFalse($test->hasErrors());
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [], ['error1']);
        $this->assertTrue($test->hasErrors());
    }

    public function testGetErrorCount(): void
    {
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [], []);
        $this->assertCount(0, $test->errors);
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [], ['error1', 'error2']);
        $this->assertCount(2, $test->errors);
    }

    public function testHasResults(): void
    {
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [], []);
        $this->assertFalse($test->hasResults());
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [ $this->createMock(\App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO::class) ], []);
        $this->assertTrue($test->hasResults());
    }

    public function testGetResultCount(): void
    {
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [], []);
        $this->assertCount(0, $test->searchResults);
        $test = new BulkSearchPartResultsDTO($this->createMock(\App\Entity\Parts\Part::class), [
            $this->createMock(\App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO::class),
            $this->createMock(\App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO::class)
        ], []);
        $this->assertCount(2, $test->searchResults);
    }
}
