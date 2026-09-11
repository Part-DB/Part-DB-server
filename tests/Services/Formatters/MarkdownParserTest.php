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

namespace App\Tests\Services\Formatters;

use App\Services\Formatters\MarkdownParser;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MarkdownParserTest extends TestCase
{
    private MarkdownParser $service;

    protected function setUp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Loading...');
        $this->service = new MarkdownParser($translator);
    }

    public function testOutputContainsDataMarkdownAttribute(): void
    {
        $result = $this->service->markForRendering('**hello**');
        $this->assertStringContainsString('data-markdown=', $result);
        $this->assertStringContainsString('data-controller="common--markdown"', $result);
    }

    public function testMarkdownContentIsHtmlescapedInAttribute(): void
    {
        $result = $this->service->markForRendering('<script>alert(1)</script>');
        // The raw < should not appear unescaped inside the attribute
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testInlineModeAddsInlineClass(): void
    {
        $result = $this->service->markForRendering('text', true);
        $this->assertStringContainsString('markdown-inline', $result);
    }

    public function testNonInlineModeDoesNotAddInlineClass(): void
    {
        $result = $this->service->markForRendering('text', false);
        $this->assertStringNotContainsString('markdown-inline', $result);
    }

    public function testOutputIsWrappedInDiv(): void
    {
        $result = $this->service->markForRendering('test');
        $this->assertStringStartsWith('<div', $result);
        $this->assertStringEndsWith('</div>', $result);
    }

    public function testTranslatorIsCalledForLoadingText(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('markdown.loading')
            ->willReturn('Loading...');

        $service = new MarkdownParser($translator);
        $service->markForRendering('test');
    }
}
