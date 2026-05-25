<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services\LogSystem;

use App\Services\LogSystem\LogLevelHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogLevelHelperTest extends TestCase
{
    private LogLevelHelper $service;

    protected function setUp(): void
    {
        $this->service = new LogLevelHelper();
    }

    public static function iconClassProvider(): \Generator
    {
        yield [LogLevel::DEBUG, 'fa-bug'];
        yield [LogLevel::INFO, 'fa-info'];
        yield [LogLevel::NOTICE, 'fa-flag'];
        yield [LogLevel::WARNING, 'fa-exclamation-circle'];
        yield [LogLevel::ERROR, 'fa-exclamation-triangle'];
        yield [LogLevel::CRITICAL, 'fa-bolt'];
        yield [LogLevel::ALERT, 'fa-radiation'];
        yield [LogLevel::EMERGENCY, 'fa-skull-crossbones'];
    }

    #[DataProvider('iconClassProvider')]
    public function testLogLevelToIconClass(string $logLevel, string $expectedIcon): void
    {
        $this->assertSame($expectedIcon, $this->service->logLevelToIconClass($logLevel));
    }

    public function testUnknownLogLevelReturnsDefaultIcon(): void
    {
        $this->assertSame('fa-question-circle', $this->service->logLevelToIconClass('unknown_level'));
    }

    public static function tableColorProvider(): \Generator
    {
        yield [LogLevel::EMERGENCY, 'table-danger'];
        yield [LogLevel::ALERT, 'table-danger'];
        yield [LogLevel::CRITICAL, 'table-danger'];
        yield [LogLevel::ERROR, 'table-danger'];
        yield [LogLevel::WARNING, 'table-warning'];
        yield [LogLevel::NOTICE, 'table-info'];
        yield [LogLevel::INFO, ''];
        yield [LogLevel::DEBUG, ''];
    }

    #[DataProvider('tableColorProvider')]
    public function testLogLevelToTableColorClass(string $logLevel, string $expectedClass): void
    {
        $this->assertSame($expectedClass, $this->service->logLevelToTableColorClass($logLevel));
    }

    public function testUnknownLogLevelReturnsEmptyColor(): void
    {
        $this->assertSame('', $this->service->logLevelToTableColorClass('unknown_level'));
    }
}
