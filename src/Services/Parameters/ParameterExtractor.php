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

declare(strict_types=1);

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

namespace App\Services\Parameters;

use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\PartParameter;
use InvalidArgumentException;

use function preg_match;

class ParameterExtractor
{
    protected const ALLOWED_PARAM_SEPARATORS = [', ', "\n"];

    protected const CHAR_LIMIT = 1000;

    /**
     * Tries to extract parameters from the given string.
     * Useful for extraction from part description and comment.
     *
     * @return AbstractParameter[]
     */
    public function extractParameters(string $input, string $class = PartParameter::class): array
    {
        if (!is_a($class, AbstractParameter::class, true)) {
            throw new InvalidArgumentException('$class must be a child class of AbstractParameter!');
        }

        //Restrict search length
        $input = mb_strimwidth($input, 0, self::CHAR_LIMIT);

        $parameters = [];

        //Try to split the input string into different sub strings each containing a single parameter
        $split = $this->splitString($input);
        foreach ($split as $param_string) {
            $tmp = $this->stringToParam($param_string, $class);
            if ($tmp instanceof AbstractParameter) {
                $parameters[] = $tmp;
            }
        }

        return $parameters;
    }

    protected function stringToParam(string $input, string $class): ?AbstractParameter
    {
        $input = trim($input);
        $regex = '/^(.*) *(?:=|:) *(.+)/u';

        $matches = [];
        preg_match($regex, $input, $matches);
        if ($matches !== []) {
            [, $name, $value] = $matches;
            $value = trim($value);

            //Don't allow empty names or values (these are a sign of an invalid extracted string)
            if (empty($name) || empty($value)) {
                return null;
            }

            /** @var AbstractParameter $parameter */
            $parameter = new $class();
            $parameter->setName(trim($name));
            $parameter->setValueText($value);

            return $parameter;
        }

        return null;
    }

    protected function splitString(string $input): array
    {
        //Allow comma as limiter (include space, to prevent splitting in german style numbers)
        $input = str_replace(static::ALLOWED_PARAM_SEPARATORS, ';', $input);

        return explode(';', $input);
    }
}
