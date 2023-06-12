<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\LabelSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelOptions;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;
use Com\Tecnick\Barcode\Barcode;
use InvalidArgumentException;

/**
 * @see \App\Tests\Services\LabelSystem\BarcodeGeneratorTest
 */
final class BarcodeGenerator
{
    public function __construct(private readonly BarcodeContentGenerator $barcodeContentGenerator)
    {
    }

    public function generateHTMLBarcode(LabelOptions $options, object $target): ?string
    {
        $svg = $this->generateSVG($options, $target);
        $base64 = $this->dataUri($svg, 'image/svg+xml');
        return '<img src="'.$base64.'" width="100%" style="min-height: 25px;" alt="'. $this->getContent($options, $target) . '" />';
    }

     /**
     * Creates a data URI (RFC 2397).
     * Based on the Twig implementaion from HTMLExtension
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

    public function generateSVG(LabelOptions $options, object $target): ?string
    {
        $barcode = new Barcode();

        $type = match ($options->getBarcodeType()) {
            BarcodeType::NONE => null,
            BarcodeType::QR => 'QRCODE',
            BarcodeType::DATAMATRIX => 'DATAMATRIX',
            BarcodeType::CODE39 => 'C39',
            BarcodeType::CODE93 => 'C93',
            BarcodeType::CODE128 => 'C128A',
            default => throw new InvalidArgumentException('Unknown label type!'),
        };

        if ($type === null) {
            return null;
        }


        return $barcode->getBarcodeObj($type, $this->getContent($options, $target))->getSvgCode();
    }

    public function getContent(LabelOptions $options, AbstractDBElement $target): ?string
    {
        $barcode = $options->getBarcodeType();
        return match (true) {
            $barcode->is2D() => $this->barcodeContentGenerator->getURLContent($target),
            $barcode->is1D() => $this->barcodeContentGenerator->get1DBarcodeContent($target),
            $barcode === BarcodeType::NONE => null,
            default => throw new InvalidArgumentException('Unknown label type!'),
        };
    }
}
