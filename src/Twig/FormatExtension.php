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

use App\Entity\Attachments\Attachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\ProjectSystem\Project;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Services\Formatters\AmountFormatter;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\EntityURLGenerator;
use App\Services\Misc\FAIconGenerator;
use App\Services\Formatters\MarkdownParser;
use App\Services\Formatters\MoneyFormatter;
use App\Services\Formatters\SIFormatter;
use App\Services\Trees\TreeViewGenerator;
use Brick\Math\BigDecimal;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

use function get_class;

final class FormatExtension extends AbstractExtension
{
    protected MarkdownParser $markdownParser;
    protected MoneyFormatter $moneyFormatter;
    protected SIFormatter $siformatter;
    protected AmountFormatter $amountFormatter;


    public function __construct(MarkdownParser $markdownParser, MoneyFormatter $moneyFormatter,
        SIFormatter $SIFormatter, AmountFormatter $amountFormatter)
    {
        $this->markdownParser = $markdownParser;
        $this->moneyFormatter = $moneyFormatter;
        $this->siformatter = $SIFormatter;
        $this->amountFormatter = $amountFormatter;
    }

    public function getFilters(): array
    {
        return [
            /* Mark the given text as markdown, which will be rendered in the browser */
            new TwigFilter('format_markdown', [$this->markdownParser, 'markForRendering'], [
                'pre_escape' => 'html',
                'is_safe' => ['html'],
            ]),
            /* Format the given amount as money, using a given currency */
            new TwigFilter('format_money', [$this, 'formatCurrency']),
            /* Format the given number using SI prefixes and the given unit (string) */
            new TwigFilter('format_si', [$this, 'siFormat']),
            /** Format the given amount using the given MeasurementUnit */
            new TwigFilter('format_amount', [$this, 'amountFormat']),
            /** Format the given number of bytes as human readable number */
            new TwigFilter('format_bytes', [$this, 'formatBytes']),
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
     * @param int $precision
     * @return string
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $size = ['B','kB','MB','GB','TB','PB','EB','ZB','YB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        //We use the real (10 based) SI prefix here
        return sprintf("%.{$precision}f", $bytes / (1000 ** $factor)) . ' ' . @$size[$factor];
    }
}
