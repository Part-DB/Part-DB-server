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

namespace App\Services\LabelSystem\BarcodeScanner;

use InvalidArgumentException;

/**
 * This class represents the content of a tme.eu barcode label.
 * The format is space-separated KEY:VALUE tokens, e.g.:
 *   QTY:1000 PN:SMD0603-5K1-1% PO:32723349/7 MFR:ROYALOHM MPN:0603SAF5101T5E CoO:TH RoHS https://www.tme.eu/details/...
 */
readonly class TMEBarcodeScanResult implements BarcodeScanResultInterface
{
    /** @var int|null Quantity (QTY) */
    public ?int $quantity;

    /** @var string|null TME part number (PN) */
    public ?string $tmePartNumber;

    /** @var string|null Purchase order number (PO) */
    public ?string $purchaseOrder;

    /** @var string|null Manufacturer name (MFR) */
    public ?string $manufacturer;

    /** @var string|null Manufacturer part number (MPN) */
    public ?string $mpn;

    /** @var string|null Country of origin (CoO) */
    public ?string $countryOfOrigin;

    /** @var bool Whether the part is RoHS compliant */
    public bool $rohs;

    /** @var string|null The product URL */
    public ?string $productUrl;

    /**
     * @param array<string, string> $fields Parsed key-value fields (keys uppercased)
     * @param string $rawInput Original barcode string
     */
    public function __construct(
        public array $fields,
        public string $rawInput,
    ) {
        $this->quantity = isset($this->fields['QTY']) ? (int) $this->fields['QTY'] : null;
        $this->tmePartNumber = $this->fields['PN'] ?? null;
        $this->purchaseOrder = $this->fields['PO'] ?? null;
        $this->manufacturer = $this->fields['MFR'] ?? null;
        $this->mpn = $this->fields['MPN'] ?? null;
        $this->countryOfOrigin = $this->fields['COO'] ?? null;
        $this->rohs = isset($this->fields['ROHS']);
        $this->productUrl = $this->fields['URL'] ?? null;
    }

    public function getSourceType(): BarcodeSourceType
    {
        return BarcodeSourceType::TME;
    }

    public function getDecodedForInfoMode(): array
    {
        return [
            'Barcode type' => 'TME',
            'TME Part No. (PN)' => $this->tmePartNumber ?? '',
            'MPN' => $this->mpn ?? '',
            'Manufacturer (MFR)' => $this->manufacturer ?? '',
            'Qty' => $this->quantity !== null ? (string) $this->quantity : '',
            'Purchase Order (PO)' => $this->purchaseOrder ?? '',
            'Country of Origin (CoO)' => $this->countryOfOrigin ?? '',
            'RoHS' => $this->rohs ? 'Yes' : 'No',
            'URL' => $this->productUrl ?? '',
        ];
    }

    /**
     * Returns true if the input looks like a TME barcode label (contains tme.eu URL).
     */
    public static function isTMEBarcode(string $input): bool
    {
        return str_contains(strtolower($input), 'tme.eu');
    }

    /**
     * Parse the TME barcode string into a TMEBarcodeScanResult.
     */
    public static function parse(string $input): self
    {
        $raw = trim($input);

        if (!self::isTMEBarcode($raw)) {
            throw new InvalidArgumentException('Not a TME barcode');
        }

        $fields = [];

        // Split on whitespace; each token is either KEY:VALUE, a bare keyword, or the URL
        $tokens = preg_split('/\s+/', $raw);
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            // The TME URL
            if (str_starts_with(strtolower($token), 'http')) {
                $fields['URL'] = $token;
                continue;
            }

            $colonPos = strpos($token, ':');
            if ($colonPos !== false) {
                $key = strtoupper(substr($token, 0, $colonPos));
                $value = substr($token, $colonPos + 1);
                $fields[$key] = $value;
            } else {
                // Bare keyword like "RoHS"
                $fields[strtoupper($token)] = '';
            }
        }

        return new self($fields, $raw);
    }
}
