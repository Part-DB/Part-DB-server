<?php
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

declare(strict_types=1);


namespace App\Tests\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\PdfTextExtractor;
use Dompdf\Dompdf;
use PHPUnit\Framework\TestCase;

final class PdfTextExtractorTest extends TestCase
{
    private function createPdf(string $html): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    public function testExtractText(): void
    {
        $pdf = $this->createPdf('<p>BC547 NPN Transistor</p><p>Collector current 100 mA</p>'
            .'<div style="page-break-before: always">Package TO-92</div>');

        $text = (new PdfTextExtractor())->extractText($pdf);

        self::assertStringContainsString('--- Page 1 ---', $text);
        self::assertStringContainsString('BC547 NPN Transistor', $text);
        self::assertStringContainsString('Collector current 100 mA', $text);
        self::assertStringContainsString('--- Page 2 ---', $text);
        self::assertStringContainsString('Package TO-92', $text);
    }

    public function testExtractTextStopsAfterMaxLength(): void
    {
        $pdf = $this->createPdf('<p>First page</p><div style="page-break-before: always">Second page</div>');

        $text = (new PdfTextExtractor())->extractText($pdf, 5);

        self::assertStringContainsString('First page', $text);
        self::assertStringNotContainsString('Second page', $text);
    }

    public function testDocumentWithoutTextResultsInEmptyString(): void
    {
        $pdf = $this->createPdf('<div style="width: 10px; height: 10px; background: black"></div>');

        self::assertSame('', (new PdfTextExtractor())->extractText($pdf));
    }

    public function testInvalidDocumentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PdfTextExtractor())->extractText('This is not a PDF');
    }
}
