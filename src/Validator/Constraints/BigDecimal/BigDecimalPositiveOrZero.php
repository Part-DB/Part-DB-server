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

use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NumberConstraintTrait;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class BigDecimalPositiveOrZero extends GreaterThanOrEqual
{
    use NumberConstraintTrait;

    public $message = 'This value should be either positive or zero.';

    public function __construct($options = null)
    {
        parent::__construct($this->configureNumberConstraintOptions($options));
    }

    public function validatedBy(): string
    {
        return BigDecimalGreaterThenOrEqualValidator::class;
    }
}
