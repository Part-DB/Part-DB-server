<?php

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
}