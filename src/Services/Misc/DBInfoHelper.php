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

namespace App\Services\Misc;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service provides db independent information about the database.
 */
class DBInfoHelper
{
    protected Connection $connection;
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
    }

    /**
     * Returns the database type of the used database.
     * @return string|null Returns 'mysql' for MySQL/MariaDB and 'sqlite' for SQLite. Returns null if unknown type
     */
    public function getDatabaseType(): ?string
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return 'mysql';
        }

        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return 'sqlite';
        }

        return null;
    }

    /**
     * Returns the database version of the used database.
     * @return string|null
     * @throws \Doctrine\DBAL\Exception
     */
    public function getDatabaseVersion(): ?string
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return $this->connection->fetchOne('SELECT VERSION()');
        }

        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return $this->connection->fetchOne('SELECT sqlite_version()');
        }

        return null;
    }

    /**
     * Returns the database size in bytes.
     * @return int|null The database size in bytes or null if unknown
     * @throws \Doctrine\DBAL\Exception
     */
    public function getDatabaseSize(): ?int
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            try {
                return $this->connection->fetchOne('SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = DATABASE()');
            } catch (\Doctrine\DBAL\Exception $e) {
                return null;
            }
        }

        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            try {
                return $this->connection->fetchOne('SELECT page_count * page_size as size FROM pragma_page_count(), pragma_page_size();');
            } catch (\Doctrine\DBAL\Exception $e) {
                return null;
            }
        }

        return null;
    }


}