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
namespace App\Services\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service provides db independent information about the database.
 */
class DBInfoHelper
{
    protected Connection $connection;

    public function __construct(protected EntityManagerInterface $entityManager)
    {
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

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return 'sqlite';
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            return 'postgresql';
        }

        return null;
    }

    /**
     * Returns the database version of the used database.
     * @throws Exception
     */
    public function getDatabaseVersion(): ?string
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return $this->connection->fetchOne('SELECT VERSION()');
        }

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return $this->connection->fetchOne('SELECT sqlite_version()');
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            return $this->connection->fetchOne('SELECT version()');
        }

        return null;
    }

    /**
     * Returns the database size in bytes.
     * @return int|null The database size in bytes or null if unknown
     * @throws Exception
     */
    public function getDatabaseSize(): ?int
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            try {
                return (int) $this->connection->fetchOne('SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = DATABASE()');
            } catch (Exception) {
                return null;
            }
        }

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            try {
                return (int) $this->connection->fetchOne('SELECT page_count * page_size as size FROM pragma_page_count(), pragma_page_size();');
            } catch (Exception) {
                return null;
            }
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            try {
                return (int) $this->connection->fetchOne('SELECT pg_database_size(current_database())');
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Returns the name of the database.
     */
    public function getDatabaseName(): ?string
    {
        return $this->connection->getDatabase();
    }

    /**
     * Returns the name of the database user.
     */
    public function getDatabaseUsername(): ?string
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            try {
                return $this->connection->fetchOne('SELECT USER()');
            } catch (Exception) {
                return null;
            }
        }

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return 'sqlite';
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            try {
                return $this->connection->fetchOne('SELECT current_user');
            } catch (Exception) {
                return null;
            }
        }
        
        return null;
    }

}
