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

namespace App\Entity\LogSystem;

use \Psr\Log\LogLevel as PSRLogLevel;

enum LogLevel: int
{
    case EMERGENCY = 0;
    case ALERT = 1;
    case CRITICAL = 2;
    case ERROR = 3;
    case WARNING = 4;
    case NOTICE = 5;
    case INFO = 6;
    case DEBUG = 7;

    /**
     * Converts the current log level to a PSR-3 log level string.
     * @return string
     */
    public function toPSR3LevelString(): string
    {
        return match ($this) {
            self::EMERGENCY => PSRLogLevel::EMERGENCY,
            self::ALERT => PSRLogLevel::ALERT,
            self::CRITICAL => PSRLogLevel::CRITICAL,
            self::ERROR => PSRLogLevel::ERROR,
            self::WARNING => PSRLogLevel::WARNING,
            self::NOTICE => PSRLogLevel::NOTICE,
            self::INFO => PSRLogLevel::INFO,
            self::DEBUG => PSRLogLevel::DEBUG,
        };
    }

    /**
     * Creates a log level (enum) from a PSR-3 log level string.
     * @param  string  $level
     * @return self
     */
    public static function fromPSR3LevelString(string $level): self
    {
        return match ($level) {
            PSRLogLevel::EMERGENCY => self::EMERGENCY,
            PSRLogLevel::ALERT => self::ALERT,
            PSRLogLevel::CRITICAL => self::CRITICAL,
            PSRLogLevel::ERROR => self::ERROR,
            PSRLogLevel::WARNING => self::WARNING,
            PSRLogLevel::NOTICE => self::NOTICE,
            PSRLogLevel::INFO => self::INFO,
            PSRLogLevel::DEBUG => self::DEBUG,
            default => throw new \InvalidArgumentException("Invalid log level: $level"),
        };
    }

    /**
     * Checks if the current log level is more important than the given one.
     * @param  LogLevel  $other
     * @return bool
     */
    public function moreImportThan(self $other): bool
    {
        //Smaller values are more important
        return $this->value < $other->value;
    }

    /**
     * Checks if the current log level is more important or equal than the given one.
     * @param  LogLevel  $other
     * @return bool
     */
    public function moreImportOrEqualThan(self $other): bool
    {
        //Smaller values are more important
        return $this->value <= $other->value;
    }

    /**
     * Checks if the current log level is less important than the given one.
     * @param  LogLevel  $other
     * @return bool
     */
    public function lessImportThan(self $other): bool
    {
        //Bigger values are less important
        return $this->value > $other->value;
    }

    /**
     * Checks if the current log level is less important or equal than the given one.
     * @param  LogLevel  $other
     * @return bool
     */
    public function lessImportOrEqualThan(self $other): bool
    {
        //Bigger values are less important
        return $this->value >= $other->value;
    }
}
