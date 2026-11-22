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
        //Part images are only embedded, if the user is allowed to view them
        LabelPartImageProviderTest::loginAs('admin');
    }

    public function testLabelEditorParameters(): void
    {
        //These parameters are used by the label editor, so they must describe the label templates
        $params = LabelHTMLGenerator::getLabelEditorParameters();
        $this->assertEqualsCanonicalizing(['code128', 'code39', 'code93'], $params['types1D']);

        //The page margins of label_style.css.twig (12px 6px)
        $this->assertEqualsWithDelta(6 * 25.4 / 96, $params['pageMarginX'], 0.001);
        $this->assertEqualsWithDelta(12 * 25.4 / 96, $params['pageMarginY'], 0.001);

        //The text column is placed right of the QR code, and ends on the label
        $layout = $params['legacy2D'];
        $this->assertGreaterThan($layout['barcodeLeft'] + $layout['barcodeWidth'], $layout['textLeft']);
        $this->assertLessThanOrEqual(1.0, $layout['textLeft'] + $layout['textWidth']);

        //The layout with a barcode size: 4mm gap next to 2D barcodes (.barcode-cell-left), 1D barcodes with the same
        //height and caption as in getBarcodeLayout()
        $sized = $params['sizedLayout'];
        $this->assertEqualsWithDelta(4.0, $sized['sideGap'], 0.001);
        $options = (new LabelOptions())->setWidth(60)->setHeight(30)->setBarcodeType(BarcodeType::CODE128)->setBarcodeSize(20);
        $barcode = $this->service->getBarcodeLayout($options);
        $this->assertEqualsWithDelta($barcode['height'], $params['legacy1D']['height'], 0.001);
        $this->assertEqualsWithDelta(30 - 2 * 3.175 - $barcode['height'] - $sized['stackedGap'] - $sized['captionHeight'],
            $barcode['cell_height'], 0.001);
    }

    public function testFloatingImagesNextToSizedQRCode(): void
    {
        //With a barcode size, the text is next to the QR code cell (with a 4mm gap): On a 100mm label with a 40mm QR code
        //there are 96.825mm - 44mm = 52.825mm = 199.65px left, so two 100px images do not fit next to each other
        $image = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="100" height="50"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setBarcodeType(BarcodeType::QR)->setLines($image.$image.'<p>Test</p>');
        $options->setBarcodeSize(10);
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-top/', $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        $options->setBarcodeSize(40);
        $this->assertStringContainsString('margin-top: 50px;', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
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

    public function testLinkedPartImagePlaceholder(): void
    {
        //Linked images are wrapped in a link inside their figure. The width of the figure (set by the corner resize handles
        //of the editor) is still the width of the placeholder, so the 320x240px image is fitted into 120x120px
        $lines = '<figure class="image image_resized image-style-align-left" style="width:120px;"><a href="https://example.com">'
            .'<img style="aspect-ratio:64/64;" src="'.LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX.';base64,AAAA" width="64" height="64">'
            .'</a></figure><p>Test</p>';
        $options = (new LabelOptions())->setLines($lines);
        $html = $this->service->getLabelHTML($options, [LabelPartImageProviderTest::createPartWithPicture()]);

        $this->assertStringContainsString('width: 120.00px; height: 90.00px; padding: 15.00px 0.00px;', $html);
        $this->assertStringContainsString('style="width: 120.00px;"', $html);
        $this->assertStringContainsString('<a href="https://example.com">', $html);
    }

    public function testPartImagePlaceholderInRowOfFloatingImages(): void
    {
        //The part image is fitted into the placeholder and centered with padding, so the floating images are fitted with
        //the size of the placeholder, not of the image: The 320x240px image is 220x165px in the 220x200px placeholder, so
        //the image which does not fit next to it starts 200px below
        $placeholder = '<figure class="image image-style-align-left"><img src="'.LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX.';base64,AAAA" width="220" height="200"></figure>';
        $right = '<figure class="image image-style-align-right">'.$this->createImage(150, 50).'</figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(100)->setLines($placeholder.$right.'<p>Test</p>');
        $part = LabelPartImageProviderTest::createPartWithPicture();
        $html = $this->service->getLabelHTML($options, [$part]);
        $this->assertStringContainsString('width: 220.00px; height: 165.00px; padding: 17.50px 0.00px;', $html);
        $this->assertStringContainsString('margin-top: 200px;', $html);

        //On the rendered label, the placeholder has its size and the other image starts below it
        $layout = $this->renderLayout($options, $part);
        [$box, $image] = $layout['figure'];
        $this->assertEqualsWithDelta(200 * 25.4 / 96, $box['bottom'] - $box['top'], 0.05);
        $this->assertEqualsWithDelta($box['bottom'], $image['top'], 0.05);
    }

    public function testFloatingImagesWhichDoNotFit(): void
    {
        //dompdf does not move floats down, if they do not fit next to the previous ones, so we have to move them down
        //below the previous row (on a 100mm wide label, the right image does not fit next to the two left ones)
        $left1 = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="110" height="110"></figure>';
        $left2 = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="220" height="105"></figure>';
        $right = '<figure class="image image_resized image-style-align-right" style="width:117.6px;"><img src="data:image/png;base64,AAAA" width="351" height="199"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setLines($left1.$left2.$right.'<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        //The row is as high as its highest image
        $this->assertStringContainsString('style="width:117.6px; margin-top: 110px;"', $html);

        //If the label is wide enough, nothing is changed
        $options->setWidth(200);
        $this->assertStringContainsString('image-style-align-right" style="width:117.6px;"', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testFloatingImageUpToTheEdge(): void
    {
        //The gap after the last image of a row may extend into the page margin, so an image fits up to the edge of the
        //label (100mm label: 96.825mm usable width = 365.95px, minus the first image with its gap = 250.83px)
        $first = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="100" height="100"></figure>';
        $image = fn(int $width) => '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="'.$width.'" height="105"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50);

        $options->setLines($first.$image(250).'<p>Test</p>');
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-top/', $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        //A wider image does not fit anymore
        $options->setLines($first.$image(252).'<p>Test</p>');
        $this->assertMatchesRegularExpression('/<figure[^>]*margin-top/', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testFloatingImagesWrappedRight(): void
    {
        //Two images wrapped right need the gap to the text on the left, the gap between them and the gap which may extend
        //into the page margin (365.95px usable width + 15.12px gap = 2 * 165px + 3 * 15.12px + 5.71px)
        $image = fn(int $width) => '<figure class="image image-style-align-right"><img src="data:image/png;base64,AAAA" width="'.$width.'" height="50"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50);

        $options->setLines($image(165).$image(165).'<p>Test</p>');
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-top/', $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        //Wider images do not fit next to each other anymore
        $options->setLines($image(170).$image(170).'<p>Test</p>');
        $this->assertStringContainsString('margin-top: 50px;', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testFloatingImagesWrappedLeftAndRight(): void
    {
        //Between an image wrapped left and an image wrapped right, the gap is only needed once (365.95px usable width
        //= 200px + 15.12px gap + 150.83px), so their gaps may overlap
        $left = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="200" height="50"></figure>';
        $right = fn(int $width) => '<figure class="image image-style-align-right"><img src="data:image/png;base64,AAAA" width="'.$width.'" height="50"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50);

        //With both gaps there are 135.71px left for the right image: Nothing is changed
        $options->setLines($left.$right(135).'<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-(top|left)/', $html);

        //A wider image fits, if the gaps overlap: The left margin of the right image is reduced by the missing 14.29px
        $options->setLines($left.$right(150).'<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringContainsString('image-style-align-right" style="margin-left: 0.83px;"', $html);
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-top/', $html);
        //The same, if the right image comes first
        $options->setLines($right(150).$left.'<p>Test</p>');
        $html = $this->service->getLabelHTML($options, [$this->getExamplePart()]);
        $this->assertStringContainsString('image-style-align-right" style="margin-left: 0.83px;"', $html);
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-top/', $html);

        //With less than one gap between the images, the right image starts a new row
        $options->setLines($left.$right(155).'<p>Test</p>');
        $this->assertStringContainsString('margin-top: 50px;', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testFloatingImagesNextToQRCode(): void
    {
        //Next to a QR code, only the text column is available for the images (54.33% of the usable width of 96.825mm
        //= 198.83px): The second image fits on a label without barcode, but not next to the QR code
        $image = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="100" height="50"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setLines($image.$image.'<p>Test</p>');
        $this->assertDoesNotMatchRegularExpression('/<figure[^>]*margin-top/', $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        $options->setBarcodeType(BarcodeType::QR);
        $this->assertStringContainsString('margin-top: 50px;', $this->service->getLabelHTML($options, [$this->getExamplePart()]));
    }

    public function testTextBelowFloatingImages(): void
    {
        //If the text does not fit next to the floating images, a spacer moves it directly below them (like in browsers),
        //as dompdf would move it down in steps of the line height
        $high = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="94" height="94"></figure>';
        $wide = '<figure class="image image-style-align-left"><img src="data:image/png;base64,AAAA" width="250" height="80"></figure>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setLines($high.$wide.'<p>Test</p>');
        //The first image is 94px high, the second one 80px: When the second image ends, there is space next to the
        //first one, so the text starts there (the spacer is 0.2px higher, see LabelHTMLGenerator::moveBelowFloatingImages())
        $this->assertStringContainsString('<div class="partdb-float-spacer" style="height: 80.20px;"></div><p>Test</p>',
            $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        //If there is enough space next to the images, the text is placed there
        $options->setLines($high.'<p>Test</p>');
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

    public function testEmptyLineNextToWideImage(): void
    {
        //On a 100mm label, a 346px wide image wrapped left leaves 4.83px next to it: Too little for text, but enough for
        //an empty line, which has no width. So the empty line stays next to the image (like in the editor), and only the
        //text after it is moved below the image (the image ends 50px - 15.36px line height = 34.64px below the empty line)
        $lines = '<figure class="image image-style-align-left">'.$this->createImage(346, 50).'</figure><p>&nbsp;</p><p>Text</p>';
        $options = (new LabelOptions())->setWidth(100)->setHeight(50)->setLines($lines);
        $this->assertStringContainsString('</figure><p><br></p><div class="partdb-float-spacer" style="height: 34.84px;"></div><p>Text</p>',
            $this->service->getLabelHTML($options, [$this->getExamplePart()]));

        //On the rendered label, the empty line starts next to the image and the text directly below it
        $layout = $this->renderLayout($options);
        [$image, $empty_line, $text] = [$layout['figure'][0], $layout['p'][0], $layout['p'][1]];
        $this->assertEqualsWithDelta($image['top'], $empty_line['top'], 0.01);
        $this->assertEqualsWithDelta($image['bottom'], $text['top'], 0.1);
        //So everything stays on the label (the page content ends 12px = 3.175mm above the bottom)
        $this->assertLessThanOrEqual(50 - 3.175, $text['bottom']);
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
        $layout = $this->renderBarcodeLayout($options);
        $this->assertCount(3, $layout['lines']);
        $this->assertSame(1, $layout['pages']);
        //The barcode starts below the last line of text...
        $this->assertGreaterThanOrEqual(max(array_column($layout['lines'], 1)) - 0.01, $layout['barcode'][0]);
        //... and stays on the label together with its caption (the page content ends 12px = 3.175mm above the bottom)
        $this->assertLessThanOrEqual(30 - 3.175 + 0.01, $layout['caption'][1]);

        //If the text fits above it, the barcode keeps its previous position: Its caption ends 32px (8.467mm) above the
        //bottom of the page content
        $layout = $this->renderBarcodeLayout($options->setLines('<p>First line</p>'));
        $this->assertEqualsWithDelta(30 - 3.175 - 8.467, $layout['caption'][1], 0.05);
        $this->assertGreaterThan(max(array_column($layout['lines'], 1)), $layout['barcode'][0]);

        //Labels with custom CSS for the barcode may have placed it somewhere else, so they keep the fixed position
        //(here 5px above the bottom of the page content), even if it covers the text
        $layout = $this->renderBarcodeLayout($options->setLines($lines)->setAdditionalCss('.C39-container { bottom: 5px; }'));
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
     * Renders a label for the given element (the example part by default) with dompdf (like LabelGenerator does) and returns the boxes of its paragraphs
     * and figures (in mm, relative to the top left corner of the label), in the order of the content.
     * @return array{p: list<array{top: float, bottom: float, left: float, right: float}>, figure: list<array{top: float, bottom: float, left: float, right: float}>}
     */
    private function renderLayout(LabelOptions $options, ?object $element = null): array
    {
        $dompdf = self::getContainer()->get(DompdfFactoryInterface::class)->create();
        $dompdf->setPaper(self::getContainer()->get(LabelGenerator::class)->mmToPointsArray($options->getWidth(), $options->getHeight()));
        $dompdf->loadHtml($this->service->getLabelHTML($options, [$element ?? $this->getExamplePart()]));

        $boxes = ['p' => [], 'figure' => []];
        $dompdf->setCallbacks([['event' => 'end_frame', 'f' => static function (Frame $frame) use (&$boxes): void {
            $node = $frame->get_node();
            if ($node instanceof \DOMElement && isset($boxes[$node->nodeName])) {
                [$x, $y, $width, $height] = $frame->get_border_box();
                $mm = static fn(float $pt): float => $pt / LabelGenerator::MM_TO_POINTS_FACTOR;
                $boxes[$node->nodeName][] = ['top' => $mm($y), 'bottom' => $mm($y + $height), 'left' => $mm($x), 'right' => $mm($x + $width)];
            }
        }]]);
        $dompdf->render();

        return $boxes;
    }

    /**
     * Creates an img element with a PNG image of the given size (dompdf needs a real image to lay it out).
     */
    private function createImage(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return '<img src="data:image/png;base64,'.base64_encode((string) ob_get_clean()).'" width="'.$width.'" height="'.$height.'">';
    }

    /**
     * Renders a label for the example part with dompdf (like LabelGenerator does) and returns the vertical position (top
     * and bottom in mm, relative to the top of the label) of its text lines, its barcode and the caption of the barcode,
     * together with the number of pages.
     * @return array{lines: list<array{float, float}>, barcode: array{float, float}|null, caption: array{float, float}|null, pages: int}
     */
    private function renderBarcodeLayout(LabelOptions $options): array
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
