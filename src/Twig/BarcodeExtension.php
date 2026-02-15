<?php

declare(strict_types=1);

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
use Twig\Attribute\AsTwigFunction;

final class BarcodeExtension
{
    /**
     * Generates a barcode in SVG format for the given content and type.
     */
    #[AsTwigFunction('barcode_svg')]
    public function barcodeSVG(string $content, string $type = 'QRCODE'): string
    {
        $barcodeFactory = new Barcode();
        return $barcodeFactory->getBarcodeObj($type, $content)->getSvgCode();
    }
}
