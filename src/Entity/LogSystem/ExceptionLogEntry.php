<?php
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

declare(strict_types=1);

namespace App\Entity\LogSystem;

use App\Exceptions\LogEntryObsoleteException;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class ExceptionLogEntry extends AbstractLogEntry
{
    protected string $typeString = 'exception';

    public function __construct()
    {
        parent::__construct();

        throw new LogEntryObsoleteException();
    }

    /**
     * The class name of the exception that caused this log entry.
     */
    public function getExceptionClass(): string
    {
        return $this->extra['t'] ?? 'Unknown Class';
    }

    /**
     * Returns the file where the exception happened.
     */
    public function getFile(): string
    {
        return $this->extra['f'] ?? 'Unknown file';
    }

    /**
     * Returns the line where the exception happened.
     */
    public function getLine(): int
    {
        return $this->extra['l'] ?? -1;
    }

    /**
     * Return the message of the exception.
     */
    public function getMessage(): string
    {
        return $this->extra['m'] ?? 'Unknown message';
    }
}
