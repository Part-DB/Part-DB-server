<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307204859 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Increase the length of the vendor_barcode field in part_lots to 1000 characters and update the index accordingly';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_lots DROP INDEX part_lots_idx_barcode, ADD INDEX part_lots_idx_barcode (vendor_barcode(100))');
        $this->addSql('ALTER TABLE part_lots CHANGE vendor_barcode vendor_barcode LONGTEXT DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_lots DROP INDEX part_lots_idx_barcode, ADD INDEX part_lots_idx_barcode (vendor_barcode)');
        $this->addSql('ALTER TABLE part_lots CHANGE vendor_barcode vendor_barcode VARCHAR(255) DEFAULT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__part_lots AS SELECT id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added, vendor_barcode, last_stocktake_at FROM part_lots');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, id_owner INTEGER DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, vendor_barcode CLOB DEFAULT NULL, last_stocktake_at DATETIME DEFAULT NULL, CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES storelocations (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F94321E5A74C FOREIGN KEY (id_owner) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO part_lots (id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added, vendor_barcode, last_stocktake_at) SELECT id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added, vendor_barcode, last_stocktake_at FROM __temp__part_lots');
        $this->addSql('DROP TABLE __temp__part_lots');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F94321E5A74C ON part_lots (id_owner)');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__part_lots AS SELECT id, description, comment, expiration_date, instock_unknown, amount, needs_refill, vendor_barcode, last_stocktake_at, last_modified, datetime_added, id_store_location, id_part, id_owner FROM part_lots');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, vendor_barcode VARCHAR(255) DEFAULT NULL, last_stocktake_at DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, id_owner INTEGER DEFAULT NULL, CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F94321E5A74C FOREIGN KEY (id_owner) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO part_lots (id, description, comment, expiration_date, instock_unknown, amount, needs_refill, vendor_barcode, last_stocktake_at, last_modified, datetime_added, id_store_location, id_part, id_owner) SELECT id, description, comment, expiration_date, instock_unknown, amount, needs_refill, vendor_barcode, last_stocktake_at, last_modified, datetime_added, id_store_location, id_part, id_owner FROM __temp__part_lots');
        $this->addSql('DROP TABLE __temp__part_lots');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F94321E5A74C ON part_lots (id_owner)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('DROP INDEX part_lots_idx_barcode');
        $this->addSql('ALTER TABLE part_lots ALTER vendor_barcode TYPE TEXT');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('DROP INDEX part_lots_idx_barcode');
        $this->addSql('ALTER TABLE part_lots ALTER vendor_barcode TYPE VARCHAR(255)');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
    }
}
