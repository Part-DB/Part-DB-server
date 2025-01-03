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

namespace App\Services\LabelSystem\BarcodeScanner;

use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * @see \App\Tests\Services\LabelSystem\Barcodes\BarcodeScanHelperTest
 */
final class BarcodeScanHelper
{
    private const PREFIX_TYPE_MAP = [
        'L' => LabelSupportedElement::PART_LOT,
        'P' => LabelSupportedElement::PART,
        'S' => LabelSupportedElement::STORELOCATION,
    ];

    public const QR_TYPE_MAP = [
        'lot' => LabelSupportedElement::PART_LOT,
        'part' => LabelSupportedElement::PART,
        'location' => LabelSupportedElement::STORELOCATION,
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Parse the given barcode content and return the target type and ID.
     * If the barcode could not be parsed, an exception is thrown.
     * Using the $type parameter, you can specify how the barcode should be parsed. If set to null, the function
     * will try to guess the type.
     * @param  string  $input
     * @param BarcodeSourceType|null  $type
     * @return LocalBarcodeScanResult
     */
    public function scanBarcodeContent(string $input, ?BarcodeSourceType $type = null): BarcodeScanResultInterface
    {
        //Do specific parsing
        if ($type === BarcodeSourceType::INTERNAL) {
            return $this->parseInternalBarcode($input) ?? throw new InvalidArgumentException('Could not parse barcode');
        }
        if ($type === BarcodeSourceType::USER_DEFINED) {
            return $this->parseUserDefinedBarcode($input) ?? throw new InvalidArgumentException('Could not parse barcode');
        }
        if ($type === BarcodeSourceType::IPN) {
            return $this->parseIPNBarcode($input) ?? throw new InvalidArgumentException('Could not parse barcode');
        }
        if ($type === BarcodeSourceType::EIGP114) {
            return $this->parseEIGP114Barcode($input);
        }

        //Null means auto and we try the different formats

        //If the barcode is formatted as EIGP114, we can parse it directly
        if (EIGP114BarcodeScanResult::isFormat06Code($input)) {
            return $this->parseEIGP114Barcode($input);
        }

        $result = $this->parseInternalBarcode($input);

        if ($result !== null) {
            return $result;
        }

        //Try to parse as User defined barcode
        $result = $this->parseUserDefinedBarcode($input);
        if ($result !== null) {
            return $result;
        }

        //Try to parse as IPN barcode
        $result = $this->parseIPNBarcode($input);
        if ($result !== null) {
            return $result;
        }

        throw new InvalidArgumentException('Unknown barcode');
    }

    private function parseEIGP114Barcode(string $input): EIGP114BarcodeScanResult
    {
        return EIGP114BarcodeScanResult::parseFormat06Code($input);
    }

    private function parseUserDefinedBarcode(string $input): ?LocalBarcodeScanResult
    {
        $lot_repo = $this->entityManager->getRepository(PartLot::class);
        //Find only the first result
        $results = $lot_repo->findBy(['user_barcode' => $input], limit: 1);

        if (count($results) === 0) {
            return null;
        }
        //We found a part, so use it to create the result
        $lot = $results[0];

        return new LocalBarcodeScanResult(
            target_type: LabelSupportedElement::PART_LOT,
            target_id: $lot->getID(),
            source_type: BarcodeSourceType::USER_DEFINED
        );
    }

    private function parseIPNBarcode(string $input): ?LocalBarcodeScanResult
    {
        $part_repo = $this->entityManager->getRepository(Part::class);
        //Find only the first result
        $results = $part_repo->findBy(['ipn' => $input], limit: 1);

        if (count($results) === 0) {
            return null;
        }
        //We found a part, so use it to create the result
        $part = $results[0];

        return new LocalBarcodeScanResult(
            target_type: LabelSupportedElement::PART,
            target_id: $part->getID(),
            source_type: BarcodeSourceType::IPN
        );
    }

    /**
     * This function tries to interpret the given barcode content as an internal barcode.
     * If the barcode could not be parsed at all, null is returned. If the barcode is a valid format, but could
     * not be found in the database, an exception is thrown.
     * @param  string  $input
     * @return LocalBarcodeScanResult|null
     */
    private function parseInternalBarcode(string $input): ?LocalBarcodeScanResult
    {
        $input = trim($input);
        $matches = [];

        //Some scanner output '-' as ß, so replace it (ß is never used, so we can replace it safely)
        $input = str_replace('ß', '-', $input);

        //Extract parts from QR code's URL
        if (preg_match('#^https?://.*/scan/(\w+)/(\d+)/?$#', $input, $matches)) {
            return new LocalBarcodeScanResult(
                target_type:  self::QR_TYPE_MAP[strtolower($matches[1])],
                target_id: (int) $matches[2],
                source_type: BarcodeSourceType::INTERNAL
            );
        }

        //New Code39 barcode use L0001 format
        if (preg_match('#^([A-Z])(\d{4,})$#', $input, $matches)) {
            $prefix = $matches[1];
            $id = (int) $matches[2];

            if (!isset(self::PREFIX_TYPE_MAP[$prefix])) {
                throw new InvalidArgumentException('Unknown prefix '.$prefix);
            }

            return new LocalBarcodeScanResult(
                target_type:  self::PREFIX_TYPE_MAP[$prefix],
                target_id: $id,
                source_type: BarcodeSourceType::INTERNAL
            );
        }

        //During development the L-000001 format was used
        if (preg_match('#^(\w)-(\d{6,})$#', $input, $matches)) {
            $prefix = $matches[1];
            $id = (int) $matches[2];

            if (!isset(self::PREFIX_TYPE_MAP[$prefix])) {
                throw new InvalidArgumentException('Unknown prefix '.$prefix);
            }

            return new LocalBarcodeScanResult(
                target_type:  self::PREFIX_TYPE_MAP[$prefix],
                target_id: $id,
                source_type: BarcodeSourceType::INTERNAL
            );
        }

        //Legacy Part-DB location labels used $L00336 format
        if (preg_match('#^\$L(\d{5,})$#', $input, $matches)) {
            return new LocalBarcodeScanResult(
                target_type: LabelSupportedElement::STORELOCATION,
                target_id: (int) $matches[1],
                source_type: BarcodeSourceType::INTERNAL
            );
        }

        //Legacy Part-DB used EAN8 barcodes for part labels. Format 0000001(2) (note the optional 8th digit => checksum)
        if (preg_match('#^(\d{7})\d?$#', $input, $matches)) {
            return new LocalBarcodeScanResult(
                target_type: LabelSupportedElement::PART,
                target_id: (int) $matches[1],
                source_type: BarcodeSourceType::INTERNAL
            );
        }

        //This function abstain from further parsing
        return null;
    }
}
