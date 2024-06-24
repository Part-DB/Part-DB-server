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
use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelOptions;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;
use App\Services\LabelSystem\Barcodes\BarcodeHelper;
use InvalidArgumentException;

/**
 * @see \App\Tests\Services\LabelSystem\LabelBarcodeGeneratorTest
 */
final class LabelBarcodeGenerator
{
    public function __construct(private readonly BarcodeContentGenerator $barcodeContentGenerator, private readonly BarcodeHelper $barcodeHelper)
    {
    }

    /**
     * Generate the barcode for the given label as HTML image tag.
     * @param  LabelOptions  $options
     * @param  AbstractDBElement  $target
     * @return string|null
     */
    public function generateHTMLBarcode(LabelOptions $options, AbstractDBElement $target): ?string
    {
        if ($options->getBarcodeType() === BarcodeType::NONE) {
            return null;
        }

        return $this->barcodeHelper->barcodeAsHTML($this->getContent($options, $target), $options->getBarcodeType());
    }

    /**
     * Generate the barcode for the given label as SVG string.
     * @param  LabelOptions  $options
     * @param  AbstractDBElement  $target
     * @return string|null
     */
    public function generateSVG(LabelOptions $options, AbstractDBElement $target): ?string
    {
        if ($options->getBarcodeType() === BarcodeType::NONE) {
            return null;
        }

        return $this->barcodeHelper->barcodeAsSVG($this->getContent($options, $target), $options->getBarcodeType());
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
