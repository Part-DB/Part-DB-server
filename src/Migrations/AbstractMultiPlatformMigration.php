<?php


namespace App\Migrations;


use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;

abstract class AbstractMultiPlatformMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $db_type = $this->connection->getDatabasePlatform()->getName();

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
        $db_type = $this->connection->getDatabasePlatform()->getName();

        switch ($db_type) {
            case 'mysql':
                $this->mySQLDown($schema);
                break;
            case 'sqlite':
                $this->sqLiteDown($schema);
                break;
            default:
                $this->abortIf(true, "Database type '$db_type' is not supported!");
                break;
        }
    }

    /**
     * Gets the legacy Part-DB version number. Returns 0, if target database is not an legacy Part-DB database.
     * @return int
     */
    public function getOldDBVersion(): int
    {
        if ($this->connection->getDatabasePlatform()->getName() !== "mysql") {
            //Old Part-DB version only supported MySQL therefore only
            return 0;
        }

        try {
            return (int) $this->connection->fetchColumn("SELECT keyValue AS version FROM `internal` WHERE `keyName` = 'dbVersion'");
        } catch (DBALException $dBALException) {
            //when the table was not found, we can proceed, because we have an empty DB!
            return 0;
        }
    }

    public function getInitalAdminPW(): string
    {
        //CHANGEME: Improve this
        return '$2y$10$36AnqCBS.YnHlVdM4UQ0oOCV7BjU7NmE0qnAVEex65AyZw1cbcEjq';
    }

    abstract public function mySQLUp(Schema $schema): void;

    abstract public function mySQLDown(Schema $schema): void;

    abstract public function sqLiteUp(Schema $schema): void;

    abstract public function sqLiteDown(Schema $schema): void;
}