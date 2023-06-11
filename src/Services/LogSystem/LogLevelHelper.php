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

namespace App\Services\LogSystem;

use App\Entity\LogSystem\AbstractLogEntry;
use Psr\Log\LogLevel;

class LogLevelHelper
{
    /**
     * Returns the FontAwesome icon class for the given log level.
     * This returns just the specific icon class (so 'fa-info' for example).
     * @param  string  $logLevel The string representation of the log level (one of the LogLevel::* constants)
     */
    public function logLevelToIconClass(string $logLevel): string
    {
        return match ($logLevel) {
            LogLevel::DEBUG => 'fa-bug',
            LogLevel::INFO => 'fa-info',
            LogLevel::NOTICE => 'fa-flag',
            LogLevel::WARNING => 'fa-exclamation-circle',
            LogLevel::ERROR => 'fa-exclamation-triangle',
            LogLevel::CRITICAL => 'fa-bolt',
            LogLevel::ALERT => 'fa-radiation',
            LogLevel::EMERGENCY => 'fa-skull-crossbones',
            default => 'fa-question-circle',
        };
    }

    /**
     * Returns the Bootstrap table color class for the given log level.
     * @param  string  $logLevel The string representation of the log level (one of the LogLevel::* constants)
     * @return string The table color class (one of the 'table-*' classes)
     */
    public function logLevelToTableColorClass(string $logLevel): string
    {

        return match ($logLevel) {
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR => 'table-danger',
            LogLevel::WARNING => 'table-warning',
            LogLevel::NOTICE => 'table-info',
            default => '',
        };
    }
}