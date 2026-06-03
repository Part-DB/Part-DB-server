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

use App\Entity\LogSystem\AbstractLogEntry;
use App\Services\LogSystem\LogDataFormatter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LogDataFormatterTest extends WebTestCase
{
    private static LogDataFormatter $service;
    private static AbstractLogEntry $dummyLog;
    private AbstractLogEntry $dummy;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$service = self::getContainer()->get(LogDataFormatter::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // A mock is fine: $logEntry is only consulted for @id (foreign key) arrays
        $this->dummy = $this->createMock(AbstractLogEntry::class);
    }

    public function testStringIsWrappedInQuoteSpans(): void
    {
        $result = self::$service->formatData('hello', $this->dummy, 'name');
        $this->assertStringContainsString('"', $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testStringSpecialCharsAreEscaped(): void
    {
        $result = self::$service->formatData('<script>', $this->dummy, 'name');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testNewlineInStringRendersAsSpan(): void
    {
        $result = self::$service->formatData("line1\nline2", $this->dummy, 'name');
        $this->assertStringContainsString('\\n', $result);
    }

    public function testBoolTrueFormatsAsString(): void
    {
        $result = self::$service->formatData(true, $this->dummy, 'enabled');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testBoolFalseFormatsAsString(): void
    {
        $result = self::$service->formatData(false, $this->dummy, 'enabled');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testBoolTrueAndFalseProduceDifferentOutput(): void
    {
        $true = self::$service->formatData(true, $this->dummy, 'enabled');
        $false = self::$service->formatData(false, $this->dummy, 'enabled');
        $this->assertNotSame($true, $false);
    }

    public function testIntegerFormatsToItsStringRepresentation(): void
    {
        $result = self::$service->formatData(42, $this->dummy, 'count');
        $this->assertSame('42', $result);
    }

    public function testFloatFormatsToItsStringRepresentation(): void
    {
        $result = self::$service->formatData(3.14, $this->dummy, 'price');
        $this->assertSame('3.14', $result);
    }

    public function testNullFormatsAsItalicNull(): void
    {
        $result = self::$service->formatData(null, $this->dummy, 'field');
        $this->assertSame('<i>null</i>', $result);
    }

    public function testDateTimeArrayFormatsToDateString(): void
    {
        $data = [
            'date' => '2024-01-15 10:30:00.000000',
            'timezone_type' => 3,
            'timezone' => 'UTC',
        ];
        $result = self::$service->formatData($data, $this->dummy, 'created_at');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        // Should not be the JSON fallback
        $this->assertStringNotContainsString('json-formatter', $result);
    }

    public function testPlainArrayFormatsAsJsonDiv(): void
    {
        $result = self::$service->formatData(['key' => 'value', 'num' => 1], $this->dummy, 'tags');
        $this->assertStringContainsString('json-formatter', $result);
    }

    public function testUnsupportedTypeThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        self::$service->formatData(new \stdClass(), $this->dummy, 'field');
    }
}
