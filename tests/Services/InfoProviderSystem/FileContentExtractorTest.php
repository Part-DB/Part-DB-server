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

use App\Services\InfoProviderSystem\FileContentExtractor;
use Dompdf\Dompdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @see FileContentExtractor
 */
final class FileContentExtractorTest extends TestCase
{
    private FileContentExtractor $extractor;

    /** @var string[] Temporary files created by the test, which are removed afterwards */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->extractor = new FileContentExtractor();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
        $this->tempFiles = [];
    }

    private function createPdf(string $html): string
    {
        $dompdf = new Dompdf();
        //DejaVu Sans is bundled with dompdf and covers the symbols used in datasheets (Ω, µ, °, ...)
        $dompdf->loadHtml('<html><body style="font-family: DejaVu Sans">'.$html.'</body></html>', 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    private function createFile(string $content, string $extension): File
    {
        $path = tempnam(sys_get_temp_dir(), 'partdb_content_extractor_');
        //The extension is irrelevant for the detection (the MIME type is guessed from the content), but realistic
        rename($path, $path .= '.'.$extension);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new File($path);
    }

    private function page(string $html): string
    {
        return '<div style="page-break-before: always">'.$html.'</div>';
    }

    // --- extractPDFText() ---

    public function testExtractText(): void
    {
        $pdf = $this->createPdf('<p>BC547 NPN Transistor</p><p>Collector current 100 mA</p>'
            .$this->page('Package TO-92'));

        $text = $this->extractor->extractPDFText($pdf);

        self::assertStringContainsString('--- Page 1 ---', $text);
        self::assertStringContainsString('BC547 NPN Transistor', $text);
        self::assertStringContainsString('Collector current 100 mA', $text);
        self::assertStringContainsString('--- Page 2 ---', $text);
        self::assertStringContainsString('Package TO-92', $text);
        //The pages are in document order
        self::assertLessThan(strpos($text, '--- Page 2 ---'), strpos($text, 'BC547 NPN Transistor'));
    }

    public function testExtractTextKeepsUnitSymbols(): void
    {
        //Datasheets are full of these, losing them would change the meaning of the values
        $pdf = $this->createPdf('<p>Resistance 10 kΩ, leakage 100 µA at 25 °C, tolerance ±1 %</p>');

        $text = $this->extractor->extractPDFText($pdf);

        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
        foreach (['10 kΩ', '100 µA', '25 °C', '±1 %'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
    }

    public function testEmptyPagesAreSkippedButKeepTheirNumber(): void
    {
        //The page markers must name the real page, so that the numbers are still right after an empty page
        $pdf = $this->createPdf('<p>Page one</p>'.$this->page('&nbsp;').$this->page('Page three'));

        $text = $this->extractor->extractPDFText($pdf);

        self::assertStringContainsString('--- Page 1 ---', $text);
        self::assertStringNotContainsString('--- Page 2 ---', $text);
        self::assertStringContainsString("--- Page 3 ---\nPage three", $text);
    }

    public function testExtractTextStopsAfterMaxLength(): void
    {
        $pdf = $this->createPdf('<p>First page</p>'.$this->page('Second page'));

        $text = $this->extractor->extractPDFText($pdf, 5);

        self::assertStringContainsString('First page', $text);
        self::assertStringNotContainsString('Second page', $text);
    }

    public function testMaxLengthOnlyStopsAfterTheLimitIsReached(): void
    {
        $pdf = $this->createPdf('<p>First page</p>'.$this->page('Second page').$this->page('Third page'));

        //Long enough for the first page, but not for the second one: that one is still read completely
        $firstPageOnly = $this->extractor->extractPDFText($pdf);
        $firstPageOnly = substr($firstPageOnly, 0, strpos($firstPageOnly, '--- Page 2 ---'));
        $text = $this->extractor->extractPDFText($pdf, mb_strlen($firstPageOnly) + 1);

        self::assertStringContainsString('Second page', $text);
        self::assertStringNotContainsString('Third page', $text);
    }

    public function testNoMaxLengthReadsAllPages(): void
    {
        $html = '<p>Page 1 content</p>';
        for ($i = 2; $i <= 20; $i++) {
            $html .= $this->page("Page $i content");
        }

        $text = $this->extractor->extractPDFText($this->createPdf($html));

        self::assertStringContainsString('--- Page 20 ---', $text);
        self::assertStringContainsString('Page 20 content', $text);
    }

    public function testDocumentWithoutTextResultsInEmptyString(): void
    {
        $pdf = $this->createPdf('<div style="width: 10px; height: 10px; background: black"></div>');

        self::assertSame('', $this->extractor->extractPDFText($pdf));
    }

    public static function invalidPdfProvider(): iterable
    {
        yield 'not a PDF at all' => ['This is not a PDF'];
        yield 'empty' => [''];
        yield 'only the header' => ["%PDF-1.7\n"];
    }

    #[DataProvider('invalidPdfProvider')]
    public function testInvalidDocumentThrows(string $content): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The PDF document could not be parsed');
        $this->extractor->extractPDFText($content);
    }

    public function testTruncatedDocumentThrowsOrReturnsText(): void
    {
        //A download which broke off: whatever the parser makes of it, it must not end in an uncaught error
        $pdf = $this->createPdf('<p>BC547 NPN Transistor</p>');
        $truncated = substr($pdf, 0, intdiv(strlen($pdf), 2));

        try {
            self::assertIsString($this->extractor->extractPDFText($truncated));
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('The PDF document could not be parsed', $e->getMessage());
        }
    }

    // --- extractContent() ---

    public function testExtractContentOfPdf(): void
    {
        $file = $this->createFile($this->createPdf('<p>BC547 NPN Transistor</p>'), 'pdf');

        $text = $this->extractor->extractContent($file);

        self::assertStringContainsString('--- Page 1 ---', $text);
        self::assertStringContainsString('BC547 NPN Transistor', $text);
    }

    public function testExtractContentPassesMaxLengthToPdf(): void
    {
        $file = $this->createFile($this->createPdf('<p>First page</p>'.$this->page('Second page')), 'pdf');

        $text = $this->extractor->extractContent($file, 5);

        self::assertStringContainsString('First page', $text);
        self::assertStringNotContainsString('Second page', $text);
    }

    public function testPdfIsDetectedByContentNotByExtension(): void
    {
        $file = $this->createFile($this->createPdf('<p>BC547 NPN Transistor</p>'), 'txt');

        //Read as text, this would be the raw PDF syntax
        $text = $this->extractor->extractContent($file);

        self::assertStringStartsWith('--- Page 1 ---', $text);
        self::assertStringNotContainsString('%PDF', $text);
    }

    public function testDamagedPdfThrows(): void
    {
        //Detected as PDF by its header, but not parseable
        $file = $this->createFile("%PDF-1.7\n".str_repeat('garbage ', 100), 'pdf');

        $this->expectException(\RuntimeException::class);
        $this->extractor->extractContent($file);
    }

    public function testExtractContentOfTextFile(): void
    {
        $content = "BC547 NPN Transistor\nCollector current: 100 mA\nPackage: TO-92";
        $file = $this->createFile($content, 'txt');

        self::assertSame($content, $this->extractor->extractContent($file));
    }

    public function testExtractContentOfMarkdownFile(): void
    {
        $content = "# BC547\n\n| Parameter | Value |\n|---|---|\n| I_C | 100 mA |\n\n* Package: **TO-92**";
        $file = $this->createFile($content, 'md');

        //Markdown is passed on as it is, its syntax is understood by the model
        self::assertSame($content, $this->extractor->extractContent($file));
    }

    public function testExtractContentOfTextFileKeepsUnicode(): void
    {
        $content = 'Resistance 10 kΩ, leakage 100 µA at 25 °C';
        $file = $this->createFile($content, 'txt');

        self::assertSame($content, $this->extractor->extractContent($file));
    }

    public function testTextFileInLegacyEncodingIsConvertedToUtf8(): void
    {
        //Text files exported by older (Windows) tools are often not UTF-8. Invalid UTF-8 can not be put into the
        //JSON request to the AI platform, so the request would fail.
        $file = $this->createFile(mb_convert_encoding('Temperatur: 25 °C, Größe: 5 µm', 'Windows-1252', 'UTF-8'), 'txt');

        $text = $this->extractor->extractContent($file);

        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
        self::assertSame('Temperatur: 25 °C, Größe: 5 µm', $text);
    }

    public function testTextFileWithByteOrderMark(): void
    {
        $file = $this->createFile("\u{FEFF}BC547 NPN Transistor", 'txt');

        self::assertSame('BC547 NPN Transistor', $this->extractor->extractContent($file));
    }

    public function testWhitespaceOnlyTextFileResultsInEmptyString(): void
    {
        //The controller rejects files without content by checking for an empty string, as for PDFs without text
        $file = $this->createFile("  \n\n\t  \n", 'txt');

        self::assertSame('', $this->extractor->extractContent($file));
    }

    public function testTextFileIsTrimmed(): void
    {
        $file = $this->createFile("\n\n  BC547 NPN Transistor  \n\n", 'txt');

        self::assertSame('BC547 NPN Transistor', $this->extractor->extractContent($file));
    }

    public function testMaxLengthAppliesToTextFiles(): void
    {
        //As for PDFs, the limit keeps huge files from being held in memory and in the cache completely
        $file = $this->createFile(str_repeat('0123456789', 1000), 'txt');

        $text = $this->extractor->extractContent($file, 25);

        self::assertSame(str_repeat('0123456789', 2).'01234', $text);
    }

    public function testMaxLengthCountsCharactersNotBytes(): void
    {
        $file = $this->createFile(str_repeat('Ω', 100), 'txt');

        $text = $this->extractor->extractContent($file, 10);

        self::assertSame(str_repeat('Ω', 10), $text);
        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    public static function unsupportedFileProvider(): iterable
    {
        //A minimal PNG (1x1 pixel)
        yield 'image' => [base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='), 'png'];
        yield 'zip archive' => ["PK\x03\x04".str_repeat("\0", 26), 'zip'];
        yield 'binary data' => [random_bytes(512)."\0\0\0", 'bin'];
    }

    #[DataProvider('unsupportedFileProvider')]
    public function testUnsupportedFileTypeThrows(string $content, string $extension): void
    {
        $file = $this->createFile($content, $extension);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported file type');
        $this->extractor->extractContent($file);
    }

    public function testUnknownMimeTypeThrows(): void
    {
        //getMimeType() returns null, if no guesser can determine a type
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported file type');
        $this->extractor->extractContent($file);
    }

    public function testAllowedMimeTypesAreSupported(): void
    {
        //The upload form uses this list, so every type in it has to be handled by extractContent()
        foreach (FileContentExtractor::ALLOWED_MIME_TYPES as $mimeType) {
            $file = $this->createMock(File::class);
            $file->method('getMimeType')->willReturn($mimeType);
            $file->method('getContent')->willReturn(
                str_contains($mimeType, 'pdf') ? $this->createPdf('<p>Content</p>') : 'Content'
            );

            self::assertStringContainsString('Content', $this->extractor->extractContent($file), $mimeType);
        }
    }
}
