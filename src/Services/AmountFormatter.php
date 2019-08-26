<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Services;


use App\Entity\Parts\MeasurementUnit;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This service formats an part amout using a Measurement Unit.
 * @package App\Services
 */
class AmountFormatter
{
    protected $siFormatter;

    public function __construct(SIFormatter $siFormatter)
    {
        $this->siFormatter = $siFormatter;
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'show_prefix' => function (Options $options) {
                if ($options['measurement_unit'] !== null) {
                    /** @var MeasurementUnit $unit */
                    $unit = $options['measurement_unit'];
                    return $unit->isUseSIPrefix();
                }
                return true;
            },
            'is_integer' => function (Options $options) {
                if ($options['measurement_unit'] !== null) {
                    /** @var MeasurementUnit $unit */
                    $unit = $options['measurement_unit'];
                    return $unit->isInteger();
                }
                return true;
            },
            'unit' => function (Options $options) {
                if ($options['measurement_unit'] !== null) {
                    /** @var MeasurementUnit $unit */
                    $unit = $options['measurement_unit'];
                    return $unit->getUnit();
                }
                return '';
            },
            'decimals' => 2,
            'error_mapping' => [ '.' => 'value']
        ]);

        $resolver->setAllowedTypes('decimals', 'int');

        $resolver->setNormalizer('decimals', function (Options $options, $value) {
            // If the unit is integer based, then dont show any decimals
            if ($options['is_integer']) {
                return 0;
            }
            return $value;
        });
    }

    /**
     * Formats the given value using the measurement unit and options.
     * @param $value float|int The value that should be formatted. Must be numeric.
     * @param MeasurementUnit|null $unit The measurement unit, whose unit symbol should be used for formatting.
     *              If set to null, it is assumed that the part amount is measured in pieces.
     * @param array $options
     * @return string The formatted string
     * @throws \InvalidArgumentException Thrown if $value is not numeric.
     */
    public function format($value, ?MeasurementUnit $unit = null, array $options = [])
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('$value must be an numeric value!');
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
        $format_string = '%.' . $options['decimals'] . 'f ' . $options['unit'];
        return sprintf($format_string, $value);
    }
}