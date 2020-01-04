<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\Services\TFA;

use App\Services\TFA\BackupCodeGenerator;
use PHPUnit\Framework\TestCase;

class BackupCodeGeneratorTest extends TestCase
{
    /**
     * Test if an exception is thrown if you are using a too high code length.
     */
    public function testLengthUpperLimit()
    {
        $this->expectException(\RuntimeException::class);
        new BackupCodeGenerator(33, 10);
    }

    /**
     * Test if an exception is thrown if you are using a too high code length.
     */
    public function testLengthLowerLimit()
    {
        $this->expectException(\RuntimeException::class);
        new BackupCodeGenerator(4, 10);
    }

    public function codeLengthDataProvider()
    {
        return [[6], [8], [10], [16]];
    }

    /**
     * @dataProvider  codeLengthDataProvider
     */
    public function testGenerateSingleCode(int $code_length)
    {
        $generator = new BackupCodeGenerator($code_length, 10);
        $this->assertRegExp("/^([a-f0-9]){{$code_length}}\$/", $generator->generateSingleCode());
    }

    public function codeCountDataProvider()
    {
        return [[2], [8], [10]];
    }

    /**
     * @dataProvider codeCountDataProvider
     */
    public function testGenerateCodeSet(int $code_count)
    {
        $generator = new BackupCodeGenerator(8, $code_count);
        $this->assertCount($code_count, $generator->generateCodeSet());
    }
}
