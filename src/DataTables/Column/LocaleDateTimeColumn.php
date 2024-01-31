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

namespace App\DataTables\Column;

use DateTime;
use DateTimeInterface;
use Exception;
use IntlDateFormatter;
use Locale;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Similar to the built-in DateTimeColumn, but the datetime is formatted using a IntlDateFormatter,
 * to get prettier locale based formatting.
 */
class LocaleDateTimeColumn extends AbstractColumn
{
    /**
     * @param $value
     * @throws Exception
     */
    public function normalize($value): string
    {
        if (null === $value) {
            return $this->options['nullValue'];
        }

        if (!$value instanceof DateTimeInterface) {
            $value = new DateTime((string) $value);
        }

        $formatValues = [
            'none' => IntlDateFormatter::NONE,
            'short' => IntlDateFormatter::SHORT,
            'medium' => IntlDateFormatter::MEDIUM,
            'long' => IntlDateFormatter::LONG,
            'full' => IntlDateFormatter::FULL,
        ];

        $formatter = IntlDateFormatter::create(
            Locale::getDefault(),
            $formatValues[$this->options['dateFormat']],
            $formatValues[$this->options['timeFormat']],
            null
        );

        //For the tooltip text
        $long_formatter = IntlDateFormatter::create(
            Locale::getDefault(),
            IntlDateFormatter::FULL,
            IntlDateFormatter::LONG,
            null
        );

        return sprintf('<span title="%s">%s</span>',
            $long_formatter->format($value->getTimestamp()), //Long form
            $formatter->format($value->getTimestamp()) //Short form
        );
    }

    protected function configureOptions(OptionsResolver $resolver): static
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'dateFormat' => 'short',
                'timeFormat' => 'short',
                'nullValue' => '',
            ])
            ->setAllowedTypes('nullValue', 'string')
        ;

        return $this;
    }
}
