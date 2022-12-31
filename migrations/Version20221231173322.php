<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221231173322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device_parts ADD price_currency_id INT DEFAULT NULL, ADD name VARCHAR(255) DEFAULT NULL, ADD comment LONGTEXT NOT NULL, ADD price NUMERIC(11, 5) DEFAULT NULL COMMENT \'(DC2Type:big_decimal)\', ADD last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, ADD datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE quantity quantity DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE device_parts ADD CONSTRAINT FK_AFC547993FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_AFC547993FFDCD60 ON device_parts (price_currency_id)');
        $this->addSql('ALTER TABLE devices ADD status VARCHAR(64) DEFAULT NULL, ADD description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE groups CHANGE permissions_data permissions_data LONGTEXT DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE parts ADD built_project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES devices (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON parts (built_project_id)');
        $this->addSql('ALTER TABLE users CHANGE permissions_data permissions_data LONGTEXT DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devices DROP status, DROP description');
        $this->addSql('ALTER TABLE device_parts DROP FOREIGN KEY FK_AFC547993FFDCD60');
        $this->addSql('DROP INDEX IDX_AFC547993FFDCD60 ON device_parts');
        $this->addSql('ALTER TABLE device_parts DROP price_currency_id, DROP name, DROP comment, DROP price, DROP last_modified, DROP datetime_added, CHANGE quantity quantity INT NOT NULL');
        $this->addSql('ALTER TABLE `groups` CHANGE permissions_data permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FEE8AE70D9');
        $this->addSql('DROP INDEX UNIQ_6940A7FEE8AE70D9 ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP built_project_id');
        $this->addSql('ALTER TABLE `users` CHANGE permissions_data permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
