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

namespace App\Tests;

use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\ResettableSettingsInterface;
use InvalidArgumentException;
use ReflectionClass;

class SettingsTestHelper
{
    /**
     * Creates a new dummy settings object for testing purposes.
     * It does not contain any embedded objects!
     * @template T of object
     * @param  string  $class
     * @phpstan-param class-string<T> $class
     * @return object
     * @phpstan-return T
     */
    public static function createSettingsDummy(string $class): object
    {
        $reflection = new ReflectionClass($class);

        //Check if it is a settings class (has a Settings attribute)
        if ($reflection->getAttributes(Settings::class) === []) {
            throw new InvalidArgumentException("The class $class is not a settings class!");
        }

        $object = $reflection->newInstanceWithoutConstructor();

        //If the object has some initialization logic, then call it
        if ($object instanceof ResettableSettingsInterface) {
            $object->resetToDefaultValues();
        }

        return $object;
    }
}