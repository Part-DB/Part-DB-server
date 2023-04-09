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
     * @return string
     */
    public function logLevelToIconClass(string $logLevel): string
    {
        switch ($logLevel) {
            case LogLevel::DEBUG:
                return 'fa-bug';
            case LogLevel::INFO:
                return 'fa-info';
            case LogLevel::NOTICE:
                return 'fa-flag';
            case LogLevel::WARNING:
                return 'fa-exclamation-circle';
            case LogLevel::ERROR:
                return 'fa-exclamation-triangle';
            case LogLevel::CRITICAL:
                return 'fa-bolt';
            case LogLevel::ALERT:
                return 'fa-radiation';
            case LogLevel::EMERGENCY:
                return 'fa-skull-crossbones';
            default:
                return 'fa-question-circle';
        }
    }

    /**
     * Returns the Bootstrap table color class for the given log level.
     * @param  string  $logLevel The string representation of the log level (one of the LogLevel::* constants)
     * @return string The table color class (one of the 'table-*' classes)
     */
    public function logLevelToTableColorClass(string $logLevel): string
    {

        switch ($logLevel) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::ERROR:
                return 'table-danger';
            case LogLevel::WARNING:
                return 'table-warning';
            case LogLevel::NOTICE:
                return 'table-info';
            default:
                return '';
        }
    }
}