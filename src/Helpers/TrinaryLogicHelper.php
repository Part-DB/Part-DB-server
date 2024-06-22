<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Helpers;

/**
 * Helper functions for logic operations with trinary logic.
 * True and false are represented as classical boolean values, undefined is represented as null.
 * @see \App\Tests\Helpers\TrinaryLogicHelperTest
 */
class TrinaryLogicHelper
{

    /**
     * Implements the trinary logic NOT.
     * @param  bool|null  $a
     * @return bool|null
     */
    public static function not(?bool $a): ?bool
    {
        if ($a === null) {
            return null;
        }
        return !$a;
    }


    /**
     * Returns the trinary logic OR of the given parameters. At least one parameter is required.
     * @param  bool|null  ...$args
     * @return bool|null
     */
    public static function or(?bool ...$args): ?bool
    {
        if (count($args) === 0) {
            throw new \LogicException('At least one parameter is required.');
        }

        // The trinary or is the maximum of the integer representation of the parameters.
        return self::intToBool(
            max(array_map(self::boolToInt(...), $args))
        );
    }

    /**
     * Returns the trinary logic AND of the given parameters. At least one parameter is required.
     * @param  bool|null  ...$args
     * @return bool|null
     */
    public static function and(?bool ...$args): ?bool
    {
        if (count($args) === 0) {
            throw new \LogicException('At least one parameter is required.');
        }

        // The trinary and is the minimum of the integer representation of the parameters.
        return self::intToBool(
            min(array_map(self::boolToInt(...), $args))
        );
    }

    /**
     * Convert the trinary bool to an integer, where true is 1, false is -1 and null is 0.
     * @param  bool|null  $a
     * @return int
     */
    private static function boolToInt(?bool $a): int
    {
        if ($a === null) {
            return 0;
        }
        return $a ? 1 : -1;
    }

    /**
     * Convert the integer to a trinary bool, where 1 is true, -1 is false and 0 is null.
     * @param  int  $a
     * @return bool|null
     */
    private static function intToBool(int $a): ?bool
    {
        if ($a === 0) {
            return null;
        }
        return $a > 0;
    }
}