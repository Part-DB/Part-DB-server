<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191214153125 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE u2f_keys (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, key_handle VARCHAR(255) NOT NULL, public_key VARCHAR(255) NOT NULL, certificate LONGTEXT NOT NULL, counter VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_4F4ADB4BA76ED395 (user_id), UNIQUE INDEX user_unique (user_id, key_handle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE u2f_keys ADD CONSTRAINT FK_4F4ADB4BA76ED395 FOREIGN KEY (user_id) REFERENCES `users` (id)');
        $this->addSql('ALTER TABLE `groups` ADD enforce_2fa TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE users ADD google_authenticator_secret VARCHAR(255) DEFAULT NULL, ADD backup_codes LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', ADD backup_codes_generation_date DATETIME DEFAULT NULL, ADD trusted_device_cookie_version INT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE u2f_keys');
        $this->addSql('ALTER TABLE `groups` DROP enforce_2fa');
        $this->addSql('ALTER TABLE `users` DROP google_authenticator_secret, DROP backup_codes, DROP backup_codes_generation_date, DROP trusted_device_cookie_version');
    }
}
