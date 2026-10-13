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

use App\Services\LabelSystem\LabelHTMLGenerator;
use PHPUnit\Framework\TestCase;

final class LabelHTMLGeneratorTest extends TestCase
{
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
}
