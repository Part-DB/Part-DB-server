<?php
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

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Parts\MeasurementUnit;
use App\Entity\PriceInformations\Currency;
use App\Services\Formatters\AmountFormatter;
use App\Services\Formatters\MarkdownParser;
use App\Services\Formatters\MoneyFormatter;
use App\Services\Formatters\SIFormatter;
use Brick\Math\BigDecimal;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class FormatExtension extends AbstractExtension
{
    public function __construct(protected MarkdownParser $markdownParser, protected MoneyFormatter $moneyFormatter, protected SIFormatter $siformatter, protected AmountFormatter $amountFormatter)
    {
    }

    public function getFilters(): array
    {
        return [
            /* Mark the given text as markdown, which will be rendered in the browser */
            new TwigFilter('format_markdown', fn(string $markdown, bool $inline_mode = false): string => $this->markdownParser->markForRendering($markdown, $inline_mode), [
                'pre_escape' => 'html',
                'is_safe' => ['html'],
            ]),
            /* Format the given amount as money, using a given currency */
            new TwigFilter('format_money', fn($amount, ?Currency $currency = null, int $decimals = 5): string => $this->formatCurrency($amount, $currency, $decimals)),
            /* Format the given number using SI prefixes and the given unit (string) */
            new TwigFilter('format_si', fn($value, $unit, $decimals = 2, bool $show_all_digits = false): string => $this->siFormat($value, $unit, $decimals, $show_all_digits)),
            /** Format the given amount using the given MeasurementUnit */
            new TwigFilter('format_amount', fn($value, ?MeasurementUnit $unit, array $options = []): string => $this->amountFormat($value, $unit, $options)),
            /** Format the given number of bytes as human-readable number */
            new TwigFilter('format_bytes', fn(int $bytes, int $precision = 2): string => $this->formatBytes($bytes, $precision)),
        ];
    }

    public function formatCurrency($amount, ?Currency $currency = null, int $decimals = 5): string
    {
        if ($amount instanceof BigDecimal) {
            $amount = (string) $amount;
        }

        return $this->moneyFormatter->format($amount, $currency, $decimals);
    }

    public function siFormat($value, $unit, $decimals = 2, bool $show_all_digits = false): string
    {
        return $this->siformatter->format($value, $unit, $decimals);
    }

    public function amountFormat($value, ?MeasurementUnit $unit, array $options = []): string
    {
        return $this->amountFormatter->format($value, $unit, $options);
    }

    /**
     * @param $bytes
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $size = ['B','kB','MB','GB','TB','PB','EB','ZB','YB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        //We use the real (10 based) SI prefix here
        return sprintf("%.{$precision}f", $bytes / (1000 ** $factor)) . ' ' . @$size[$factor];
    }
}
