<?php

declare(strict_types=1);

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

use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Services\LabelSystem\Barcodes\BarcodeHelper;
use App\Services\LabelSystem\LabelBarcodeGenerator;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;
use Com\Tecnick\Barcode\Exception;

final class BarcodeProvider implements PlaceholderProviderInterface
{
    public function __construct(private readonly LabelBarcodeGenerator $barcodeGenerator,
        private readonly BarcodeContentGenerator $barcodeContentGenerator,
        private readonly BarcodeHelper $barcodeHelper)
    {
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ('[[1D_CONTENT]]' === $placeholder) {
            try {
                return $this->barcodeContentGenerator->get1DBarcodeContent($label_target);
            } catch (\InvalidArgumentException) {
                return 'ERROR!';
            }
        }

        if ('[[2D_CONTENT]]' === $placeholder) {
            try {
                return $this->barcodeContentGenerator->getURLContent($label_target);
            } catch (\InvalidArgumentException) {
                return 'ERROR!';
            }
        }

        if ('[[BARCODE_QR]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType(BarcodeType::QR);
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        if ('[[BARCODE_C39]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType(BarcodeType::CODE39);
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        if ('[[BARCODE_C128]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType(BarcodeType::CODE128);
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        if ($label_target instanceof Part || $label_target instanceof PartLot) {
            if ($label_target instanceof PartLot) {
                $label_target = $label_target->getPart();
            }

            if ($label_target === null || $label_target->getIPN() === null || $label_target->getIPN() === '') {
                //Replace with empty result, if no IPN is set
                return '';
            }

            try {
                //Add placeholders for the IPN barcode
                if ('[[IPN_BARCODE_C39]]' === $placeholder) {
                    return $this->barcodeHelper->barcodeAsHTML($label_target->getIPN(), BarcodeType::CODE39);
                }
                if ('[[IPN_BARCODE_C128]]' === $placeholder) {
                    return $this->barcodeHelper->barcodeAsHTML($label_target->getIPN(), BarcodeType::CODE128);
                }
                if ('[[IPN_BARCODE_QR]]' === $placeholder) {
                    return $this->barcodeHelper->barcodeAsHTML($label_target->getIPN(), BarcodeType::QR);
                }
            } catch (Exception $e) {
                //If an error occurs, output it
                return '<b>IPN Barcode ERROR!</b>: '.$e->getMessage();
            }
        }




        return null;
    }
}
