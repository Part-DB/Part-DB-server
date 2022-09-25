<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220925162725 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add indices to improve performance';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachments CHANGE type_id type_id INT NOT NULL');
        $this->addSql('CREATE INDEX attachments_idx_id_element_id_class_name ON attachments (id, element_id, class_name)');
        $this->addSql('CREATE INDEX attachments_idx_class_name_id ON attachments (class_name, id)');
        $this->addSql('CREATE INDEX attachment_name_idx ON attachments (name)');
        $this->addSql('CREATE INDEX attachment_element_idx ON attachments (class_name, element_id)');
        $this->addSql('ALTER TABLE categories CHANGE partname_regex partname_regex LONGTEXT NOT NULL, CHANGE partname_hint partname_hint LONGTEXT NOT NULL, CHANGE default_description default_description LONGTEXT NOT NULL, CHANGE default_comment default_comment LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX name_idx ON categories (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON categories (parent_id, name)');
        $this->addSql('ALTER TABLE currencies CHANGE exchange_rate exchange_rate NUMERIC(11, 5) DEFAULT NULL COMMENT \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE INDEX name_idx ON currencies (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON currencies (parent_id, name)');
        $this->addSql('ALTER TABLE device_parts CHANGE mountnames mountnames LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX name_idx ON footprints (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON footprints (parent_id, name)');
        $this->addSql('CREATE INDEX name_idx ON groups (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON groups (parent_id, name)');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(4) NOT NULL');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE INDEX name_idx ON manufacturers (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON manufacturers (parent_id, name)');
        $this->addSql('CREATE INDEX name_idx ON measurement_units (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON measurement_units (parent_id, name)');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON orderdetails (supplierpartnr)');
        $this->addSql('CREATE INDEX parameter_name_idx ON parameters (name)');
        $this->addSql('CREATE INDEX parameter_group_idx ON parameters (param_group)');
        $this->addSql('CREATE INDEX parameter_type_element_idx ON parameters (type, element_id)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('ALTER TABLE parts CHANGE description description LONGTEXT NOT NULL, CHANGE comment comment LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('ALTER TABLE pricedetails CHANGE price price NUMERIC(11, 5) NOT NULL COMMENT \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount ON pricedetails (min_discount_quantity)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount_price_qty ON pricedetails (min_discount_quantity, price_related_quantity)');
        $this->addSql('CREATE INDEX name_idx ON storelocations (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON storelocations (parent_id, name)');
        $this->addSql('ALTER TABLE suppliers CHANGE shipping_costs shipping_costs NUMERIC(11, 5) DEFAULT NULL COMMENT \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE INDEX name_idx ON suppliers (name)');
        $this->addSql('CREATE INDEX parent_name_idx ON suppliers (parent_id, name)');
        $this->addSql('ALTER TABLE users CHANGE config_instock_comment_w config_instock_comment_w LONGTEXT NOT NULL, CHANGE config_instock_comment_a config_instock_comment_a LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX user_idx_username ON users (name)');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX attachments_idx_id_element_id_class_name ON `attachments`');
        $this->addSql('DROP INDEX attachments_idx_class_name_id ON `attachments`');
        $this->addSql('DROP INDEX attachment_name_idx ON `attachments`');
        $this->addSql('DROP INDEX attachment_element_idx ON `attachments`');
        $this->addSql('ALTER TABLE `attachments` CHANGE type_id type_id INT DEFAULT NULL');
        $this->addSql('DROP INDEX name_idx ON `categories`');
        $this->addSql('DROP INDEX parent_name_idx ON `categories`');
        $this->addSql('ALTER TABLE `categories` CHANGE partname_hint partname_hint TEXT NOT NULL, CHANGE partname_regex partname_regex TEXT NOT NULL, CHANGE default_description default_description TEXT NOT NULL, CHANGE default_comment default_comment TEXT NOT NULL');
        $this->addSql('DROP INDEX name_idx ON currencies');
        $this->addSql('DROP INDEX parent_name_idx ON currencies');
        $this->addSql('ALTER TABLE currencies CHANGE exchange_rate exchange_rate NUMERIC(11, 5) DEFAULT NULL');
        $this->addSql('ALTER TABLE `device_parts` CHANGE mountnames mountnames MEDIUMTEXT NOT NULL');
        $this->addSql('DROP INDEX name_idx ON `footprints`');
        $this->addSql('DROP INDEX parent_name_idx ON `footprints`');
        $this->addSql('DROP INDEX name_idx ON `groups`');
        $this->addSql('DROP INDEX parent_name_idx ON `groups`');
        $this->addSql('DROP INDEX log_idx_type ON log');
        $this->addSql('DROP INDEX log_idx_type_target ON log');
        $this->addSql('DROP INDEX log_idx_datetime ON log');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('DROP INDEX name_idx ON `manufacturers`');
        $this->addSql('DROP INDEX parent_name_idx ON `manufacturers`');
        $this->addSql('DROP INDEX name_idx ON `measurement_units`');
        $this->addSql('DROP INDEX parent_name_idx ON `measurement_units`');
        $this->addSql('DROP INDEX orderdetails_supplier_part_nr ON `orderdetails`');
        $this->addSql('DROP INDEX parameter_name_idx ON parameters');
        $this->addSql('DROP INDEX parameter_group_idx ON parameters');
        $this->addSql('DROP INDEX parameter_type_element_idx ON parameters');
        $this->addSql('DROP INDEX parts_idx_datet_name_last_id_needs ON `parts`');
        $this->addSql('DROP INDEX parts_idx_name ON `parts`');
        $this->addSql('ALTER TABLE `parts` CHANGE description description MEDIUMTEXT NOT NULL, CHANGE comment comment MEDIUMTEXT NOT NULL');
        $this->addSql('DROP INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots');
        $this->addSql('DROP INDEX part_lots_idx_needs_refill ON part_lots');
        $this->addSql('DROP INDEX pricedetails_idx_min_discount ON `pricedetails`');
        $this->addSql('DROP INDEX pricedetails_idx_min_discount_price_qty ON `pricedetails`');
        $this->addSql('ALTER TABLE `pricedetails` CHANGE price price NUMERIC(11, 5) NOT NULL');
        $this->addSql('DROP INDEX name_idx ON `storelocations`');
        $this->addSql('DROP INDEX parent_name_idx ON `storelocations`');
        $this->addSql('DROP INDEX name_idx ON `suppliers`');
        $this->addSql('DROP INDEX parent_name_idx ON `suppliers`');
        $this->addSql('ALTER TABLE `suppliers` CHANGE shipping_costs shipping_costs NUMERIC(11, 5) DEFAULT NULL');
        $this->addSql('DROP INDEX user_idx_username ON `users`');
        $this->addSql('ALTER TABLE `users` CHANGE config_instock_comment_a config_instock_comment_a TEXT NOT NULL, CHANGE config_instock_comment_w config_instock_comment_w TEXT NOT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        
    }

    public function sqLiteDown(Schema $schema): void
    {
        // TODO: Implement sqLiteDown() method.
    }
}
