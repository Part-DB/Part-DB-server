<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\DataTables\Column;

use DateTime;
use DateTimeInterface;
use IntlDateFormatter;
use Locale;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Similar to the built in DateTimeColumn, but the datetime is formatted using a IntlDateFormatter,
 * to get prettier locale based formatting.
 */
class LocaleDateTimeColumn extends AbstractColumn
{
    public function normalize($value)
    {
        if (null === $value) {
            return $this->options['nullValue'];
        } elseif (! $value instanceof DateTimeInterface) {
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

        return $formatter->format($value->getTimestamp());
    }

    protected function configureOptions(OptionsResolver $resolver)
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
