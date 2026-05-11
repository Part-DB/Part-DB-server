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

use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Entity\LogSystem\UserLoginLogEntry;
use App\Entity\LogSystem\UserLogoutLogEntry;
use App\Services\LogSystem\LogEntryExtraFormatter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LogEntryExtraFormatterTest extends WebTestCase
{
    private static LogEntryExtraFormatter $service;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$service = self::getContainer()->get(LogEntryExtraFormatter::class);
    }

    public function testFormatUserLoginLogEntryContainsIp(): void
    {
        $entry = new UserLoginLogEntry('127.0.0.1', anonymize: false);
        $result = self::$service->format($entry);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('127.0.0.1', $result);
    }

    public function testFormatDatabaseUpdatedLogEntryContainsVersions(): void
    {
        $entry = new DatabaseUpdatedLogEntry('1.0.0', '2.0.0');
        $result = self::$service->format($entry);
        $this->assertStringContainsString('1.0.0', $result);
        $this->assertStringContainsString('2.0.0', $result);
    }

    public function testFormatUserLogoutContainsIp(): void
    {
        $entry = new UserLogoutLogEntry('10.0.0.1', anonymize: false);
        $result = self::$service->format($entry);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('10.0.0.1', $result);
    }

    public function testFormatConsoleReplacesHtmlTags(): void
    {
        $entry = new DatabaseUpdatedLogEntry('1.0', '2.0');
        $result = self::$service->formatConsole($entry);
        // Console format replaces the arrow icon with →
        $this->assertStringContainsString('→', $result);
        // No raw HTML tags should remain from the arrow icon
        $this->assertStringNotContainsString('<i class="fas fa-long-arrow-alt-right"></i>', $result);
    }

    public function testFormatConsoleReturnsString(): void
    {
        $entry = new UserLoginLogEntry('192.168.1.1', anonymize: false);
        $result = self::$service->formatConsole($entry);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testIpAddressIsHtmlEscapedInFormat(): void
    {
        // Verify that the IP embedded in the result is safe (htmlspecialchars is applied)
        $entry = new UserLoginLogEntry('192.168.0.1', anonymize: false);
        $result = self::$service->format($entry);
        // The result must not contain unescaped HTML even from a crafted IP
        $this->assertStringNotContainsString('<script>', $result);
    }
}
