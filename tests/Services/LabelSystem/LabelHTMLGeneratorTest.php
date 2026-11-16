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
namespace App\Tests\Services\LabelSystem;

use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\Parts\Part;
use App\Services\LabelSystem\LabelExampleElementsGenerator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\LabelSystem\LabelHTMLGenerator;
use Dompdf\Frame;
use Jbtronics\DompdfFontLoaderBundle\Services\DompdfFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LabelHTMLGeneratorTest extends KernelTestCase
{
    private LabelHTMLGenerator $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(LabelHTMLGenerator::class);
    }

    public function testBarcodeLayoutNone(): void
    {
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::NONE);
        $this->assertNull($this->service->getBarcodeLayout($options));
    }

    public function testBarcodeLayout2D(): void
    {
        $options = (new LabelOptions())->setWidth(60)->setHeight(30)->setBarcodeType(BarcodeType::QR);
        $layout = $this->service->getBarcodeLayout($options);

        $this->assertTrue($layout['side']);
        //30% of the usable width (60mm - 2 * 1.5875mm)
        $this->assertEqualsWithDelta(17.048, $layout['width'], 0.001);
        $this->assertSame($layout['width'], $layout['height']);
        //The QR code must never be larger than the usable label height
        $options->setWidth(200);
        $this->assertEqualsWithDelta(30 - 2 * 3.175, $this->service->getBarcodeLayout($options)['width'], 0.001);
        //An explicit size is always used
        $options->setBarcodeSize(12.5);
        $this->assertSame(12.5, $this->service->getBarcodeLayout($options)['width']);
    }

    public function testBarcodeLayout1D(): void
    {
        $options = (new LabelOptions())->setWidth(60)->setHeight(30)->setBarcodeType(BarcodeType::CODE128);
        $layout = $this->service->getBarcodeLayout($options);

        $this->assertFalse($layout['side']);
        $this->assertEqualsWithDelta(39.6875, $layout['width'], 0.001);
        $this->assertEqualsWithDelta(7.9375, $layout['height'], 0.001);
        //The text cell gets the space above the barcode
        $this->assertEqualsWithDelta(30 - 2 * 3.175 - 7.9375 - 1.0 - 2.5, $layout['cell_height'], 0.001);
        //Without a size, the barcode (with its 2.709mm high caption) ends 32px above the bottom of the content: The text
        //gets the space above it
        $this->assertEqualsWithDelta(30 - 2 * 3.175 - 8.4667 - 7.9375 - 2.709, $layout['legacy_text_height'], 0.001);
        //On small labels there is no space above that position
        $this->assertSame(0.0, $this->service->getBarcodeLayout($options->setHeight(20))['legacy_text_height']);
    }

    public function testLegacyLayoutWithoutSize(): void
    {
        //Existing labels (without a barcode size) must keep the legacy layouts, so that they look exactly as before
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::QR)->setLines('<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringContainsString('<div class="col-5">', $html);
        $this->assertStringNotContainsString('<table class="label-layout', $html);

        $options->setBarcodeType(BarcodeType::CODE128);
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringContainsString('<div class="C39-container" style="">', $html);
        $this->assertStringNotContainsString('<table class="label-layout', $html);
    }

    public function testLegacy1DLayoutDoesNotOverlapText(): void
    {
        //Without a barcode size, 1D barcodes used to be placed at a fixed position, 32px above the bottom of the page
        //content. On a 50x30mm label only a single line of text fitted above it, the following lines were covered.
        $lines = '<p>First line</p><p>Second line</p><p>Third line</p>';
        $options = (new LabelOptions())->setWidth(50)->setHeight(30)->setBarcodeType(BarcodeType::CODE128)->setLines($lines);
        $layout = $this->renderLayout($options);
        $this->assertCount(3, $layout['lines']);
        $this->assertSame(1, $layout['pages']);
        //The barcode starts below the last line of text...
        $this->assertGreaterThanOrEqual(max(array_column($layout['lines'], 1)) - 0.01, $layout['barcode'][0]);
        //... and stays on the label together with its caption (the page content ends 12px = 3.175mm above the bottom)
        $this->assertLessThanOrEqual(30 - 3.175 + 0.01, $layout['caption'][1]);

        //If the text fits above it, the barcode keeps its previous position: Its caption ends 32px (8.467mm) above the
        //bottom of the page content
        $layout = $this->renderLayout($options->setLines('<p>First line</p>'));
        $this->assertEqualsWithDelta(30 - 3.175 - 8.467, $layout['caption'][1], 0.05);
        $this->assertGreaterThan(max(array_column($layout['lines'], 1)), $layout['barcode'][0]);

        //Labels with custom CSS for the barcode may have placed it somewhere else, so they keep the fixed position
        //(here 5px above the bottom of the page content), even if it covers the text
        $layout = $this->renderLayout($options->setLines($lines)->setAdditionalCss('.C39-container { bottom: 5px; }'));
        $this->assertEqualsWithDelta(30 - 3.175 - 5 * 25.4 / 96, $layout['caption'][1], 0.05);
    }

    public function testDefaultLayoutWithoutPlaceholder(): void
    {
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::QR)->setBarcodeSize(15)->setLines('<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);

        $this->assertStringContainsString('<table class="label-layout', $html);
        $this->assertStringContainsString('width: 15mm', $html);
    }

    /**
     * Renders a label for the example part with dompdf (like LabelGenerator does) and returns the vertical position (top
     * and bottom in mm, relative to the top of the label) of its text lines, its barcode and the caption of the barcode,
     * together with the number of pages.
     * @return array{lines: list<array{float, float}>, barcode: array{float, float}|null, caption: array{float, float}|null, pages: int}
     */
    private function renderLayout(LabelOptions $options): array
    {
        $dompdf = self::getContainer()->get(DompdfFactoryInterface::class)->create();
        $dompdf->setPaper(self::getContainer()->get(LabelGenerator::class)->mmToPointsArray($options->getWidth(), $options->getHeight()));
        $dompdf->loadHtml($this->service->getLabelHTML($options, [$this->getExamplePart()]));

        $layout = ['lines' => [], 'barcode' => null, 'caption' => null];
        $dompdf->setCallbacks([['event' => 'end_frame', 'f' => static function (Frame $frame) use (&$layout): void {
            $node = $frame->get_node();
            if (!$node instanceof \DOMElement) {
                return;
            }
            [, $y, , $height] = $frame->get_border_box();
            $box = [$y / LabelGenerator::MM_TO_POINTS_FACTOR, ($y + $height) / LabelGenerator::MM_TO_POINTS_FACTOR];
            if ($node->nodeName === 'p') {
                $layout['lines'][] = $box;
            } elseif ($node->nodeName === 'img' && $node->getAttribute('class') === 'C39') {
                $layout['barcode'] = $box;
            } elseif ($node->getAttribute('class') === 'C39-text') {
                $layout['caption'] = $box;
            }
        }]]);
        $dompdf->render();
        $layout['pages'] = $dompdf->getCanvas()->get_page_count();

        return $layout;
    }

    private function getExamplePart(): Part
    {
        return self::getContainer()->get(LabelExampleElementsGenerator::class)->getExamplePart();
    }
}
