<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190829104643 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        //Add timestamps to orderdetails and pricedetails

        $this->addSql('ALTER TABLE attachements CHANGE element_id element_id INT DEFAULT NULL, CHANGE type_id type_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE attachement_types CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE devices CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE device_parts CHANGE id_part id_part INT DEFAULT NULL, CHANGE id_device id_device INT DEFAULT NULL');
        $this->addSql('ALTER TABLE categories CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE footprints CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL, CHANGE filename_3d filename_3d MEDIUMTEXT NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE manufacturers CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE measurement_units CHANGE unit unit VARCHAR(255) DEFAULT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE measurement_units ADD CONSTRAINT FK_F5AF83CF727ACA70 FOREIGN KEY (parent_id) REFERENCES `measurement_units` (id)');
        $this->addSql('CREATE INDEX IDX_F5AF83CF727ACA70 ON measurement_units (parent_id)');
        $this->addSql('ALTER TABLE parts ADD manufacturing_status VARCHAR(255) DEFAULT NULL, CHANGE id_category id_category INT DEFAULT NULL, CHANGE id_footprint id_footprint INT DEFAULT NULL, CHANGE order_orderdetails_id order_orderdetails_id INT DEFAULT NULL, CHANGE id_manufacturer id_manufacturer INT DEFAULT NULL, CHANGE id_master_picture_attachement id_master_picture_attachement INT DEFAULT NULL, CHANGE id_part_unit id_part_unit INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE mass mass DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE part_lots CHANGE id_store_location id_store_location INT DEFAULT NULL, CHANGE id_part id_part INT DEFAULT NULL, CHANGE expiration_date expiration_date DATETIME DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE storelocations CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE storage_type_id storage_type_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE suppliers CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE default_currency_id default_currency_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE shipping_costs shipping_costs NUMERIC(11, 5) DEFAULT NULL');
        $this->addSql('ALTER TABLE currencies CHANGE exchange_rate exchange_rate NUMERIC(11, 5) DEFAULT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('ALTER TABLE orderdetails ADD last_modified DATETIME NOT NULL, CHANGE part_id part_id INT DEFAULT NULL, CHANGE id_supplier id_supplier INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE pricedetails ADD datetime_added DATETIME NOT NULL, CHANGE orderdetails_id orderdetails_id INT DEFAULT NULL, CHANGE id_currency id_currency INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `groups` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE group_id group_id INT DEFAULT NULL, CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE first_name first_name VARCHAR(255) DEFAULT NULL, CHANGE last_name last_name VARCHAR(255) DEFAULT NULL, CHANGE department department VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE config_language config_language VARCHAR(255) DEFAULT NULL, CHANGE config_timezone config_timezone VARCHAR(255) DEFAULT NULL, CHANGE config_theme config_theme VARCHAR(255) DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');

        //Fix typo in attachment table names
        $this->addSql("ALTER TABLE attachements RENAME TO attachments;");
        $this->addSql("ALTER TABLE attachement_types RENAME TO attachment_types;");

        //Fill empty timestamps with current date
        $tables = ["attachments", "attachment_types", "categories", "devices", "footprints", "manufacturers",
            "orderdetails", "pricedetails", "storelocations"];

        foreach ($tables as $table) {
            $this->addSql("UPDATE $table SET datetime_added = NOW() WHERE datetime_added = '0000-00-00 00:00:00'");
            $this->addSql("UPDATE $table SET last_modified = datetime_added WHERE last_modified = '0000-00-00 00:00:00'");
        }

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `attachement_types` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `attachements` CHANGE type_id type_id INT DEFAULT NULL, CHANGE element_id element_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `categories` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE currencies DROP FOREIGN KEY FK_37C44693727ACA70');
        $this->addSql('DROP INDEX IDX_37C44693727ACA70 ON currencies');
        $this->addSql('ALTER TABLE currencies CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE exchange_rate exchange_rate NUMERIC(11, 5) DEFAULT \'NULL\', CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `device_parts` CHANGE id_device id_device INT DEFAULT NULL, CHANGE id_part id_part INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `devices` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `footprints` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE filename_3d filename_3d MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `groups` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `manufacturers` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `measurement_units` DROP FOREIGN KEY FK_F5AF83CF727ACA70');
        $this->addSql('DROP INDEX IDX_F5AF83CF727ACA70 ON `measurement_units`');
        $this->addSql('ALTER TABLE `measurement_units` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE unit unit VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `orderdetails` DROP last_modified, CHANGE part_id part_id INT DEFAULT NULL, CHANGE id_supplier id_supplier INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE part_lots CHANGE id_store_location id_store_location INT DEFAULT NULL, CHANGE id_part id_part INT DEFAULT NULL, CHANGE expiration_date expiration_date DATETIME DEFAULT \'NULL\', CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `parts` DROP manufacturing_status, CHANGE id_category id_category INT DEFAULT NULL, CHANGE id_footprint id_footprint INT DEFAULT NULL, CHANGE id_manufacturer id_manufacturer INT DEFAULT NULL, CHANGE id_master_picture_attachement id_master_picture_attachement INT DEFAULT NULL, CHANGE order_orderdetails_id order_orderdetails_id INT DEFAULT NULL, CHANGE id_part_unit id_part_unit INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE mass mass DOUBLE PRECISION DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE `pricedetails` DROP datetime_added, CHANGE orderdetails_id orderdetails_id INT DEFAULT NULL, CHANGE id_currency id_currency INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `storelocations` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE storage_type_id storage_type_id INT DEFAULT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `suppliers` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE default_currency_id default_currency_id INT DEFAULT NULL, CHANGE shipping_costs shipping_costs NUMERIC(11, 5) DEFAULT \'NULL\', CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `users` CHANGE group_id group_id INT DEFAULT NULL, CHANGE password password VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE first_name first_name VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE last_name last_name VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE department department VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE email email VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE config_language config_language VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE config_timezone config_timezone VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE config_theme config_theme VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
    }
}
