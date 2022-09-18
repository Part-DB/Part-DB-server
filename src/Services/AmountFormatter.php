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

namespace App\Services;

use App\Entity\Parts\MeasurementUnit;
use InvalidArgumentException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This service formats an part amout using a Measurement Unit.
 */
class AmountFormatter
{
    protected SIFormatter $siFormatter;

    public function __construct(SIFormatter $siFormatter)
    {
        $this->siFormatter = $siFormatter;
    }

    /**
     *  Formats the given value using the measurement unit and options.
     *
     * @param float|string|int     $value
     * @param MeasurementUnit|null $unit  The measurement unit, whose unit symbol should be used for formatting.
     *                                    If set to null, it is assumed that the part amount is measured in pieces.
     *
     * @return string The formatted string
     *
     * @throws InvalidArgumentException thrown if $value is not numeric
     */
    public function format($value, ?MeasurementUnit $unit = null, array $options = []): string
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('$value must be an numeric value!');
        }
        $value = (float) $value;

        //Find out what options to use
        $resolver = new OptionsResolver();
        $resolver->setDefault('measurement_unit', $unit);
        $this->configureOptions($resolver);

        $options = $resolver->resolve($options);

        if ($options['is_integer']) {
            $value = round($value);
        }

        //If the measurement unit uses a SI prefix format it that way.
        if ($options['show_prefix']) {
            return $this->siFormatter->format($value, $options['unit'], $options['decimals']);
        }

        //Otherwise just output it
        if (!empty($options['unit'])) {
            $format_string = '%.'.$options['decimals'].'f '.$options['unit'];
        } else { //Dont add space after number if no unit was specified
            $format_string = '%.'.$options['decimals'].'f';
        }

        return sprintf($format_string, $value);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'show_prefix' => static function (Options $options) {
                if (null !== $options['measurement_unit']) {
                    /** @var MeasurementUnit $unit */
                    $unit = $options['measurement_unit'];

                    return $unit->isUseSIPrefix();
                }

                return false;
            },
            'is_integer' => static function (Options $options) {
                if (null !== $options['measurement_unit']) {
                    /** @var MeasurementUnit $unit */
                    $unit = $options['measurement_unit'];

                    return $unit->isInteger();
                }

                return true;
            },
            'unit' => static function (Options $options) {
                if (null !== $options['measurement_unit']) {
                    /** @var MeasurementUnit $unit */
                    $unit = $options['measurement_unit'];

                    //When no unit is set, return empty string so that this can be formatted properly
                    return $unit->getUnit() ?? '';
                }

                return '';
            },
            'decimals' => 2,
            'error_mapping' => [
                '.' => 'value',
            ],
        ]);

        $resolver->setAllowedTypes('decimals', 'int');

        $resolver->setNormalizer('decimals', static function (Options $options, $value) {
            // If the unit is integer based, then dont show any decimals
            if ($options['is_integer']) {
                return 0;
            }

            return $value;
        });
    }
}
