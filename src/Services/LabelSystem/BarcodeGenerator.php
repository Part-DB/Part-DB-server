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

        switch ($options->getBarcodeType()) {
            case 'qr':
                $type = 'QRCODE';

                break;
            case 'datamatrix':
                $type = 'DATAMATRIX';

                break;
            case 'code39':
                $type = 'C39';

                break;
            case 'code93':
                $type = 'C93';

                break;
            case 'code128':
                $type = 'C128A';

                break;
            case 'none':
                return null;
            default:
                throw new InvalidArgumentException('Unknown label type!');
        }

        $bobj = $barcode->getBarcodeObj($type, $this->getContent($options, $target));

        return $bobj->getSvgCode();
    }

    public function getContent(LabelOptions $options, AbstractDBElement $target): ?string
    {
        return match ($options->getBarcodeType()) {
            'qr', 'datamatrix' => $this->barcodeContentGenerator->getURLContent($target),
            'code39', 'code93', 'code128' => $this->barcodeContentGenerator->get1DBarcodeContent($target),
            'none' => null,
            default => throw new InvalidArgumentException('Unknown label type!'),
        };
    }
}
