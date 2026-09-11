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

use App\Services\LogSystem\LogDiffFormatter;
use PHPUnit\Framework\TestCase;

final class LogDiffFormatterTest extends TestCase
{
    private LogDiffFormatter $service;

    protected function setUp(): void
    {
        $this->service = new LogDiffFormatter();
    }

    public function testPositiveNumericDiff(): void
    {
        $result = $this->service->formatDiff(1, 6);
        $this->assertStringContainsString('text-success', $result);
        $this->assertStringContainsString('+5', $result);
    }

    public function testNegativeNumericDiff(): void
    {
        $result = $this->service->formatDiff(10, 3);
        $this->assertStringContainsString('text-danger', $result);
        $this->assertStringContainsString('-7', $result);
    }

    public function testZeroNumericDiff(): void
    {
        $result = $this->service->formatDiff(5, 5);
        $this->assertStringContainsString('text-muted', $result);
        $this->assertStringContainsString('0', $result);
    }

    public function testStringDiffReturnsNonEmptyHtml(): void
    {
        $result = $this->service->formatDiff('hello world', 'hello PHP');
        $this->assertNotEmpty($result);
        // DiffHelper returns HTML
        $this->assertStringContainsString('<', $result);
    }

    public function testUnsupportedTypesReturnEmptyString(): void
    {
        // booleans are neither string nor numeric → empty
        $result = $this->service->formatDiff(true, false);
        $this->assertSame('', $result);
    }

    public function testFloatDiff(): void
    {
        $result = $this->service->formatDiff(1.5, 3.0);
        $this->assertStringContainsString('text-success', $result);
    }
}
