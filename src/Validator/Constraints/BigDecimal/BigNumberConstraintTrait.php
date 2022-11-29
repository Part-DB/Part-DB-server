<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Validator\Constraints\BigDecimal;

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

use function is_array;

trait BigNumberConstraintTrait
{
    private function configureNumberConstraintOptions($options): array
    {
        if (null === $options) {
            $options = [];
        } elseif (!is_array($options)) {
            $options = [$this->getDefaultOption() => $options];
        }

        if (isset($options['propertyPath'])) {
            throw new ConstraintDefinitionException(sprintf('The "propertyPath" option of the "%s" constraint cannot be set.', static::class));
        }

        if (isset($options['value'])) {
            throw new ConstraintDefinitionException(sprintf('The "value" option of the "%s" constraint cannot be set.', static::class));
        }

        $options['value'] = 0;

        return $options;
    }
}