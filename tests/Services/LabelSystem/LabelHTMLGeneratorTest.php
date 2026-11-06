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
use App\Services\LabelSystem\LabelHTMLGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LabelHTMLGeneratorTest extends KernelTestCase
{
    private LabelHTMLGenerator $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(LabelHTMLGenerator::class);
        //Part images are only embedded, if the user is allowed to view them
        LabelPartImageProviderTest::loginAs('admin');
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
    }

    public function testLabelEditorParameters(): void
    {
        //These parameters are used by the label editor, so they must describe the same size as getBarcodeLayout()
        $params = LabelHTMLGenerator::getLabelEditorParameters();
        $this->assertEqualsCanonicalizing(['code128', 'code39', 'code93'], $params['types1D']);

        $options = (new LabelOptions())->setWidth(60)->setHeight(30)->setBarcodeType(BarcodeType::QR);
        $content_width = 60 - 2 * $params['pageMarginX'];
        $this->assertEqualsWithDelta($content_width * $params['default2DWidthFactor'], $this->service->getBarcodeLayout($options)['width'], 0.001);

        $options->setBarcodeType(BarcodeType::CODE128);
        $this->assertEqualsWithDelta($params['default1DHeight'], $this->service->getBarcodeLayout($options)['height'], 0.001);
    }

    public function testPartImagePlaceholder(): void
    {
        //Like CKEditor outputs a resized block image: The 320x240 image must be fitted into the 120x120px placeholder
        $lines = '<figure class="image image-style-align-right image_resized" style="width:120px;">'
            .'<img style="aspect-ratio:64/64;" src="'.LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX.';base64,AAAA" width="64" height="64"></figure><p>Test</p>';
        $options = (new LabelOptions())->setLines($lines);
        $html = $this->service->getLabelHTML($options, [LabelPartImageProviderTest::createPartWithPicture()]);

        $this->assertStringNotContainsString(LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX, $html);
        $this->assertStringContainsString('src="data:image/png;base64,', $html);
        $this->assertStringContainsString('width: 120.00px; height: 90.00px; padding: 15.00px 0.00px;', $html);
        $this->assertStringContainsString('style="width: 120.00px;"', $html);
        $this->assertStringContainsString('image-style-align-right', $html);

        //Without an image, the placeholder becomes an empty box with its size
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringContainsString('src="data:image/gif;base64,', $html);
        $this->assertStringContainsString('width: 120.00px; height: 120.00px;', $html);
    }

    public function testRectangularPartImagePlaceholder(): void
    {
        //The size set in the editor is stored as the size of the placeholder image: The 320x240 image is fitted into 120x60px
        $ph = LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX.';base64,AAAA';
        $options = (new LabelOptions())->setLines('<p><img src="'.$ph.'" width="120" height="60"></p>');
        $html = $this->service->getLabelHTML($options, [LabelPartImageProviderTest::createPartWithPicture()]);
        $this->assertStringContainsString('width: 80.00px; height: 60.00px; padding: 0.00px 20.00px;', $html);

        //Resized with the resize handles afterward: The aspect ratio of the placeholder is kept
        $options->setLines('<p><img class="image_resized" style="aspect-ratio:120/60;width:240px;" src="'.$ph.'" width="120" height="60"></p>');
        $html = $this->service->getLabelHTML($options, [LabelPartImageProviderTest::createPartWithPicture()]);
        $this->assertStringContainsString('width: 160.00px; height: 120.00px; padding: 0.00px 40.00px;', $html);
    }

    public function testFloatingImagesWhichDoNotFit(): void
    {
        //dompdf does not move floats down, if they do not fit next to the previous ones, so we have to move them down
        //below the previous row (on a 100mm wide label, the right image does not fit next to the two left ones)
        $left1 = '<figure class="image image-style-align-left"><img src="'.LabelHTMLGenerator::BARCODE_IMAGE_SRC_PREFIX.';base64,AAAA" width="64" height="64"></figure>';
        $left2 = '<figure class="image image-style-align-left"><img src="'.LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX.';base64,AAAA" width="220" height="105"></figure>';
        $right = '<figure class="image image_resized image-style-align-right" style="width:117.6px;"><img src="data:image/png;base64,AAAA" width="351" height="199"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setBarcodeType(BarcodeType::QR)
            ->setLines($left1.$left2.$right.'<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        //The row is as high as the QR code (30% of the usable width of 96.825mm = 109.79px)
        $this->assertStringContainsString('style="width:117.6px; margin-top: 109.79px;"', $html);

        //If the label is wide enough, nothing is changed
        $options->setWidth(200);
        $this->assertStringContainsString('image-style-align-right" style="width:117.6px;"', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testFloatingImageUpToTheEdge(): void
    {
        //The gap after the last image of a row may extend into the page margin, so an image fits up to the edge of the
        //label (100mm label: 96.825mm usable width = 365.95px, minus the QR code with its gap = 241.07px)
        $qr = '<figure class="image image-style-align-left"><img src="'.LabelHTMLGenerator::BARCODE_IMAGE_SRC_PREFIX.';base64,AAAA" width="64" height="64"></figure>';
        $image = fn(int $width) => '<figure class="image image-style-align-left"><img src="'.LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX.';base64,AAAA" width="'.$width.'" height="105"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setBarcodeType(BarcodeType::QR);

        $options->setLines($qr.$image(241).'<p>Test</p>');
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-top/', $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        //A wider image does not fit anymore
        $options->setLines($qr.$image(243).'<p>Test</p>');
        $this->assertMatchesRegularExpression('/<figure[^>]*margin-top/', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testTextBelowFloatingImages(): void
    {
        //If the text does not fit next to the floating images, a spacer moves it directly below them (like in browsers),
        //as dompdf would move it down in steps of the line height
        $qr = '<figure class="image image-style-align-left"><img src="'.LabelHTMLGenerator::BARCODE_IMAGE_SRC_PREFIX.';base64,AAAA" width="64" height="64"></figure>';
        $image = '<figure class="image image-style-align-left"><img src="'.LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX.';base64,AAAA" width="241" height="80"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setBarcodeType(BarcodeType::QR)->setBarcodeSize(25)
            ->setLines($qr.$image.'<p>Test</p>');
        //The QR code is 25mm = 94.49px high, the part image 80px: When the part image ends, there is space next to the
        //QR code, so the text starts there (the spacer is 0.2px higher, see LabelHTMLGenerator::moveBelowFloatingImages())
        $this->assertStringContainsString('<div class="partdb-float-spacer" style="height: 80.20px;"></div><p>Test</p>',
            $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        //If there is enough space next to the images, the text is placed there
        $options->setLines($qr.'<p>Test</p>');
        $this->assertStringNotContainsString('partdb-float-spacer', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testEmptyLinesWithFloatingImages(): void
    {
        //Empty lines saved by CKEditor contain a non-breaking space, which is replaced by a line break next to floating images
        $image = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="40" height="20"></figure>';
        $options = (new LabelOptions())->setLines($image.'<p>&nbsp;</p><p>Test&nbsp;1</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringContainsString('<p><br></p><p>Test&nbsp;1</p>', $html);

        //Without floating images, the lines are not changed
        $options->setLines('<p>&nbsp;</p><p>Test</p>');
        $this->assertStringContainsString('<p>&nbsp;</p><p>Test</p>', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
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

    public function testLegacyLayoutWithFloatingImages(): void
    {
        //dompdf can not handle floats inside floats, so the legacy QR columns must not float if the lines contain wrapped images
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::QR)->setLines('<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringNotContainsString('row-inline', substr($html, strpos($html, '<body>')));

        $options->setLines('<figure class="image image-style-align-right"><img src="data:image/png;base64,AAAA"></figure><p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringContainsString('<div class="row row-inline">', $html);
    }

    public function testDefaultLayoutWithoutPlaceholder(): void
    {
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::QR)->setBarcodeSize(15)->setLines('<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);

        $this->assertStringContainsString('<table class="label-layout', $html);
        $this->assertStringContainsString('width: 15mm', $html);
    }

    public function testBarcodePlaceholderImage(): void
    {
        //Like CKEditor outputs a resized image, with the text wrapped around it
        $lines = '<figure class="image image-style-align-right image_resized" style="width:50px;">'
            .'<img style="aspect-ratio:64/64;" src="'.LabelHTMLGenerator::BARCODE_IMAGE_SRC_PREFIX.';base64,AAAA" alt="Barcode" width="64" height="64">'
            .'</figure><p>Test</p>';
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::QR)->setBarcodeSize(15)->setLines($lines);
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);

        //The default layout must not be used
        $this->assertStringNotContainsString('<table class="label-layout', $html);
        //The placeholder is replaced by the real barcode with the configured size...
        $this->assertStringNotContainsString(LabelHTMLGenerator::BARCODE_IMAGE_SRC_PREFIX, $html);
        $this->assertStringContainsString('src="data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('width: 15mm; height: 15mm;', $html);
        $this->assertStringNotContainsString('width="64"', $html);
        $this->assertStringNotContainsString('width:50px', $html);
        //... while keeping the placement
        $this->assertStringContainsString('image-style-align-right', $html);
        $this->assertStringContainsString('<p>Test</p>', $html);
    }

    public function testBarcodePlaceholderClass(): void
    {
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::CODE128)
            ->setLines('<p><img class="partdb-barcode" style="float: right;">Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);

        $this->assertStringNotContainsString('<table class="label-layout', $html);
        $this->assertStringContainsString('float: right; width: 39.688mm; height: 7.938mm;', $html);
    }

    public function testBarcodePlaceholderWithoutBarcodeType(): void
    {
        $options = (new LabelOptions())->setBarcodeType(BarcodeType::NONE)
            ->setLines('<p><img class="partdb-barcode">Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);

        $this->assertStringNotContainsString('<img class="partdb-barcode"', $html);
        $this->assertStringContainsString('<p>Test</p>', $html);
    }

    private function getExamplePart(): Part
    {
        return self::getContainer()->get(LabelExampleElementsGenerator::class)->getExamplePart();
    }
}
