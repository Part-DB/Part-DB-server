<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200311204104 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE parameters (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, symbol VARCHAR(255) NOT NULL, value_min DOUBLE PRECISION DEFAULT NULL, value_typical DOUBLE PRECISION DEFAULT NULL, value_max DOUBLE PRECISION DEFAULT NULL, unit VARCHAR(255) NOT NULL, value_text VARCHAR(255) NOT NULL, param_group VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, type SMALLINT NOT NULL, element_id INT NOT NULL, INDEX IDX_69348FE1F1F2A24 (element_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `groups` ADD perms_parts_parameters SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE `users` ADD perms_parts_parameters SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT');

        $sql = 'UPDATE `groups`'.
            'SET perms_parts_parameters = 341 WHERE (id = 1 AND name = "admins") OR (id = 3 AND name = "users");';
        $this->addSql($sql);

        $sql = 'UPDATE `groups`'.
            'SET perms_parts_parameters = 681 WHERE (id = 2 AND name = "readonly");';
        $this->addSql($sql);

        $this->write('<question>[!!!] Permissions were updated! Please check if they fit your expectations!</question>');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE parameters');
        $this->addSql('ALTER TABLE `groups` DROP perms_parts_parameters');
        $this->addSql('ALTER TABLE `users` DROP perms_parts_parameters');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) DEFAULT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->skipIf(true, "Migration not needed for SQLite. Skipping...");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->skipIf(true, "Migration not needed for SQLite. Skipping...");
    }
}
