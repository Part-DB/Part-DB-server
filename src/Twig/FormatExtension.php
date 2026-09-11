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
use Twig\Attribute\AsTwigFilter;

final readonly class FormatExtension
{
    public function __construct(private MarkdownParser $markdownParser, private MoneyFormatter $moneyFormatter, private SIFormatter $siformatter, private AmountFormatter $amountFormatter)
    {
    }

    /**
     * Mark the given text as markdown, which will be rendered in the browser
     */
    #[AsTwigFilter("format_markdown", isSafe: ['html'], preEscape: 'html')]
    public function formatMarkdown(string $markdown, bool $inline_mode = false): string
    {
        return $this->markdownParser->markForRendering($markdown, $inline_mode);
    }

    /**
     * Format the given amount as money, using a given currency
     */
    #[AsTwigFilter("format_money")]
    public function formatMoney(BigDecimal|float|string $amount, ?Currency $currency = null, int $decimals = 5): string
    {
        if ($amount instanceof BigDecimal) {
            $amount = (string) $amount;
        }

        return $this->moneyFormatter->format($amount, $currency, $decimals);
    }

    /**
     * Format the given number using SI prefixes and the given unit (string)
     */
    #[AsTwigFilter("format_si")]
    public function siFormat(float $value, string $unit, int $decimals = 2, bool $show_all_digits = false): string
    {
        return $this->siformatter->format($value, $unit, $decimals);
    }

    #[AsTwigFilter("format_amount")]
    public function amountFormat(float|int|string $value, ?MeasurementUnit $unit, array $options = []): string
    {
        return $this->amountFormatter->format($value, $unit, $options);
    }

    #[AsTwigFilter("format_bytes")]
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $size = ['B','kB','MB','GB','TB','PB','EB','ZB','YB'];
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
        //We use the real (10 based) SI prefix here
        return sprintf("%.{$precision}f", $bytes / (1000 ** $factor)) . ' ' . @$size[$factor];
    }
}
