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


namespace App\Services\Doctrine;

use App\Doctrine\Functions\Natsort;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service allows to debug the natsort function by showing various information about the current state of
 * the natsort function.
 */
class NatsortDebugHelper
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        // This is a dummy constructor
    }

    /**
     * Check if the slow natural sort is allowed on the Natsort function.
     * If it is not, then the request handler might need to be adjusted.
     * @return bool
     */
    public function isSlowNaturalSortAllowed(): bool
    {
        return Natsort::isSlowNaturalSortAllowed();
    }

    public function getNaturalSortMethod(): string
    {
        //Construct a dummy query which uses the Natsort function
        $query = $this->entityManager->createQuery('SELECT natsort(1) FROM ' . Part::class . ' p');
        $sql = $query->getSQL();
        //Remove the leading SELECT and the trailing semicolon
        $sql = substr($sql, 7, -1);

        //Remove AS and everything afterwards
        $sql = preg_replace('/\s+AS\s+.*/', '', $sql);

        //If just 1 is returned, then we use normal (non-natural sorting)
        if ($sql === '1') {
            return 'Disabled';
        }

        if (str_contains( $sql, 'COLLATE numeric')) {
            return 'Native (PostgreSQL)';
        }

        if (str_contains($sql, 'NATURAL_SORT_KEY')) {
            return 'Native (MariaDB)';
        }

        if (str_contains($sql, 'COLLATE NATURAL_CMP')) {
            return 'Emulation via PHP (SQLite)';
        }

        if (str_contains($sql, 'NatSortKey')) {
            return 'Emulation via custom function (MySQL)';
        }


        return 'Unknown ('.  $sql . ')';
    }
}