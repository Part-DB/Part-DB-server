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

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class DatabaseUpdatedLogEntry extends AbstractLogEntry
{
    protected string $typeString = 'database_updated';

    public function __construct(string $oldVersion, string $newVersion)
    {
        parent::__construct();
        $this->extra['o'] = $oldVersion;
        $this->extra['n'] = $newVersion;
    }

    /**
     * Checks if the database update was successful.
     */
    public function isSuccessful(): bool
    {
        //We dont save unsuccessful updates now, so just assume it to save space.
        return $this->extra['s'] ?? true;
    }

    /**
     * Gets the database version before update.
     */
    public function getOldVersion(): string
    {
        return (string) ($this->extra['o'] ?? '');
    }

    /**
     * Gets the (target) database version after update.
     */
    public function getNewVersion(): string
    {
        return (string) ($this->extra['n'] ?? '');
    }
}
