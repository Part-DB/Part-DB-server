<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Entity\LogSystem;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class DatabaseUpdatedLogEntry extends AbstractLogEntry
{
    protected $typeString = 'database_updated';

    public function __construct(string $oldVersion, string $newVersion)
    {
        parent::__construct();
        $this->extra['o'] = $oldVersion;
        $this->extra['n'] = $newVersion;
    }

    /**
     * Checks if the database update was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        //We dont save unsuccessful updates now, so just assume it to save space.
        return $this->extra['s'] ?? true;
    }

    /**
     * Gets the database version before update.
     *
     * @return string
     */
    public function getOldVersion(): string
    {
        return (string) ($this->extra['o'] ?? '');
    }

    /**
     * Gets the (target) database version after update.
     *
     * @return string
     */
    public function getNewVersion(): string
    {
        return (string) ($this->extra['n'] ?? '');
    }
}
