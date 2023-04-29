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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\LabelSystem\LabelOptions;
use App\Services\LabelSystem\BarcodeGenerator;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;

final class BarcodeProvider implements PlaceholderProviderInterface
{
    private BarcodeGenerator $barcodeGenerator;
    private BarcodeContentGenerator $barcodeContentGenerator;

    public function __construct(BarcodeGenerator $barcodeGenerator, BarcodeContentGenerator $barcodeContentGenerator)
    {
        $this->barcodeGenerator = $barcodeGenerator;
        $this->barcodeContentGenerator = $barcodeContentGenerator;
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ('[[1D_CONTENT]]' === $placeholder) {
            try {
                return $this->barcodeContentGenerator->get1DBarcodeContent($label_target);
            } catch (\InvalidArgumentException $e) {
                return 'ERROR!';
            }
        }

        if ('[[2D_CONTENT]]' === $placeholder) {
            try {
                return $this->barcodeContentGenerator->getURLContent($label_target);
            } catch (\InvalidArgumentException $e) {
                return 'ERROR!';
            }
        }

        if ('[[BARCODE_QR]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType('qr');
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        if ('[[BARCODE_C39]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType('code39');
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        if ('[[BARCODE_C128]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType('code128');
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        return null;
    }
}