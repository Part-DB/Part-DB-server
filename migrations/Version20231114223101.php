<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231114223101 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add schema for part associations and vendor barcodes';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE part_association (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, other_id INT NOT NULL, type SMALLINT NOT NULL, other_type VARCHAR(255) DEFAULT NULL, comment LONGTEXT DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_61B952E07E3C61F9 (owner_id), INDEX IDX_61B952E0998D9879 (other_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE part_association ADD CONSTRAINT FK_61B952E07E3C61F9 FOREIGN KEY (owner_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE part_association ADD CONSTRAINT FK_61B952E0998D9879 FOREIGN KEY (other_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE part_lots ADD vendor_barcode VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_association DROP FOREIGN KEY FK_61B952E07E3C61F9');
        $this->addSql('ALTER TABLE part_association DROP FOREIGN KEY FK_61B952E0998D9879');
        $this->addSql('DROP TABLE part_association');
        $this->addSql('DROP INDEX part_lots_idx_barcode ON part_lots');
        $this->addSql('ALTER TABLE part_lots DROP vendor_barcode');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE part_association (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, owner_id INTEGER NOT NULL, other_id INTEGER NOT NULL, type SMALLINT NOT NULL, other_type VARCHAR(255) DEFAULT NULL, comment CLOB DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_61B952E07E3C61F9 FOREIGN KEY (owner_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_61B952E0998D9879 FOREIGN KEY (other_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_61B952E07E3C61F9 ON part_association (owner_id)');
        $this->addSql('CREATE INDEX IDX_61B952E0998D9879 ON part_association (other_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__part_lots AS SELECT id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM part_lots');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, id_owner INTEGER DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, vendor_barcode VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES storelocations (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F94321E5A74C FOREIGN KEY (id_owner) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO part_lots (id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added) SELECT id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM __temp__part_lots');
        $this->addSql('DROP TABLE __temp__part_lots');
        $this->addSql('CREATE INDEX IDX_EBC8F94321E5A74C ON part_lots (id_owner)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE part_association');
        $this->addSql('CREATE TEMPORARY TABLE __temp__part_lots AS SELECT id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM part_lots');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, id_owner INTEGER DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F94321E5A74C FOREIGN KEY (id_owner) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO part_lots (id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added) SELECT id, id_store_location, id_part, id_owner, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM __temp__part_lots');
        $this->addSql('DROP TABLE __temp__part_lots');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F94321E5A74C ON part_lots (id_owner)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
    }
}
