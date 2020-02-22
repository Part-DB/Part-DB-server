<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version1 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates an inital empty database';
    }

    public function up(Schema $schema): void
    {
        try {
            //Check if we can use this migration method:
            $version = (int) $this->connection->fetchColumn("SELECT keyValue AS version FROM `internal` WHERE `keyName` = 'dbVersion'");
            $this->skipIf(true, 'Old Part-DB Database detected! Continue with upgrade...');
        } catch (DBALException $dBALException) {
            //when the table was not found, we can proceed, because we have an empty DB!
        }

        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE `attachment_types` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, filetype_filter LONGTEXT NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_EFAED719727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `categories` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, partname_hint LONGTEXT NOT NULL, partname_regex LONGTEXT NOT NULL, disable_footprints TINYINT(1) NOT NULL, disable_manufacturers TINYINT(1) NOT NULL, disable_autodatasheets TINYINT(1) NOT NULL, disable_properties TINYINT(1) NOT NULL, default_description LONGTEXT NOT NULL, default_comment LONGTEXT NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_3AF34668727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE currencies (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, iso_code VARCHAR(255) NOT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_37C44693727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `devices` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, order_quantity INT NOT NULL, order_only_missing_parts TINYINT(1) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_11074E9A727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `footprints` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, filename LONGTEXT NOT NULL, filename_3d LONGTEXT NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_A34D68A2727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `manufacturers` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_94565B12727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `measurement_units` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer TINYINT(1) NOT NULL, use_si_prefix TINYINT(1) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_F5AF83CF727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `storelocations` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, storage_type_id INT DEFAULT NULL, is_full TINYINT(1) NOT NULL, only_single_part TINYINT(1) NOT NULL, limit_to_existing_parts TINYINT(1) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_7517020727ACA70 (parent_id), INDEX IDX_7517020B270BFF1 (storage_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `suppliers` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, default_currency_id INT DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_AC28B95C727ACA70 (parent_id), INDEX IDX_AC28B95CECD792C0 (default_currency_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `attachments` (id INT AUTO_INCREMENT NOT NULL, type_id INT DEFAULT NULL, element_id INT NOT NULL, show_in_table TINYINT(1) NOT NULL, filename VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, class_name VARCHAR(255) NOT NULL, INDEX IDX_47C4FAD6C54C8C93 (type_id), INDEX IDX_47C4FAD61F1F2A24 (element_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `parts` (id INT AUTO_INCREMENT NOT NULL, id_category INT NOT NULL, id_footprint INT DEFAULT NULL, id_manufacturer INT DEFAULT NULL, id_master_picture_attachement INT DEFAULT NULL, order_orderdetails_id INT DEFAULT NULL, id_part_unit INT DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, minamount DOUBLE PRECISION NOT NULL, comment LONGTEXT NOT NULL, visible TINYINT(1) NOT NULL, favorite TINYINT(1) NOT NULL, order_quantity INT NOT NULL, manual_order TINYINT(1) NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, needs_review TINYINT(1) NOT NULL, tags LONGTEXT NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, INDEX IDX_6940A7FE5697F554 (id_category), INDEX IDX_6940A7FE7E371A10 (id_footprint), INDEX IDX_6940A7FE1ECB93AE (id_manufacturer), INDEX IDX_6940A7FEEBBCC786 (id_master_picture_attachement), UNIQUE INDEX UNIQ_6940A7FE81081E9B (order_orderdetails_id), INDEX IDX_6940A7FE2626CEF9 (id_part_unit), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `users` (id INT AUTO_INCREMENT NOT NULL, group_id INT DEFAULT NULL, name VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, need_pw_change TINYINT(1) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_theme VARCHAR(255) DEFAULT NULL, config_currency VARCHAR(255) NOT NULL, config_image_path LONGTEXT NOT NULL, config_instock_comment_w LONGTEXT NOT NULL, config_instock_comment_a LONGTEXT NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INT NOT NULL, perms_groups INT NOT NULL, perms_users INT NOT NULL, perms_self INT NOT NULL, perms_system_config INT NOT NULL, perms_system_database INT NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_instock SMALLINT NOT NULL, perms_parts_mininstock SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_storelocation SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INT NOT NULL, perms_devices_parts INT NOT NULL, perms_storelocations INT NOT NULL, perms_footprints INT NOT NULL, perms_categories INT NOT NULL, perms_suppliers INT NOT NULL, perms_manufacturers INT NOT NULL, perms_attachement_types INT NOT NULL, perms_tools INT NOT NULL, perms_labels INT NOT NULL, UNIQUE INDEX UNIQ_1483A5E95E237E06 (name), INDEX IDX_1483A5E9FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `device_parts` (id INT AUTO_INCREMENT NOT NULL, id_device INT DEFAULT NULL, id_part INT DEFAULT NULL, quantity INT NOT NULL, mountnames LONGTEXT NOT NULL, INDEX IDX_AFC547992F180363 (id_device), INDEX IDX_AFC54799C22F6CC4 (id_part), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE part_lots (id INT AUTO_INCREMENT NOT NULL, id_store_location INT DEFAULT NULL, id_part INT NOT NULL, description LONGTEXT NOT NULL, comment LONGTEXT NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown TINYINT(1) NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill TINYINT(1) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_EBC8F9435D8F4B37 (id_store_location), INDEX IDX_EBC8F943C22F6CC4 (id_part), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `orderdetails` (id INT AUTO_INCREMENT NOT NULL, part_id INT NOT NULL, id_supplier INT DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete TINYINT(1) NOT NULL, supplier_product_url VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_489AFCDC4CE34BEC (part_id), INDEX IDX_489AFCDCCBF180EB (id_supplier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `pricedetails` (id INT AUTO_INCREMENT NOT NULL, orderdetails_id INT NOT NULL, id_currency INT DEFAULT NULL, price NUMERIC(11, 5) NOT NULL, price_related_quantity DOUBLE PRECISION NOT NULL, min_discount_quantity DOUBLE PRECISION NOT NULL, manual_input TINYINT(1) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_C68C44594A01DDC7 (orderdetails_id), INDEX IDX_C68C4459398D64AA (id_currency), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `groups` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INT NOT NULL, perms_groups INT NOT NULL, perms_users INT NOT NULL, perms_self INT NOT NULL, perms_system_config INT NOT NULL, perms_system_database INT NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_instock SMALLINT NOT NULL, perms_parts_mininstock SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_storelocation SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INT NOT NULL, perms_devices_parts INT NOT NULL, perms_storelocations INT NOT NULL, perms_footprints INT NOT NULL, perms_categories INT NOT NULL, perms_suppliers INT NOT NULL, perms_manufacturers INT NOT NULL, perms_attachement_types INT NOT NULL, perms_tools INT NOT NULL, perms_labels INT NOT NULL, INDEX IDX_F06D3970727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `attachment_types` ADD CONSTRAINT FK_EFAED719727ACA70 FOREIGN KEY (parent_id) REFERENCES `attachment_types` (id)');
        $this->addSql('ALTER TABLE `categories` ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id)');
        $this->addSql('ALTER TABLE `devices` ADD CONSTRAINT FK_11074E9A727ACA70 FOREIGN KEY (parent_id) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE `footprints` ADD CONSTRAINT FK_A34D68A2727ACA70 FOREIGN KEY (parent_id) REFERENCES `footprints` (id)');
        $this->addSql('ALTER TABLE `manufacturers` ADD CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES `manufacturers` (id)');
        $this->addSql('ALTER TABLE `measurement_units` ADD CONSTRAINT FK_F5AF83CF727ACA70 FOREIGN KEY (parent_id) REFERENCES `measurement_units` (id)');
        $this->addSql('ALTER TABLE `storelocations` ADD CONSTRAINT FK_7517020727ACA70 FOREIGN KEY (parent_id) REFERENCES `storelocations` (id)');
        $this->addSql('ALTER TABLE `storelocations` ADD CONSTRAINT FK_7517020B270BFF1 FOREIGN KEY (storage_type_id) REFERENCES `measurement_units` (id)');
        $this->addSql('ALTER TABLE `suppliers` ADD CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES `suppliers` (id)');
        $this->addSql('ALTER TABLE `suppliers` ADD CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id)');
        $this->addSql('ALTER TABLE `attachments` ADD CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES `attachment_types` (id)');
        $this->addSql('ALTER TABLE `attachments` ADD CONSTRAINT FK_47C4FAD61F1F2A24 FOREIGN KEY (element_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES `footprints` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES `manufacturers` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FEEBBCC786 FOREIGN KEY (id_master_picture_attachement) REFERENCES `attachments` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES `orderdetails` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES `measurement_units` (id)');
        $this->addSql('ALTER TABLE `users` ADD CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES `groups` (id)');
        $this->addSql('ALTER TABLE `device_parts` ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE `device_parts` ADD CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES `storelocations` (id)');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `orderdetails` ADD CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `orderdetails` ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES `suppliers` (id)');
        $this->addSql('ALTER TABLE `pricedetails` ADD CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES `orderdetails` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `pricedetails` ADD CONSTRAINT FK_C68C4459398D64AA FOREIGN KEY (id_currency) REFERENCES currencies (id)');
        $this->addSql('ALTER TABLE `groups` ADD CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES `groups` (id)');

        /**
         * IGNORE is important here, or we get errors on MySQL 5.7 or higher.
         * TODO: Maybe find a better way (like explicitly define default value), so we can use the strict mode.
         */

        //Create table for user logs:
        $sql = $updateSteps[] = 'CREATE TABLE `log` '.
            '( `id` INT NOT NULL AUTO_INCREMENT , `datetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ,'.
            ' `id_user` INT NOT NULL ,'.
            ' `level` TINYINT NOT NULL ,'.
            ' `type` SMALLINT NOT NULL ,'.
            ' `target_id` INT NOT NULL ,'.
            ' `target_type` SMALLINT NOT NULL ,'.
            ' `extra` MEDIUMTEXT NOT NULL ,'.
            ' PRIMARY KEY (`id`),'.
            ' INDEX (`id_user`)) ENGINE = InnoDB;';
        $this->addSql($sql);
        //Create first groups and users:
        //Add needed groups.
        $sql = <<<'EOD'
                INSERT IGNORE INTO `groups`
                (`id`,`name`,`parent_id`,`comment`,`perms_system`,`perms_groups`,
                `perms_users`,
                `perms_self`,`perms_system_config`,`perms_system_database`,
                `perms_parts`,`perms_parts_name`,`perms_parts_description`,
                `perms_parts_instock`,`perms_parts_mininstock`,
                `perms_parts_footprint`,`perms_parts_storelocation`,
                `perms_parts_manufacturer`,`perms_parts_comment`,
                `perms_parts_order`,`perms_parts_orderdetails`,`perms_parts_prices`
                ,`perms_parts_attachements`,`perms_devices`,`perms_devices_parts`,
                `perms_storelocations`,`perms_footprints`,`perms_categories`,
                `perms_suppliers`,`perms_manufacturers`,`perms_attachement_types`,
                `perms_tools`)
                VALUES (1, 'admins', NULL, 'Users of this group can do everything: Read, Write and Administrative actions.',
                    21, 1365, 87381, 85, 85, 21, 1431655765, 5, 5, 5, 5, 5, 5, 5, 5, 5, 325, 325, 325, 5461, 325, 5461, 5461,
                    5461, 5461, 5461, 1365, 1365),
                (2, 'readonly', NULL, 
                   'Users of this group can only read informations, use tools, and don\'t have access to administrative tools.', 
                    42, 2730, 174762, 154, 170, 42, -1516939607, 9, 9, 9, 9, 9, 9, 9, 9, 9, 649, 649, 649, 1705, 649, 1705, 1705,
                    1705, 1705, 1705, 681, 1366),
                (3, 'users', NULL,
                    'Users of this group, can edit part informations, create new ones, etc. but are not allowed to use administrative tools. (But can read current configuration, and see Server status)',
                    42, 2730, 109226, 89, 105, 41, 1431655765, 5, 5, 5, 5, 5, 5, 5, 5, 5, 325, 325, 325, 5461, 325, 5461, 5461, 5461,
                    5461, 5461, 1365, 1365); 
EOD;
        $this->addSql($sql);
        $admin_pw = '$2y$10$36AnqCBS.YnHlVdM4UQ0oOCV7BjU7NmE0qnAVEex65AyZw1cbcEjq';
        $sql = <<<EOD
            INSERT IGNORE INTO `users`
            (`id`,`name`,`password`,`first_name`,`last_name`,`department`,
             `email`,
             `need_pw_change`,`group_id`,`perms_system`,`perms_groups`,
             `perms_users`,`perms_self`,`perms_system_config`,
             `perms_system_database`,`perms_parts`,`perms_parts_name`,
             `perms_parts_description`,`perms_parts_instock`,
             `perms_parts_mininstock`,`perms_parts_footprint`,
             `perms_parts_storelocation`,`perms_parts_manufacturer`,
             `perms_parts_comment`,`perms_parts_order`,
             `perms_parts_orderdetails`,`perms_parts_prices`,
             `perms_parts_attachements`,`perms_devices`,`perms_devices_parts`,
             `perms_storelocations`,`perms_footprints`,`perms_categories`,
             `perms_suppliers`,`perms_manufacturers`,`perms_attachement_types`,
             `perms_tools`)
              VALUES (1,'anonymous','','','','','',0,2,21844,20480,0,0,0,0,0,21840,21840,
             21840,21840,
             21840,21840,21840,21840,21840,21520,21520,21520,20480,21520,20480,
             20480,20480,20480,20480,21504,20480),
              (
              2,'admin', '${admin_pw}','','',
              '','',1,1,21845,21845,21845,21,85,21,349525,21845,21845,21845,21845
              ,21845,21845,21845,21845,21845,21845,21845,21845,21845,21845,21845,
              21845,21845,21845,21845,21845,21845); 
EOD;
        $this->addSql($sql);
        //Allow users and admins full use of labels. readonly can not write/delete profiles.
        $this->addSql("UPDATE `groups` SET `perms_labels` = '85' WHERE `groups`.`id` = 1;");
        $this->addSql("UPDATE `groups` SET `perms_labels` = '165' WHERE `groups`.`id` = 2;");
        $this->addSql("UPDATE `groups` SET `perms_labels` = '85' WHERE `groups`.`id` = 3;");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `attachment_types` DROP FOREIGN KEY FK_EFAED719727ACA70');
        $this->addSql('ALTER TABLE `attachments` DROP FOREIGN KEY FK_47C4FAD6C54C8C93');
        $this->addSql('ALTER TABLE `categories` DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE5697F554');
        $this->addSql('ALTER TABLE currencies DROP FOREIGN KEY FK_37C44693727ACA70');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95CECD792C0');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C4459398D64AA');
        $this->addSql('ALTER TABLE `devices` DROP FOREIGN KEY FK_11074E9A727ACA70');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE `footprints` DROP FOREIGN KEY FK_A34D68A2727ACA70');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE7E371A10');
        $this->addSql('ALTER TABLE `manufacturers` DROP FOREIGN KEY FK_94565B12727ACA70');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE1ECB93AE');
        $this->addSql('ALTER TABLE `measurement_units` DROP FOREIGN KEY FK_F5AF83CF727ACA70');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_7517020B270BFF1');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE2626CEF9');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_7517020727ACA70');
        $this->addSql('ALTER TABLE part_lots DROP FOREIGN KEY FK_EBC8F9435D8F4B37');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95C727ACA70');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FEEBBCC786');
        $this->addSql('ALTER TABLE `attachments` DROP FOREIGN KEY FK_47C4FAD61F1F2A24');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC54799C22F6CC4');
        $this->addSql('ALTER TABLE part_lots DROP FOREIGN KEY FK_EBC8F943C22F6CC4');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDC4CE34BEC');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE81081E9B');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C44594A01DDC7');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E9FE54D947');
        $this->addSql('ALTER TABLE `groups` DROP FOREIGN KEY FK_F06D3970727ACA70');
        $this->addSql('DROP TABLE `attachment_types`');
        $this->addSql('DROP TABLE `categories`');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('DROP TABLE `devices`');
        $this->addSql('DROP TABLE `footprints`');
        $this->addSql('DROP TABLE `manufacturers`');
        $this->addSql('DROP TABLE `measurement_units`');
        $this->addSql('DROP TABLE `storelocations`');
        $this->addSql('DROP TABLE `suppliers`');
        $this->addSql('DROP TABLE `attachments`');
        $this->addSql('DROP TABLE `parts`');
        $this->addSql('DROP TABLE `users`');
        $this->addSql('DROP TABLE `device_parts`');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('DROP TABLE `orderdetails`');
        $this->addSql('DROP TABLE `pricedetails`');
        $this->addSql('DROP TABLE `groups`');
    }
}
