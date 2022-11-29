<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Services\UserSystem\TFA;

use App\Services\UserSystem\TFA\BackupCodeGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BackupCodeGeneratorTest extends TestCase
{
    /**
     * Test if an exception is thrown if you are using a too high code length.
     */
    public function testLengthUpperLimit(): void
    {
        $this->expectException(RuntimeException::class);
        new BackupCodeGenerator(33, 10);
    }

    /**
     * Test if an exception is thrown if you are using a too high code length.
     */
    public function testLengthLowerLimit(): void
    {
        $this->expectException(RuntimeException::class);
        new BackupCodeGenerator(4, 10);
    }

    public function codeLengthDataProvider(): array
    {
        return [[6], [8], [10], [16]];
    }

    /**
     * @dataProvider  codeLengthDataProvider
     */
    public function testGenerateSingleCode(int $code_length): void
    {
        $generator = new BackupCodeGenerator($code_length, 10);
        $this->assertMatchesRegularExpression("/^([a-f0-9]){{$code_length}}\$/", $generator->generateSingleCode());
    }

    public function codeCountDataProvider(): array
    {
        return [[2], [8], [10]];
    }

    /**
     * @dataProvider codeCountDataProvider
     */
    public function testGenerateCodeSet(int $code_count): void
    {
        $generator = new BackupCodeGenerator(8, $code_count);
        $this->assertCount($code_count, $generator->generateCodeSet());
    }
}
