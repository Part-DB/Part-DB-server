<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Twig;

use Com\Tecnick\Barcode\Barcode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BarcodeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            /* Generates a barcode with the given Type and Data and returns it as an SVG represenation */
            new TwigFunction('barcode_svg', [$this, 'barcodeSVG']),
        ];
    }

    public function barcodeSVG(string $content, string $type = 'QRCODE'): string
    {
        $barcodeFactory = new Barcode();
        return $barcodeFactory->getBarcodeObj($type, $content)->getSvgCode();
    }
}
