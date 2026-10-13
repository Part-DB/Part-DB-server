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

    private function getExamplePart(): Part
    {
        return self::getContainer()->get(LabelExampleElementsGenerator::class)->getExamplePart();
    }
}
