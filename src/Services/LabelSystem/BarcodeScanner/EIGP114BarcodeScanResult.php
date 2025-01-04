<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This class represents the content of a EIGP114 barcode.
 * Based on PR 811, EIGP 114.2018 (https://www.ecianow.org/assets/docs/GIPC/EIGP-114.2018%20ECIA%20Labeling%20Specification%20for%20Product%20and%20Shipment%20Identification%20in%20the%20Electronics%20Industry%20-%202D%20Barcode.pdf),
 * , https://forum.digikey.com/t/digikey-product-labels-decoding-digikey-barcodes/41097
 */
class EIGP114BarcodeScanResult implements BarcodeScanResultInterface
{

    /**
     * @var string|null Ship date in format YYYYMMDD
     */
    public readonly ?string $shipDate;

    /**
     * @var string|null Customer assigned part number – Optional based on
     * agreements between Distributor and Supplier
     */
    public readonly ?string $customerPartNumber;

    /**
     * @var string|null Supplier assigned part number
     */
    public readonly ?string $supplierPartNumber;

    /**
     * @var int|null Quantity of product
     */
    public readonly ?int $quantity;

    /**
     * @var string|mixed|null Customer assigned purchase order number
     */
    public readonly ?string $customerPO;

    /**
     * @var string|null Line item number from PO. Required on Logistic Label when
     * used on back of Packing Slip. See Section 4.9
    */
    public readonly ?string $customerPOLine;

    /**
     * 9D - YYWW (Year and Week of Manufacture). ) If no date code is used
     * for a particular part, this field should be populated with N/T
     * to indicate the product is Not Traceable by this data field.
     * @var string|null
     */
    public readonly ?string $dateCode;

    /**
     * 10D - YYWW (Year and Week of Manufacture). ) If no date code is used
     * for a particular part, this field should be populated with N/T
     * to indicate the product is Not Traceable by this data field.
     * @var string|null
     */
    public readonly ?string $alternativeDateCode;

    /**
     * Traceability number assigned to a batch or group of items. If
     * no lot code is used for a particular part, this field should be
     * populated with N/T to indicate the product is Not Traceable
     * by this data field.
     * @var string|null
     */
    public readonly ?string $lotCode;

    /**
     * Country where part was manufactured. Two-letter code from
     * ISO 3166 country code list
     * @var string|null
     */
    public readonly ?string $countryOfOrigin;

    /**
     * @var string|null Unique alphanumeric number assigned by supplier
     * 3S - Package ID for Inner Pack when part of a mixed Logistic
     * Carton. Always used in conjunction with a mixed logistic label
     * with a 5S data identifier for Package ID.
     */
    public readonly ?string $packageId1;

    /**
     * @var string|mixed|null
     * 4S - Package ID for Logistic Carton with like items
     */
    public readonly ?string $packageId2;

    /**
     * @var string|null
     * 5S - Package ID for Logistic Carton with mixed items
     */
    public readonly ?string $packageId3;

    /**
     * @var string|null Unique alphanumeric number assigned by supplier.
     */
    public readonly ?string $packingListNumber;

    /**
     * @var string|null Ship date in format YYYYMMDD
     */
    public readonly ?string $serialNumber;

    /**
     * @var string|null Code for sorting and classifying LEDs. Use when applicable
     */
    public readonly ?string $binCode;

    /**
     * @var int|null Sequential carton count in format “#/#” or “# of #”
     */
    public readonly ?int $packageCount;

    /**
     * @var string|null Alphanumeric string assigned by the supplier to distinguish
     * from one closely-related design variation to another. Use as
     * required or when applicable
     */
    public readonly ?string $revisionNumber;

    /**
     * @var string|null Digikey Extension: This is not represented in the ECIA spec, but the field being used is found in the ANSI MH10.8.2-2016 spec on which the ECIA spec is based. In the ANSI spec it is called First Level (Supplier Assigned) Part Number.
     */
    public readonly ?string $digikeyPartNumber;

    /**
     * @var string|null Digikey Extension: This can be shared across multiple invoices and time periods and is generated as an order enters our system from any vector (web, API, phone order, etc.)
     */
    public readonly ?string $digikeySalesOrderNumber;

    /**
     * @var string|null Digikey extension: This is typically assigned per shipment as items are being released to be picked in the warehouse. A SO can have many Invoice numbers
     */
    public readonly ?string $digikeyInvoiceNumber;

    /**
     * @var string|null Digikey extension: This is for internal DigiKey purposes and defines the label type.
     */
    public readonly ?string $digikeyLabelType;

    /**
     * @var string|null You will also see this as the last part of a URL for a product detail page. Ex https://www.digikey.com/en/products/detail/w%C3%BCrth-elektronik/860010672008/5726907
     */
    public readonly ?string $digikeyPartID;

    /**
     * @var string|null Digikey Extension: For internal use of Digikey. Probably not needed
     */
    public readonly ?string $digikeyNA;

    /**
     * @var string|null Digikey Extension: This is a field of varying length used to keep the barcode approximately the same size between labels. It is safe to ignore.
     */
    public readonly ?string $digikeyPadding;

    public readonly ?string $mouserPositionInOrder;

    public readonly ?string $mouserManufacturer;



    /**
     *
     * @param  array<string, string>  $data The fields of the EIGP114 barcode, where the key is the field name and the value is the field content
     */
    public function __construct(public readonly array $data)
    {
        //IDs per EIGP 114.2018
        $this->shipDate = $data['6D'] ?? null;
        $this->customerPartNumber = $data['P'] ?? null;
        $this->supplierPartNumber = $data['1P'] ?? null;
        $this->quantity = isset($data['Q']) ? (int)$data['Q'] : null;
        $this->customerPO = $data['K'] ?? null;
        $this->customerPOLine = $data['4K'] ?? null;
        $this->dateCode = $data['9D'] ?? null;
        $this->alternativeDateCode = $data['10D'] ?? null;
        $this->lotCode = $data['1T'] ?? null;
        $this->countryOfOrigin = $data['4L'] ?? null;
        $this->packageId1 = $data['3S'] ?? null;
        $this->packageId2 = $data['4S'] ?? null;
        $this->packageId3 = $data['5S'] ?? null;
        $this->packingListNumber = $data['11K'] ?? null;
        $this->serialNumber = $data['S'] ?? null;
        $this->binCode = $data['33P'] ?? null;
        $this->packageCount = isset($data['13Q']) ? (int)$data['13Q'] : null;
        $this->revisionNumber = $data['2P'] ?? null;
        //IDs used by Digikey
        $this->digikeyPartNumber = $data['30P'] ?? null;
        $this->digikeySalesOrderNumber = $data['1K'] ?? null;
        $this->digikeyInvoiceNumber = $data['10K'] ?? null;
        $this->digikeyLabelType = $data['11Z'] ?? null;
        $this->digikeyPartID = $data['12Z'] ?? null;
        $this->digikeyNA = $data['13Z'] ?? null;
        $this->digikeyPadding = $data['20Z'] ?? null;
        //IDs used by Mouser
        $this->mouserPositionInOrder = $data['14K'] ?? null;
        $this->mouserManufacturer = $data['1V'] ?? null;
    }

    /**
     * Tries to guess the vendor of the barcode based on the supplied data field.
     * This is experimental and should not be relied upon.
     * @return string|null The guessed vendor as smallcase string (e.g. "digikey", "mouser", etc.), or null if the vendor could not be guessed
     */
    public function guessBarcodeVendor(): ?string
    {
        //If the barcode data contains the digikey extensions, we assume it is a digikey barcode
        if (isset($this->data['13Z']) || isset($this->data['20Z']) || isset($this->data['12Z']) || isset($this->data['11Z'])) {
            return 'digikey';
        }

        //If the barcode data contains the mouser extensions, we assume it is a mouser barcode
        if (isset($this->data['14K']) || isset($this->data['1V'])) {
            return 'mouser';
        }

        //According to this thread (https://github.com/inventree/InvenTree/issues/853), Newark/element14 codes contains a "3P" field
        if (isset($this->data['3P'])) {
            return 'element14';
        }

        return null;
    }

    /**
     * Checks if the given input is a valid format06 formatted data.
     * This just perform a simple check for the header, the content might be malformed still.
     * @param  string  $input
     * @return bool
     */
    public static function isFormat06Code(string $input): bool
    {
        //Code must begin with [)><RS>06<GS>
        if(!str_starts_with($input, "[)>\u{1E}06\u{1D}")){
            return false;
        }

        //Digikey does not put a trailer onto the barcode, so we just check for the header

        return true;
    }

    /**
     * Parses a format06 code a returns a new instance of this class
     * @param  string  $input
     * @return self
     */
    public static function parseFormat06Code(string $input): self
    {
        //Ensure that the input is a valid format06 code
        if (!self::isFormat06Code($input)) {
            throw new \InvalidArgumentException("The given input is not a valid format06 code");
        }

        //Remove the trailer, if present
        if (str_ends_with($input, "\u{1E}\u{04}")){
            $input = substr($input, 5, -2);
        }

        //Split the input into the different fields (using the <GS> separator)
        $parts = explode("\u{1D}", $input);

        //The first field is the format identifier, which we do not need
        array_shift($parts);

        //Split the fields into key-value pairs
        $results = [];

        foreach($parts as $part) {
            //^                     0*                            ([1-9]?            \d*                           [A-Z])
            //Start of the string   Leading zeros are discarded    Not a zero        Any number of digits          single uppercase Letter
            //                      00                             1                 4                             K

            if(!preg_match('/^0*([1-9]?\d*[A-Z])/', $part, $matches)) {
                throw new \LogicException("Could not parse field: $part");
            }
            //Extract the key
            $key = $matches[0];
            //Extract the field value
            $fieldValue = substr($part, strlen($matches[0]));

            $results[$key] = $fieldValue;
        }

        return new self($results);
    }

    public function getDecodedForInfoMode(): array
    {
        $tmp = [
            'Barcode type' => 'EIGP114',
            'Guessed vendor from barcode' => $this->guessBarcodeVendor() ?? 'Unknown',
        ];

        //Iterate over all fields of this object and add them to the array if they are not null
        foreach($this as $key => $value) {
            //Skip data key
            if ($key === 'data') {
                continue;
            }
            if($value !== null) {
                $tmp[$key] = $value;
            }
        }

        return $tmp;
    }
}