<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Form\Type\Helper;

use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;

/**
 * This transformer formats small values in scienfitic notation instead of rounding it to 0, like the default
 * NumberFormatter.
 */
class ExponentialNumberTransformer extends NumberToLocalizedStringTransformer
{
    public function __construct(
        private ?int $scale = null,
        ?bool $grouping = false,
        ?int $roundingMode = \NumberFormatter::ROUND_HALFUP,
        protected ?string $locale = null
    ) {
        //Set scale to null, to disable rounding of values
        parent::__construct($scale, $grouping, $roundingMode, $locale);
    }

    /**
     * Transforms a number type into localized number.
     *
     * @param int|float|null $value Number value
     *
     * @throws TransformationFailedException if the given value is not numeric
     *                                       or if the value cannot be transformed
     */
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (!is_numeric($value)) {
            throw new TransformationFailedException('Expected a numeric.');
        }

        //If the value is too small, the number formatter would return 0, therfore use exponential notation for small numbers
        if (abs($value) < 1e-3) {
            $formatter = $this->getScientificNumberFormatter();
        } else {
            $formatter = $this->getNumberFormatter();
        }



        $value = $formatter->format($value);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        // Convert non-breaking and narrow non-breaking spaces to normal ones
        $value = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $value);

        return $value;
    }

    protected function getScientificNumberFormatter(): \NumberFormatter
    {
        $formatter = new \NumberFormatter($this->locale ?? \Locale::getDefault(), \NumberFormatter::SCIENTIFIC);

        if (null !== $this->scale) {
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $this->scale);
            $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, $this->roundingMode);
        }

        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, (int) $this->grouping);

        return $formatter;
    }

    protected function getNumberFormatter(): \NumberFormatter
    {
        $formatter = parent::getNumberFormatter();

        //Unset the fraction digits, as we don't want to round the number
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
        if (null !== $this->scale) {
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $this->scale);
        } else {
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 100);
        }


        return $formatter;
    }
}