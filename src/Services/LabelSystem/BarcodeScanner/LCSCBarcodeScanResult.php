<?php

declare(strict_types=1);

namespace App\Services\LabelSystem\BarcodeScanner;

use InvalidArgumentException;

/**
 * This class represents the content of a lcsc.com barcode
 * Its data structure is represented by {pbn:...,on:...,pc:...,pm:...,qty:...}
 */
readonly class LCSCBarcodeScanResult implements BarcodeScanResultInterface
{

    /** @var string|null (pbn) */
    public ?string $pickBatchNumber;

    /** @var string|null (on) */
    public ?string $orderNumber;

    /** @var string|null LCSC Supplier part number (pc) */
    public ?string $lcscCode;

    /** @var string|null (pm) */
    public ?string $mpn;

    /** @var int|null (qty) */
    public ?int $quantity;

    /** @var string|null Country Channel as raw value (CC) */
    public ?string $countryChannel;

    /**
     * @var string|null Warehouse code as raw value (WC)
     */
    public ?string $warehouseCode;

    /**
     * @var string|null Unknown numeric code (pdi)
     */
    public ?string $pdi;

    /**
     * @var string|null Unknown value (hp)
     */
    public ?string $hp;

    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public array $fields,
        public string $rawInput,
    ) {

        $this->pickBatchNumber = $this->fields['pbn'] ?? null;
        $this->orderNumber = $this->fields['on'] ?? null;
        $this->lcscCode = $this->fields['pc'] ?? null;
        $this->mpn = $this->fields['pm'] ?? null;
        $this->quantity = isset($this->fields['qty']) ? (int)$this->fields['qty'] : null;
        $this->countryChannel = $this->fields['cc'] ?? null;
        $this->warehouseCode = $this->fields['wc'] ?? null;
        $this->pdi = $this->fields['pdi'] ?? null;
        $this->hp = $this->fields['hp'] ?? null;

    }

    public function getSourceType(): BarcodeSourceType
    {
        return BarcodeSourceType::LCSC;
    }

    /**
     * @return array|float[]|int[]|null[]|string[] An array of fields decoded from the barcode
     */
    public function getDecodedForInfoMode(): array
    {
        // Keep it human-friendly
        return [
            'Barcode type' => 'LCSC',
            'MPN (pm)' => $this->mpn ?? '',
            'LCSC code (pc)' => $this->lcscCode ?? '',
            'Qty' => $this->quantity !== null ? (string) $this->quantity : '',
            'Order No (on)' => $this->orderNumber ?? '',
            'Pick Batch (pbn)' => $this->pickBatchNumber ?? '',
            'Warehouse (wc)' => $this->warehouseCode ?? '',
            'Country/Channel (cc)' => $this->countryChannel ?? '',
            'PDI (unknown meaning)' => $this->pdi ?? '',
            'HP (unknown meaning)' => $this->hp ?? '',
        ];
    }

    /**
     * Parses the barcode data to see if the input matches the expected format used by lcsc.com
     * @param string $input
     * @return bool
     */
    public static function isLCSCBarcode(string $input): bool
    {
        $s = trim($input);

        // Your example: {pbn:...,on:...,pc:...,pm:...,qty:...}
        if (!str_starts_with($s, '{') || !str_ends_with($s, '}')) {
            return false;
        }

        // Must contain at least pm: and pc: (common for LCSC labels)
        return (stripos($s, 'pm:') !== false) && (stripos($s, 'pc:') !== false);
    }

    /**
     * Parse the barcode input string into the fields used by lcsc.com
     * @param string $input
     * @return self
     */
    public static function parse(string $input): self
    {
        $raw = trim($input);

        if (!self::isLCSCBarcode($raw)) {
            throw new InvalidArgumentException('Not an LCSC barcode');
        }

        $inner = substr($raw, 1, -1); // remove { }

        $fields = [];

        // This format is comma-separated pairs, values do not contain commas in your sample.
        $pairs = array_filter(
            array_map(trim(...), explode(',', $inner)),
            static fn(string $s): bool => $s !== ''
        );

        foreach ($pairs as $pair) {
            $pos = strpos($pair, ':');
            if ($pos === false) {
                continue;
            }

            $k = trim(substr($pair, 0, $pos));
            $v = trim(substr($pair, $pos + 1));

            if ($k === '') {
                continue;
            }

            $fields[$k] = $v;
        }

        if (!isset($fields['pm']) || trim($fields['pm']) === '') {
            throw new InvalidArgumentException('LCSC barcode missing pm field');
        }

        return new self($fields, $raw);
    }
}
