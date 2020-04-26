<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\LabelSystem\LabelOptions;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;
use Com\Tecnick\Barcode\Barcode;

class BarcodeGenerator
{
    protected $barcodeContentGenerator;

    public function __construct(BarcodeContentGenerator $barcodeContentGenerator)
    {
        $this->barcodeContentGenerator = $barcodeContentGenerator;
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
            case 'none':
                return null;
            default:
                throw new \InvalidArgumentException('Unknown label type!');
        }



        $bobj = $barcode->getBarcodeObj($type, $this->getContent($options, $target));

        return $bobj->getSvgCode();
    }

    public function getContent(LabelOptions $options, object $target): ?string
    {
        switch ($options->getBarcodeType()) {
            case 'qr':
            case 'datamatrix':
                return $this->barcodeContentGenerator->getURLContent($target);
            case 'code39':
            case 'code93':
                return $this->barcodeContentGenerator->get1DBarcodeContent($target);
            case 'none':
                return null;
            default:
                throw new \InvalidArgumentException('Unknown label type!');
        }
    }

}