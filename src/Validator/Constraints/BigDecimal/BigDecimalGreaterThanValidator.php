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

namespace App\Validator\Constraints\BigDecimal;


use Brick\Math\BigDecimal;
use Symfony\Component\Validator\Constraints\AbstractComparisonValidator;
use Symfony\Component\Validator\Constraints\GreaterThan;

/**
 * Validates values are greater than the previous (>).
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BigDecimalGreaterThanValidator extends AbstractComparisonValidator
{
    /**
     * {@inheritdoc}
     */
    protected function compareValues($value1, $value2)
    {
        if ($value1 instanceof BigDecimal) {
            $value1 = (string) $value1;
        }

        if ($value2 instanceof BigDecimal) {
            $value2 = (string) $value2;
        }

        return null === $value2 || $value1 > $value2;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode()
    {
        return GreaterThan::TOO_LOW_ERROR;
    }
}