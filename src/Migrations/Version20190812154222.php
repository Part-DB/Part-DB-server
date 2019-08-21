<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190812154222 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE `measurement_units` (id INT AUTO_INCREMENT NOT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer TINYINT(1) NOT NULL, use_si_prefix TINYINT(1) NOT NULL, comment LONGTEXT NOT NULL, parent_id INT DEFAULT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME NOT NULL, datetime_added DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE part_lots (id INT AUTO_INCREMENT NOT NULL, id_store_location INT DEFAULT NULL, id_part INT DEFAULT NULL, description LONGTEXT NOT NULL, comment LONGTEXT NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown TINYINT(1) NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill TINYINT(1) NOT NULL, last_modified DATETIME NOT NULL, datetime_added DATETIME NOT NULL, INDEX IDX_EBC8F9435D8F4B37 (id_store_location), INDEX IDX_EBC8F943C22F6CC4 (id_part), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE currencies (id INT AUTO_INCREMENT NOT NULL, iso_code VARCHAR(255) NOT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL, comment LONGTEXT NOT NULL, parent_id INT DEFAULT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME NOT NULL, datetime_added DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES `storelocations` (id)');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');

        /** Migrate the part locations for parts with known instock */
        $this->addSql(
            'INSERT INTO part_lots (id_part, id_store_location, amount, instock_unknown, last_modified, datetime_added) ' .
                'SELECT parts.id, parts.id_storelocation,  parts.instock, 0, NOW(), NOW() FROM parts ' .
                'WHERE parts.instock >= 0'
        );

        //Migrate part locations for parts with unknown instock
        $this->addSql(
            'INSERT INTO part_lots (id_part, id_store_location, amount, instock_unknown, last_modified, datetime_added) ' .
            'SELECT parts.id, parts.id_storelocation, 0, 1, NOW(), NOW() FROM parts ' .
            'WHERE parts.instock = -2'
        );

        $this->addSql('ALTER TABLE attachements CHANGE element_id element_id INT DEFAULT NULL, CHANGE type_id type_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE attachement_types ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE categories ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE devices ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE footprints ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL, CHANGE filename_3d filename_3d MEDIUMTEXT NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE manufacturers ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE storelocations ADD storage_type_id INT DEFAULT NULL, ADD only_single_part TINYINT(1) NOT NULL, ADD limit_to_existing_parts TINYINT(1) NOT NULL, ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE storelocations ADD CONSTRAINT FK_7517020B270BFF1 FOREIGN KEY (storage_type_id) REFERENCES `measurement_units` (id)');
        $this->addSql('CREATE INDEX IDX_7517020B270BFF1 ON storelocations (storage_type_id)');
        $this->addSql('ALTER TABLE suppliers ADD default_currency_id INT DEFAULT NULL, ADD shipping_costs NUMERIC(11, 5) DEFAULT NULL, ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON suppliers (default_currency_id)');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY parts_id_storelocation_fk');
        $this->addSql('DROP INDEX IDX_6940A7FE8DF69834 ON parts');
        $this->addSql('ALTER TABLE parts ADD id_part_unit INT DEFAULT NULL, CHANGE mininstock minamount DOUBLE PRECISION NOT NULL, ADD manufacturer_product_number VARCHAR(255) NOT NULL, ADD needs_review TINYINT(1) NOT NULL, ADD tags LONGTEXT NOT NULL, ADD mass DOUBLE PRECISION DEFAULT NULL, DROP id_storelocation, DROP instock, CHANGE id_category id_category INT DEFAULT NULL, CHANGE id_footprint id_footprint INT DEFAULT NULL, CHANGE order_orderdetails_id order_orderdetails_id INT DEFAULT NULL, CHANGE id_manufacturer id_manufacturer INT DEFAULT NULL, CHANGE id_master_picture_attachement id_master_picture_attachement INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES `measurement_units` (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON parts (id_part_unit)');
        $this->addSql('ALTER TABLE users CHANGE group_id group_id INT DEFAULT NULL, CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE first_name first_name VARCHAR(255) DEFAULT NULL, CHANGE last_name last_name VARCHAR(255) DEFAULT NULL, CHANGE department department VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE config_language config_language VARCHAR(255) DEFAULT NULL, CHANGE config_timezone config_timezone VARCHAR(255) DEFAULT NULL, CHANGE config_theme config_theme VARCHAR(255) DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE device_parts CHANGE id_part id_part INT DEFAULT NULL, CHANGE id_device id_device INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails CHANGE part_id part_id INT DEFAULT NULL, CHANGE id_supplier id_supplier INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE pricedetails ADD id_currency INT DEFAULT NULL, CHANGE orderdetails_id orderdetails_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE pricedetails ADD CONSTRAINT FK_C68C4459398D64AA FOREIGN KEY (id_currency) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON pricedetails (id_currency)');
        $this->addSql('ALTER TABLE groups ADD not_selectable TINYINT(1) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_7517020B270BFF1');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE2626CEF9');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95CECD792C0');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C4459398D64AA');
        $this->addSql('DROP TABLE `measurement_units`');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('ALTER TABLE `attachement_types` DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `attachements` CHANGE type_id type_id INT DEFAULT NULL, CHANGE element_id element_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `categories` DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `device_parts` CHANGE id_device id_device INT DEFAULT NULL, CHANGE id_part id_part INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `devices` DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `footprints` DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE filename_3d filename_3d MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `groups` DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `manufacturers` DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `orderdetails` CHANGE part_id part_id INT DEFAULT NULL, CHANGE id_supplier id_supplier INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('DROP INDEX IDX_6940A7FE2626CEF9 ON `parts`');
        $this->addSql('ALTER TABLE `parts` ADD id_storelocation INT DEFAULT NULL, ADD instock INT NOT NULL, DROP id_part_unit, DROP minamount, DROP manufacturer_product_number, DROP needs_review, DROP tags, DROP mass, CHANGE id_category id_category INT DEFAULT NULL, CHANGE id_footprint id_footprint INT DEFAULT NULL, CHANGE id_manufacturer id_manufacturer INT DEFAULT NULL, CHANGE id_master_picture_attachement id_master_picture_attachement INT DEFAULT NULL, CHANGE order_orderdetails_id order_orderdetails_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT parts_id_storelocation_fk FOREIGN KEY (id_storelocation) REFERENCES storelocations (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FE8DF69834 ON `parts` (id_storelocation)');
        $this->addSql('DROP INDEX IDX_C68C4459398D64AA ON `pricedetails`');
        $this->addSql('ALTER TABLE `pricedetails` DROP id_currency, CHANGE orderdetails_id orderdetails_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('DROP INDEX IDX_7517020B270BFF1 ON `storelocations`');
        $this->addSql('ALTER TABLE `storelocations` DROP storage_type_id, DROP only_single_part, DROP limit_to_existing_parts, DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('DROP INDEX IDX_AC28B95CECD792C0 ON `suppliers`');
        $this->addSql('ALTER TABLE `suppliers` DROP default_currency_id, DROP shipping_costs, DROP not_selectable, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `users` CHANGE group_id group_id INT DEFAULT NULL, CHANGE password password VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE first_name first_name VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE last_name last_name VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE department department VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE email email VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE config_language config_language VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE config_timezone config_timezone VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE config_theme config_theme VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
    }
}
