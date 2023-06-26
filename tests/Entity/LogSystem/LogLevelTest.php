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

namespace App\Tests\Entity\LogSystem;

use App\Entity\LogSystem\LogLevel;
use PHPUnit\Framework\TestCase;

class LogLevelTest extends TestCase
{

    public function testToPSR3LevelString(): void
    {
        $this->assertSame('debug', LogLevel::DEBUG->toPSR3LevelString());
        $this->assertSame('info', LogLevel::INFO->toPSR3LevelString());
        $this->assertSame('notice', LogLevel::NOTICE->toPSR3LevelString());
        $this->assertSame('warning', LogLevel::WARNING->toPSR3LevelString());
        $this->assertSame('error', LogLevel::ERROR->toPSR3LevelString());
        $this->assertSame('critical', LogLevel::CRITICAL->toPSR3LevelString());
        $this->assertSame('alert', LogLevel::ALERT->toPSR3LevelString());
        $this->assertSame('emergency', LogLevel::EMERGENCY->toPSR3LevelString());
    }

    public function testFromPSR3LevelString(): void
    {
        $this->assertSame(LogLevel::DEBUG, LogLevel::fromPSR3LevelString('debug'));
        $this->assertSame(LogLevel::INFO, LogLevel::fromPSR3LevelString('info'));
        $this->assertSame(LogLevel::NOTICE, LogLevel::fromPSR3LevelString('notice'));
        $this->assertSame(LogLevel::WARNING, LogLevel::fromPSR3LevelString('warning'));
        $this->assertSame(LogLevel::ERROR, LogLevel::fromPSR3LevelString('error'));
        $this->assertSame(LogLevel::CRITICAL, LogLevel::fromPSR3LevelString('critical'));
        $this->assertSame(LogLevel::ALERT, LogLevel::fromPSR3LevelString('alert'));
        $this->assertSame(LogLevel::EMERGENCY, LogLevel::fromPSR3LevelString('emergency'));
    }

    public function testMoreImportOrEqualThan(): void
    {
        $this->assertTrue(LogLevel::DEBUG->moreImportOrEqualThan(LogLevel::DEBUG));
        $this->assertFalse(LogLevel::DEBUG->moreImportOrEqualThan(LogLevel::INFO));
        $this->assertFalse(LogLevel::DEBUG->moreImportOrEqualThan(LogLevel::NOTICE));
        $this->assertTrue(LogLevel::EMERGENCY->moreImportOrEqualThan(LogLevel::DEBUG));
    }

    public function testMoreImportThan(): void
    {
        $this->assertFalse(LogLevel::DEBUG->moreImportThan(LogLevel::DEBUG));
        $this->assertFalse(LogLevel::DEBUG->moreImportThan(LogLevel::INFO));
        $this->assertFalse(LogLevel::DEBUG->moreImportThan(LogLevel::NOTICE));
        $this->assertTrue(LogLevel::EMERGENCY->moreImportThan(LogLevel::DEBUG));
    }

    public function testLessImportThan(): void
    {
        $this->assertFalse(LogLevel::DEBUG->lessImportThan(LogLevel::DEBUG));
        $this->assertTrue(LogLevel::DEBUG->lessImportThan(LogLevel::INFO));
        $this->assertTrue(LogLevel::DEBUG->lessImportThan(LogLevel::NOTICE));
        $this->assertFalse(LogLevel::EMERGENCY->lessImportThan(LogLevel::DEBUG));
    }

    public function testLessImportOrEqualThan(): void
    {
        $this->assertTrue(LogLevel::DEBUG->lessImportOrEqualThan(LogLevel::DEBUG));
        $this->assertTrue(LogLevel::DEBUG->lessImportOrEqualThan(LogLevel::INFO));
        $this->assertTrue(LogLevel::DEBUG->lessImportOrEqualThan(LogLevel::NOTICE));
        $this->assertFalse(LogLevel::EMERGENCY->lessImportOrEqualThan(LogLevel::DEBUG));
    }
}
