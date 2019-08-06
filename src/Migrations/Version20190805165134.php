<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190805165134 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {

        //Deactive SQL Modes (especially NO_ZERO_DATE, which prevents updating)
        $this->addSql("SET sql_mode = ''");

        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');


        $skip_this_version = false;
        try {
            //Check if we can use this migration method:
            $version = (int)$this->connection->fetchColumn("SELECT keyValue AS version FROM `internal` WHERE `keyName` = 'dbVersion'");

            $this->abortIf($version !== 26, "This database migration can only be used if the database version is 26! Install Part-DB 0.5.6 and update database there!");

        } catch (DBALException $ex) {
            //when the table was not found, then you can not use this migration
            $this->skipIf(true, "Empty database detected. Skip migration.");
        }

        $this->addSql('ALTER TABLE attachements DROP FOREIGN KEY attachements_type_id_fk');
        $this->addSql('ALTER TABLE attachements ADD datetime_added DATETIME NOT NULL, CHANGE type_id type_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE element_id element_id INT DEFAULT NULL, CHANGE filename filename VARCHAR(255) NOT NULL, CHANGE show_in_table show_in_table TINYINT(1) NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE attachements ADD CONSTRAINT FK_212B82DC1F1F2A24 FOREIGN KEY (element_id) REFERENCES `parts` (id)');
        $this->addSql('CREATE INDEX IDX_212B82DC1F1F2A24 ON attachements (element_id)');
        $this->addSql('CREATE INDEX IDX_212B82DCC54C8C93 ON attachements (type_id)');
        $this->addSql('ALTER TABLE attachements ADD CONSTRAINT attachements_type_id_fk FOREIGN KEY (type_id) REFERENCES attachement_types (id)');
        $this->addSql('ALTER TABLE attachement_types DROP FOREIGN KEY attachement_types_parent_id_fk');
        $this->addSql('ALTER TABLE attachement_types CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_9A8C1C77727ACA70 ON attachement_types (parent_id)');
        $this->addSql('ALTER TABLE attachement_types ADD CONSTRAINT attachement_types_parent_id_fk FOREIGN KEY (parent_id) REFERENCES attachement_types (id)');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY categories_parent_id_fk');
        $this->addSql('ALTER TABLE categories CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE disable_footprints disable_footprints TINYINT(1) NOT NULL, CHANGE disable_manufacturers disable_manufacturers TINYINT(1) NOT NULL, CHANGE disable_autodatasheets disable_autodatasheets TINYINT(1) NOT NULL, CHANGE disable_properties disable_properties TINYINT(1) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT categories_parent_id_fk FOREIGN KEY (parent_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE devices DROP FOREIGN KEY devices_parent_id_fk');
        $this->addSql('ALTER TABLE devices CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE order_quantity order_quantity INT NOT NULL, CHANGE order_only_missing_parts order_only_missing_parts TINYINT(1) NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE comment comment LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX IDX_11074E9A727ACA70 ON devices (parent_id)');
        $this->addSql('ALTER TABLE devices ADD CONSTRAINT devices_parent_id_fk FOREIGN KEY (parent_id) REFERENCES devices (id)');
        $this->addSql('ALTER TABLE device_parts CHANGE id_part id_part INT DEFAULT NULL, CHANGE id_device id_device INT DEFAULT NULL, CHANGE quantity quantity INT NOT NULL');
        $this->addSql('ALTER TABLE device_parts ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE device_parts ADD CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');
        $this->addSql('CREATE INDEX IDX_AFC547992F180363 ON device_parts (id_device)');
        $this->addSql('DROP INDEX device_parts_id_part_k ON device_parts');
        $this->addSql('CREATE INDEX IDX_AFC54799C22F6CC4 ON device_parts (id_part)');
        $this->addSql('ALTER TABLE footprints DROP FOREIGN KEY footprints_parent_id_fk');
        $this->addSql('ALTER TABLE footprints CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL, CHANGE filename_3d filename_3d MEDIUMTEXT NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON footprints (parent_id)');
        $this->addSql('ALTER TABLE footprints ADD CONSTRAINT footprints_parent_id_fk FOREIGN KEY (parent_id) REFERENCES footprints (id)');
        $this->addSql('ALTER TABLE `groups` CHANGE name name VARCHAR(255) NOT NULL, CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE perms_labels perms_labels INT NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `groups` ADD CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES `groups` (id)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON `groups` (parent_id)');
        $this->addSql('ALTER TABLE manufacturers DROP FOREIGN KEY manufacturers_parent_id_fk');
        $this->addSql('ALTER TABLE manufacturers CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE address address VARCHAR(255) NOT NULL, CHANGE phone_number phone_number VARCHAR(255) NOT NULL, CHANGE fax_number fax_number VARCHAR(255) NOT NULL, CHANGE email_address email_address VARCHAR(255) NOT NULL, CHANGE website website VARCHAR(255) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(255) NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON manufacturers (parent_id)');
        $this->addSql('ALTER TABLE manufacturers ADD CONSTRAINT manufacturers_parent_id_fk FOREIGN KEY (parent_id) REFERENCES manufacturers (id)');
        $this->addSql('ALTER TABLE orderdetails CHANGE part_id part_id INT DEFAULT NULL, CHANGE id_supplier id_supplier INT DEFAULT NULL, CHANGE supplierpartnr supplierpartnr VARCHAR(255) NOT NULL, CHANGE obsolete obsolete TINYINT(1) NOT NULL, CHANGE supplier_product_url supplier_product_url VARCHAR(255) NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES `suppliers` (id)');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON orderdetails (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON orderdetails (id_supplier)');
        $this->addSql('ALTER TABLE parts DROP INDEX parts_order_orderdetails_id_k, ADD UNIQUE INDEX UNIQ_6940A7FE81081E9B (order_orderdetails_id)');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY parts_id_footprint_fk');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY parts_id_manufacturer_fk');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY parts_id_storelocation_fk');
        $this->addSql('ALTER TABLE parts CHANGE id_footprint id_footprint INT DEFAULT NULL, CHANGE id_storelocation id_storelocation INT DEFAULT NULL, CHANGE order_orderdetails_id order_orderdetails_id INT DEFAULT NULL, CHANGE id_manufacturer id_manufacturer INT DEFAULT NULL, CHANGE id_category id_category INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE instock instock INT NOT NULL, CHANGE mininstock mininstock INT NOT NULL, CHANGE order_quantity order_quantity INT NOT NULL, CHANGE manual_order manual_order TINYINT(1) NOT NULL, CHANGE id_master_picture_attachement id_master_picture_attachement INT DEFAULT NULL, CHANGE manufacturer_product_url manufacturer_product_url VARCHAR(255) NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE favorite favorite TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEEBBCC786 FOREIGN KEY (id_master_picture_attachement) REFERENCES `attachements` (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEBBCC786 ON parts (id_master_picture_attachement)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON parts (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON parts (id_footprint)');
        $this->addSql('DROP INDEX parts_id_storelocation_k ON parts');
        $this->addSql('CREATE INDEX IDX_6940A7FE8DF69834 ON parts (id_storelocation)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON parts (id_manufacturer)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT parts_id_footprint_fk FOREIGN KEY (id_footprint) REFERENCES footprints (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT parts_id_manufacturer_fk FOREIGN KEY (id_manufacturer) REFERENCES manufacturers (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT parts_id_storelocation_fk FOREIGN KEY (id_storelocation) REFERENCES storelocations (id)');
        $this->addSql('ALTER TABLE pricedetails CHANGE orderdetails_id orderdetails_id INT DEFAULT NULL, CHANGE price price NUMERIC(11, 5) NOT NULL, CHANGE price_related_quantity price_related_quantity INT NOT NULL, CHANGE min_discount_quantity min_discount_quantity INT NOT NULL, CHANGE manual_input manual_input TINYINT(1) NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE pricedetails ADD CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES `orderdetails` (id)');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON pricedetails (orderdetails_id)');
        $this->addSql('ALTER TABLE storelocations DROP FOREIGN KEY storelocations_parent_id_fk');
        $this->addSql('ALTER TABLE storelocations CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE is_full is_full TINYINT(1) NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_7517020727ACA70 ON storelocations (parent_id)');
        $this->addSql('ALTER TABLE storelocations ADD CONSTRAINT storelocations_parent_id_fk FOREIGN KEY (parent_id) REFERENCES storelocations (id)');
        $this->addSql('ALTER TABLE suppliers DROP FOREIGN KEY suppliers_parent_id_fk');
        $this->addSql('ALTER TABLE suppliers CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE address address VARCHAR(255) NOT NULL, CHANGE phone_number phone_number VARCHAR(255) NOT NULL, CHANGE fax_number fax_number VARCHAR(255) NOT NULL, CHANGE email_address email_address VARCHAR(255) NOT NULL, CHANGE website website VARCHAR(255) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(255) NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');

        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON suppliers (parent_id)');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT suppliers_parent_id_fk FOREIGN KEY (parent_id) REFERENCES suppliers (id)');
        $this->addSql('ALTER TABLE users CHANGE name name VARCHAR(180) NOT NULL, CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE first_name first_name VARCHAR(255) DEFAULT NULL, CHANGE last_name last_name VARCHAR(255) DEFAULT NULL, CHANGE department department VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE need_pw_change need_pw_change TINYINT(1) NOT NULL, CHANGE group_id group_id INT DEFAULT NULL, CHANGE config_language config_language VARCHAR(255) DEFAULT NULL, CHANGE config_timezone config_timezone VARCHAR(255) DEFAULT NULL, CHANGE config_theme config_theme VARCHAR(255) DEFAULT NULL, CHANGE config_currency config_currency VARCHAR(255) NOT NULL, CHANGE perms_labels perms_labels INT NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES `groups` (id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON users (group_id)');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON users (name)');


        //Drop indizes at last to prevent errors
        $this->addSql('DROP INDEX attachements_element_id_k ON attachements');
        $this->addSql('DROP INDEX attachements_type_id_fk ON attachements');
        $this->addSql('DROP INDEX attachement_types_parent_id_k ON attachement_types');
        $this->addSql('DROP INDEX categories_parent_id_k ON categories');
        $this->addSql('DROP INDEX devices_parent_id_k ON devices');
        $this->addSql('DROP INDEX device_parts_combination_uk ON device_parts');
        $this->addSql('DROP INDEX attachements_class_name_k ON attachements');
        $this->addSql('DROP INDEX device_parts_id_device_k ON device_parts');
        $this->addSql('DROP INDEX footprints_parent_id_k ON footprints');
        $this->addSql('DROP INDEX name ON `groups`');
        $this->addSql('DROP INDEX manufacturers_parent_id_k ON manufacturers');
        $this->addSql('DROP INDEX orderdetails_part_id_k ON orderdetails');
        $this->addSql('DROP INDEX orderdetails_id_supplier_k ON orderdetails');
        $this->addSql('DROP INDEX favorite ON parts');
        $this->addSql('DROP INDEX parts_id_category_k ON parts');
        $this->addSql('DROP INDEX parts_id_footprint_k ON parts');
        $this->addSql('DROP INDEX parts_id_manufacturer_k ON parts');
        $this->addSql('DROP INDEX pricedetails_combination_uk ON pricedetails');
        $this->addSql('DROP INDEX pricedetails_orderdetails_id_k ON pricedetails');
        $this->addSql('DROP INDEX storelocations_parent_id_k ON storelocations');
        $this->addSql('DROP INDEX suppliers_parent_id_k ON suppliers');
        $this->addSql('DROP INDEX name ON users');

        //Set the dbVersion to a high value, to prevent the old Part-DB versions to upgrade DB!
        $this->addSql("UPDATE `internal` SET `keyValue` = '99' WHERE `internal`.`keyName` = 'dbVersion'");

    }

    public function down(Schema $schema) : void
    {
        $this->abortIf(true, 'You can not downgrade now!');

        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `attachement_types` DROP FOREIGN KEY FK_9A8C1C77727ACA70');
        $this->addSql('ALTER TABLE `attachement_types` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_9a8c1c77727aca70 ON `attachement_types`');
        $this->addSql('CREATE INDEX attachement_types_parent_id_k ON `attachement_types` (parent_id)');
        $this->addSql('ALTER TABLE `attachement_types` ADD CONSTRAINT FK_9A8C1C77727ACA70 FOREIGN KEY (parent_id) REFERENCES `attachement_types` (id)');
        $this->addSql('ALTER TABLE `attachements` DROP FOREIGN KEY FK_212B82DC1F1F2A24');
        $this->addSql('ALTER TABLE `attachements` DROP FOREIGN KEY FK_212B82DCC54C8C93');
        $this->addSql('ALTER TABLE `attachements` DROP FOREIGN KEY FK_212B82DC1F1F2A24');
        $this->addSql('ALTER TABLE `attachements` DROP datetime_added, CHANGE type_id type_id INT NOT NULL, CHANGE element_id element_id INT NOT NULL, CHANGE show_in_table show_in_table TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL');
        $this->addSql('CREATE INDEX attachements_class_name_k ON `attachements` (class_name)');
        $this->addSql('DROP INDEX idx_212b82dc1f1f2a24 ON `attachements`');
        $this->addSql('CREATE INDEX attachements_element_id_k ON `attachements` (element_id)');
        $this->addSql('DROP INDEX idx_212b82dcc54c8c93 ON `attachements`');
        $this->addSql('CREATE INDEX attachements_type_id_fk ON `attachements` (type_id)');
        $this->addSql('ALTER TABLE `attachements` ADD CONSTRAINT FK_212B82DCC54C8C93 FOREIGN KEY (type_id) REFERENCES `attachement_types` (id)');
        $this->addSql('ALTER TABLE `attachements` ADD CONSTRAINT FK_212B82DC1F1F2A24 FOREIGN KEY (element_id) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE `categories` DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE `categories` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE disable_footprints disable_footprints TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE disable_manufacturers disable_manufacturers TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE disable_autodatasheets disable_autodatasheets TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE disable_properties disable_properties TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_3af34668727aca70 ON `categories`');
        $this->addSql('CREATE INDEX categories_parent_id_k ON `categories` (parent_id)');
        $this->addSql('ALTER TABLE `categories` ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC54799C22F6CC4');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC54799C22F6CC4');
        $this->addSql('ALTER TABLE `device_parts` CHANGE id_device id_device INT DEFAULT 0 NOT NULL, CHANGE id_part id_part INT DEFAULT 0 NOT NULL, CHANGE quantity quantity INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX device_parts_combination_uk ON `device_parts` (id_part, id_device)');
        $this->addSql('DROP INDEX idx_afc547992f180363 ON `device_parts`');
        $this->addSql('CREATE INDEX device_parts_id_device_k ON `device_parts` (id_device)');
        $this->addSql('DROP INDEX idx_afc54799c22f6cc4 ON `device_parts`');
        $this->addSql('CREATE INDEX device_parts_id_part_k ON `device_parts` (id_part)');
        $this->addSql('ALTER TABLE `device_parts` ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE `device_parts` ADD CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE `devices` DROP FOREIGN KEY FK_11074E9A727ACA70');
        $this->addSql('ALTER TABLE `devices` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE order_quantity order_quantity INT DEFAULT 0 NOT NULL, CHANGE order_only_missing_parts order_only_missing_parts TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_11074e9a727aca70 ON `devices`');
        $this->addSql('CREATE INDEX devices_parent_id_k ON `devices` (parent_id)');
        $this->addSql('ALTER TABLE `devices` ADD CONSTRAINT FK_11074E9A727ACA70 FOREIGN KEY (parent_id) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE `footprints` DROP FOREIGN KEY FK_A34D68A2727ACA70');
        $this->addSql('ALTER TABLE `footprints` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE filename_3d filename_3d MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_a34d68a2727aca70 ON `footprints`');
        $this->addSql('CREATE INDEX footprints_parent_id_k ON `footprints` (parent_id)');
        $this->addSql('ALTER TABLE `footprints` ADD CONSTRAINT FK_A34D68A2727ACA70 FOREIGN KEY (parent_id) REFERENCES `footprints` (id)');
        $this->addSql('ALTER TABLE `groups` DROP FOREIGN KEY FK_F06D3970727ACA70');
        $this->addSql('DROP INDEX IDX_F06D3970727ACA70 ON `groups`');
        $this->addSql('ALTER TABLE `groups` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE comment comment MEDIUMTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(32) NOT NULL COLLATE utf8_general_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL, CHANGE perms_labels perms_labels SMALLINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX name ON `groups` (name)');
        $this->addSql('ALTER TABLE `manufacturers` DROP FOREIGN KEY FK_94565B12727ACA70');
        $this->addSql('ALTER TABLE `manufacturers` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE address address MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE phone_number phone_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE fax_number fax_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE email_address email_address TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE website website TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE auto_product_url auto_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_94565b12727aca70 ON `manufacturers`');
        $this->addSql('CREATE INDEX manufacturers_parent_id_k ON `manufacturers` (parent_id)');
        $this->addSql('ALTER TABLE `manufacturers` ADD CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES `manufacturers` (id)');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDC4CE34BEC');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDC4CE34BEC');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE `orderdetails` CHANGE part_id part_id INT NOT NULL, CHANGE id_supplier id_supplier INT DEFAULT 0 NOT NULL, CHANGE supplierpartnr supplierpartnr TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE obsolete obsolete TINYINT(1) DEFAULT \'0\', CHANGE supplier_product_url supplier_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_489afcdccbf180eb ON `orderdetails`');
        $this->addSql('CREATE INDEX orderdetails_id_supplier_k ON `orderdetails` (id_supplier)');
        $this->addSql('DROP INDEX idx_489afcdc4ce34bec ON `orderdetails`');
        $this->addSql('CREATE INDEX orderdetails_part_id_k ON `orderdetails` (part_id)');
        $this->addSql('ALTER TABLE `orderdetails` ADD CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE `orderdetails` ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES `suppliers` (id)');
        $this->addSql('ALTER TABLE `parts` DROP INDEX UNIQ_6940A7FE81081E9B, ADD INDEX parts_order_orderdetails_id_k (order_orderdetails_id)');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE5697F554');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FEEBBCC786');
        $this->addSql('DROP INDEX IDX_6940A7FEEBBCC786 ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE5697F554');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE7E371A10');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE8DF69834');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE1ECB93AE');
        $this->addSql('ALTER TABLE `parts` CHANGE id_category id_category INT DEFAULT 0 NOT NULL, CHANGE id_footprint id_footprint INT DEFAULT NULL, CHANGE id_storelocation id_storelocation INT DEFAULT NULL, CHANGE id_manufacturer id_manufacturer INT DEFAULT NULL, CHANGE id_master_picture_attachement id_master_picture_attachement INT DEFAULT NULL, CHANGE order_orderdetails_id order_orderdetails_id INT DEFAULT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE name name MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE instock instock INT DEFAULT 0 NOT NULL, CHANGE mininstock mininstock INT DEFAULT 0 NOT NULL, CHANGE favorite favorite TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE order_quantity order_quantity INT DEFAULT 1 NOT NULL, CHANGE manual_order manual_order TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE manufacturer_product_url manufacturer_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('CREATE INDEX favorite ON `parts` (favorite)');
        $this->addSql('DROP INDEX idx_6940a7fe7e371a10 ON `parts`');
        $this->addSql('CREATE INDEX parts_id_footprint_k ON `parts` (id_footprint)');
        $this->addSql('DROP INDEX idx_6940a7fe1ecb93ae ON `parts`');
        $this->addSql('CREATE INDEX parts_id_manufacturer_k ON `parts` (id_manufacturer)');
        $this->addSql('DROP INDEX idx_6940a7fe5697f554 ON `parts`');
        $this->addSql('CREATE INDEX parts_id_category_k ON `parts` (id_category)');
        $this->addSql('DROP INDEX idx_6940a7fe8df69834 ON `parts`');
        $this->addSql('CREATE INDEX parts_id_storelocation_k ON `parts` (id_storelocation)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES `footprints` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE8DF69834 FOREIGN KEY (id_storelocation) REFERENCES `storelocations` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES `manufacturers` (id)');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C44594A01DDC7');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C44594A01DDC7');
        $this->addSql('ALTER TABLE `pricedetails` CHANGE orderdetails_id orderdetails_id INT NOT NULL, CHANGE price price NUMERIC(11, 5) DEFAULT \'NULL\', CHANGE price_related_quantity price_related_quantity INT DEFAULT 1 NOT NULL, CHANGE min_discount_quantity min_discount_quantity INT DEFAULT 1 NOT NULL, CHANGE manual_input manual_input TINYINT(1) DEFAULT \'1\' NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX pricedetails_combination_uk ON `pricedetails` (orderdetails_id, min_discount_quantity)');
        $this->addSql('DROP INDEX idx_c68c44594a01ddc7 ON `pricedetails`');
        $this->addSql('CREATE INDEX pricedetails_orderdetails_id_k ON `pricedetails` (orderdetails_id)');
        $this->addSql('ALTER TABLE `pricedetails` ADD CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES `orderdetails` (id)');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_7517020727ACA70');
        $this->addSql('ALTER TABLE `storelocations` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE is_full is_full TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_7517020727aca70 ON `storelocations`');
        $this->addSql('CREATE INDEX storelocations_parent_id_k ON `storelocations` (parent_id)');
        $this->addSql('ALTER TABLE `storelocations` ADD CONSTRAINT FK_7517020727ACA70 FOREIGN KEY (parent_id) REFERENCES `storelocations` (id)');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95C727ACA70');
        $this->addSql('ALTER TABLE `suppliers` CHANGE parent_id parent_id INT DEFAULT NULL, CHANGE address address MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE phone_number phone_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE fax_number fax_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE email_address email_address TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE website website TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE auto_product_url auto_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('DROP INDEX idx_ac28b95c727aca70 ON `suppliers`');
        $this->addSql('CREATE INDEX suppliers_parent_id_k ON `suppliers` (parent_id)');
        $this->addSql('ALTER TABLE `suppliers` ADD CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES `suppliers` (id)');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E9FE54D947');
        $this->addSql('DROP INDEX IDX_1483A5E9FE54D947 ON `users`');
        $this->addSql('ALTER TABLE `users` CHANGE group_id group_id INT DEFAULT NULL, CHANGE name name VARCHAR(32) NOT NULL COLLATE utf8_general_ci, CHANGE password password VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8_general_ci, CHANGE need_pw_change need_pw_change TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE first_name first_name TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE last_name last_name TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE department department TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE email email TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_language config_language TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_timezone config_timezone TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_theme config_theme TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_currency config_currency TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE last_modified last_modified DATETIME DEFAULT \'\'0000-00-00 00:00:00\'\' NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT \'current_timestamp()\' NOT NULL, CHANGE perms_labels perms_labels SMALLINT NOT NULL');
        $this->addSql('DROP INDEX uniq_1483a5e95e237e06 ON `users`');
        $this->addSql('CREATE UNIQUE INDEX name ON `users` (name)');
    }
}
