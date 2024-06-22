<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\LabelSystem\Barcodes;

use App\Entity\LabelSystem\BarcodeType;
use Com\Tecnick\Barcode\Barcode;

/**
 * This function is used to generate barcodes of various types using arbitrary (text) content.
 * @see \App\Tests\Services\LabelSystem\Barcodes\BarcodeHelperTest
 */
class BarcodeHelper
{

    /**
     * Generates a barcode with the given content and type and returns it as SVG string.
     * @param  string  $content
     * @param  BarcodeType  $type
     * @return string
     */
    public function barcodeAsSVG(string $content, BarcodeType $type): string
    {
        $barcode = new Barcode();

        $type_str = match ($type) {
            BarcodeType::NONE => throw new \InvalidArgumentException('Barcode type must not be NONE! This would make no sense...'),
            BarcodeType::QR => 'QRCODE',
            BarcodeType::DATAMATRIX => 'DATAMATRIX',
            BarcodeType::CODE39 => 'C39',
            BarcodeType::CODE93 => 'C93',
            BarcodeType::CODE128 => 'C128A',
        };

        return $barcode->getBarcodeObj($type_str, $content)->getSvgCode();
    }

    /**
     * Generates a barcode with the given content and type and returns it as HTML image tag.
     * @param  string  $content
     * @param  BarcodeType  $type
     * @param  string  $width Width of the image tag
     * @param  string|null  $alt_text The alt text of the image tag. If null, the content is used.
     * @return string
     */
    public function barcodeAsHTML(string $content, BarcodeType $type, string $width = '100%', ?string $alt_text = null): string
    {
        $svg = $this->barcodeAsSVG($content, $type);
        $base64 = $this->dataUri($svg, 'image/svg+xml');
        $alt_text ??= $content;
        
        return '<img src="'.$base64.'" width="'.$width.'" style="min-height: 25px;" alt="'.$alt_text.'"/>';
    }

    /**
     * Creates a data URI (RFC 2397).
     * Based on the Twig implementation from HTMLExtension
     *
     * Length validation is not performed on purpose, validation should
     * be done before calling this filter.
     *
     * @return string The generated data URI
     */
    private function dataUri(string $data, string $mime): string
    {
        $repr = 'data:';

        $repr .= $mime;
        if (str_starts_with($mime, 'text/')) {
            $repr .= ','.rawurlencode($data);
        } else {
            $repr .= ';base64,'.base64_encode($data);
        }

        return $repr;
    }
}