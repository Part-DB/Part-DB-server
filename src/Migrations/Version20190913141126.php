<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190913141126 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `groups` ADD perms_parts_category SMALLINT NOT NULL, ADD perms_parts_minamount SMALLINT NOT NULL, ADD perms_parts_lots SMALLINT NOT NULL, ADD perms_parts_tags SMALLINT NOT NULL, ADD perms_parts_unit SMALLINT NOT NULL, ADD perms_parts_mass SMALLINT NOT NULL, ADD perms_parts_status SMALLINT NOT NULL, ADD perms_parts_mpn SMALLINT NOT NULL, ADD perms_currencies INT NOT NULL, ADD perms_measurement_units INT NOT NULL, DROP perms_parts_instock, DROP perms_parts_mininstock, DROP perms_parts_storelocation');
        $this->addSql('ALTER TABLE users ADD currency_id INT DEFAULT NULL, ADD settings LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', ADD perms_parts_category SMALLINT NOT NULL, ADD perms_parts_minamount SMALLINT NOT NULL, ADD perms_parts_lots SMALLINT NOT NULL, ADD perms_parts_tags SMALLINT NOT NULL, ADD perms_parts_unit SMALLINT NOT NULL, ADD perms_parts_mass SMALLINT NOT NULL, ADD perms_parts_status SMALLINT NOT NULL, ADD perms_parts_mpn SMALLINT NOT NULL, ADD perms_currencies INT NOT NULL, ADD perms_measurement_units INT NOT NULL, DROP config_currency, DROP perms_parts_instock, DROP perms_parts_mininstock, DROP perms_parts_storelocation');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON users (currency_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `groups` ADD perms_parts_instock SMALLINT NOT NULL, ADD perms_parts_mininstock SMALLINT NOT NULL, ADD perms_parts_storelocation SMALLINT NOT NULL, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E938248176');
        $this->addSql('DROP INDEX IDX_1483A5E938248176 ON `users`');
        $this->addSql('ALTER TABLE `users` ADD config_currency VARCHAR(255) NOT NULL COLLATE utf8_general_ci, ADD perms_parts_instock SMALLINT NOT NULL, ADD perms_parts_mininstock SMALLINT NOT NULL, ADD perms_parts_storelocation SMALLINT NOT NULL, DROP currency_id, DROP settings, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units');
    }
}
