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

namespace App\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

abstract class AbstractMultiPlatformMigration extends AbstractMigration
{
    public const ADMIN_PW_LENGTH = 10;
    protected string $admin_pw = '';

    protected LoggerInterface $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->logger = $logger;
        parent::__construct($connection, $logger);
    }

    public function up(Schema $schema): void
    {
        $db_type = $this->getDatabaseType();

        switch ($db_type) {
            case 'mysql':
                $this->mySQLUp($schema);
                break;
            case 'sqlite':
                $this->sqLiteUp($schema);
                break;
            default:
                $this->abortIf(true, "Database type '$db_type' is not supported!");
                break;
        }
    }

    public function down(Schema $schema): void
    {
        $db_type = $this->getDatabaseType();

        switch ($db_type) {
            case 'mysql':
                $this->mySQLDown($schema);
                break;
            case 'sqlite':
                $this->sqLiteDown($schema);
                break;
            default:
                $this->abortIf(true, "Database type is not supported!");
                break;
        }
    }

    /**
     * Gets the legacy Part-DB version number. Returns 0, if target database is not an legacy Part-DB database.
     */
    public function getOldDBVersion(): int
    {
        if ('mysql' !== $this->getDatabaseType()) {
            //Old Part-DB version only supported MySQL therefore only
            return 0;
        }

        try {
            $version = $this->connection->fetchOne("SELECT keyValue AS version FROM `internal` WHERE `keyName` = 'dbVersion'");
            if (is_bool($version)) {
                return 0;
            }
            return (int) $version;
        } catch (Exception $dBALException) {
            //when the table was not found, we can proceed, because we have an empty DB!
            return 0;
        }
    }

    /**
     * Returns the hash of a new random password, created for the initial admin user, which can be written to DB.
     * The plaintext version of the password will be outputed to user after this migration.
     */
    public function getInitalAdminPW(): string
    {
        if (empty($this->admin_pw)) {
            if (!empty($_ENV['INITIAL_ADMIN_PW'])) {
                $this->admin_pw = $_ENV['INITIAL_ADMIN_PW'];
            } else {
                $this->admin_pw = substr(md5(random_bytes(10)), 0, static::ADMIN_PW_LENGTH);
            }
        }

        //As we dont have access to container, just use the default PHP pw hash function
        return password_hash($this->admin_pw, PASSWORD_DEFAULT);
    }

    public function postUp(Schema $schema): void
    {
        parent::postUp($schema);

        if (!empty($this->admin_pw)) {
            $this->logger->warning('');
            $this->logger->warning('<bg=yellow;fg=black>The initial password for the "admin" user is: '.$this->admin_pw.'</>');
            $this->logger->warning('');
        }
    }

    /**
     * Checks if a foreign key on a table exists in the database.
     * This method is only supported for MySQL/MariaDB databases yet!
     * @param  string  $table
     * @param  string  $fk_name
     * @return bool Returns true, if the foreign key exists
     * @throws Exception
     */
    public function doesFKExists(string $table, string $fk_name): bool
    {
        $db_type = $this->getDatabaseType();
        if ($db_type !== 'mysql') {
            throw new \RuntimeException('This method is only supported for MySQL/MariaDB databases!');
        }

        $sql = "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$fk_name' AND TABLE_NAME = '$table' AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $result = (int) $this->connection->fetchOne($sql);

        return $result > 0;
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

    abstract public function mySQLUp(Schema $schema): void;

    abstract public function mySQLDown(Schema $schema): void;

    abstract public function sqLiteUp(Schema $schema): void;

    abstract public function sqLiteDown(Schema $schema): void;
}
