<?php

declare(strict_types=1);

namespace App\Services\LabelSystem\BarcodeScanner;

use InvalidArgumentException;

/**
 * This class represents the content of a lcsc.com barcode
 * Its data structure is represented by {pbn:...,on:...,pc:...,pm:...,qty:...}
 */
class LCSCBarcodeScanResult implements BarcodeScanResultInterface
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public readonly array $fields,
        public readonly string $raw_input,
    ) {}

    public function getSourceType(): BarcodeSourceType
    {
        return BarcodeSourceType::LCSC;
    }

    /**
     * @return string|null The manufactures part number
     */
    public function getPM(): ?string
    {
        $v = $this->fields['pm'] ?? null;
        $v = $v !== null ? trim($v) : null;
        return ($v === '') ? null : $v;
    }

    /**
     * @return string|null The lcsc.com part number
     */
    public function getPC(): ?string
    {
        $v = $this->fields['pc'] ?? null;
        $v = $v !== null ? trim($v) : null;
        return ($v === '') ? null : $v;
    }

    /**
     * @return array|float[]|int[]|null[]|string[] An array of fields decoded from the barcode
     */
    public function getDecodedForInfoMode(): array
    {
        // Keep it human-friendly
        return [
            'Barcode type' => 'LCSC',
            'MPN (pm)' => $this->getPM() ?? '',
            'LCSC code (pc)' => $this->getPC() ?? '',
            'Qty' => $this->fields['qty'] ?? '',
            'Order No (on)' => $this->fields['on'] ?? '',
            'Pick Batch (pbn)' => $this->fields['pbn'] ?? '',
            'Warehouse (wc)' => $this->fields['wc'] ?? '',
            'Country/Channel (cc)' => $this->fields['cc'] ?? '',
        ];
    }

    /**
     * Parses the barcode data to see if the input matches the expected format used by lcsc.com
     * @param string $input
     * @return bool
     */
    public static function looksLike(string $input): bool
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

        if (!self::looksLike($raw)) {
            throw new InvalidArgumentException('Not an LCSC barcode');
        }

        $inner = trim($raw);
        $inner = substr($inner, 1, -1); // remove { }

        $fields = [];

        // This format is comma-separated pairs, values do not contain commas in your sample.
        $pairs = array_filter(array_map('trim', explode(',', $inner)));

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
