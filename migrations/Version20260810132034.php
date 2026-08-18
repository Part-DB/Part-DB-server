<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260810132034 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Make some relations that were never meant to be optional not nullable: project_bom_entries.project, api_tokens.user and orderdetails.supplier are always set by the application, but were nullable in the database. Orphaned rows without the relation are deleted.';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('DELETE FROM project_bom_entries WHERE id_device IS NULL');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE project_bom_entries CHANGE id_device id_device INT NOT NULL');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES projects (id) ON DELETE CASCADE');

        $this->addSql('DELETE FROM api_tokens WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE api_tokens DROP FOREIGN KEY FK_2CAD560EA76ED395');
        $this->addSql('ALTER TABLE api_tokens CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('DELETE FROM orderdetails WHERE id_supplier IS NULL');
        $this->addSql('ALTER TABLE orderdetails DROP FOREIGN KEY FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE orderdetails CHANGE id_supplier id_supplier INT NOT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES suppliers (id)');
    }

    public function mySQLDown(Schema $schema): void
    {
        // We can not restore the deleted orphaned rows here, we can only revert the schema change.
        $this->addSql('ALTER TABLE orderdetails DROP FOREIGN KEY FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE orderdetails CHANGE id_supplier id_supplier INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES suppliers (id)');

        $this->addSql('ALTER TABLE api_tokens DROP FOREIGN KEY FK_2CAD560EA76ED395');
        $this->addSql('ALTER TABLE api_tokens CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');

        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE project_bom_entries CHANGE id_device id_device INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES projects (id)');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('DELETE FROM project_bom_entries WHERE id_device IS NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project_bom_entries AS SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM project_bom_entries');
        $this->addSql('DROP TABLE project_bom_entries');
        $this->addSql(<<<'SQL'
            CREATE TABLE project_bom_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER NOT NULL, id_part INTEGER DEFAULT NULL, price_currency_id INTEGER DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, mountnames CLOB NOT NULL, name VARCHAR(255) DEFAULT NULL, comment CLOB NOT NULL, price NUMERIC(11, 5) DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql('INSERT INTO project_bom_entries (id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added) SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM __temp__project_bom_entries');
        $this->addSql('DROP TABLE __temp__project_bom_entries');
        $this->addSql('CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)');
        $this->addSql('CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)');

        $this->addSql('DELETE FROM api_tokens WHERE user_id IS NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__api_tokens AS SELECT id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added FROM api_tokens');
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql(<<<'SQL'
            CREATE TABLE api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, valid_until DATETIME DEFAULT NULL, token VARCHAR(68) NOT NULL, level SMALLINT NOT NULL, last_time_used DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql('INSERT INTO api_tokens (id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added) SELECT id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added FROM __temp__api_tokens');
        $this->addSql('DROP TABLE __temp__api_tokens');
        $this->addSql('CREATE INDEX IDX_2CAD560EA76ED395 ON api_tokens (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CAD560E5F37A13B ON api_tokens (token)');

        $this->addSql('DELETE FROM orderdetails WHERE id_supplier IS NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__orderdetails AS SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, prices_includes_vat, eda_visibility FROM orderdetails');
        $this->addSql('DROP TABLE orderdetails');
        $this->addSql(<<<'SQL'
            CREATE TABLE orderdetails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER NOT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url CLOB NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, prices_includes_vat BOOLEAN DEFAULT NULL, eda_visibility BOOLEAN DEFAULT NULL, CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES suppliers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql('INSERT INTO orderdetails (id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, prices_includes_vat, eda_visibility) SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, prices_includes_vat, eda_visibility FROM __temp__orderdetails');
        $this->addSql('DROP TABLE __temp__orderdetails');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON orderdetails (id_supplier)');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON orderdetails (part_id)');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON orderdetails (supplierpartnr)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        // We can not restore the deleted orphaned rows here, we can only revert the schema change.
        $this->addSql('CREATE TEMPORARY TABLE __temp__orderdetails AS SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, prices_includes_vat, eda_visibility FROM orderdetails');
        $this->addSql('DROP TABLE orderdetails');
        $this->addSql(<<<'SQL'
            CREATE TABLE orderdetails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url CLOB NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, prices_includes_vat BOOLEAN DEFAULT NULL, eda_visibility BOOLEAN DEFAULT NULL, CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES suppliers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql('INSERT INTO orderdetails (id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, prices_includes_vat, eda_visibility) SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, prices_includes_vat, eda_visibility FROM __temp__orderdetails');
        $this->addSql('DROP TABLE __temp__orderdetails');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON orderdetails (supplierpartnr)');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON orderdetails (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON orderdetails (id_supplier)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__api_tokens AS SELECT id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added FROM api_tokens');
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql(<<<'SQL'
            CREATE TABLE api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, valid_until DATETIME DEFAULT NULL, token VARCHAR(68) NOT NULL, level SMALLINT NOT NULL, last_time_used DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql('INSERT INTO api_tokens (id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added) SELECT id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added FROM __temp__api_tokens');
        $this->addSql('DROP TABLE __temp__api_tokens');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CAD560E5F37A13B ON api_tokens (token)');
        $this->addSql('CREATE INDEX IDX_2CAD560EA76ED395 ON api_tokens (user_id)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__project_bom_entries AS SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM project_bom_entries');
        $this->addSql('DROP TABLE project_bom_entries');
        $this->addSql(<<<'SQL'
            CREATE TABLE project_bom_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, price_currency_id INTEGER DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, mountnames CLOB NOT NULL, name VARCHAR(255) DEFAULT NULL, comment CLOB NOT NULL, price NUMERIC(11, 5) DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql('INSERT INTO project_bom_entries (id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added) SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM __temp__project_bom_entries');
        $this->addSql('DROP TABLE __temp__project_bom_entries');
        $this->addSql('CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)');
        $this->addSql('CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)');
        $this->addSql('CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('DELETE FROM project_bom_entries WHERE id_device IS NULL');
        $this->addSql('ALTER TABLE project_bom_entries DROP CONSTRAINT FK_1AA2DD312F180363');
        $this->addSql('ALTER TABLE project_bom_entries ALTER id_device SET NOT NULL');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('DELETE FROM api_tokens WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE api_tokens DROP CONSTRAINT FK_2CAD560EA76ED395');
        $this->addSql('ALTER TABLE api_tokens ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('DELETE FROM orderdetails WHERE id_supplier IS NULL');
        $this->addSql('ALTER TABLE orderdetails DROP CONSTRAINT FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE orderdetails ALTER id_supplier SET NOT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES suppliers (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        // We can not restore the deleted orphaned rows here, we can only revert the schema change.
        $this->addSql('ALTER TABLE orderdetails DROP CONSTRAINT FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE orderdetails ALTER id_supplier DROP NOT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES suppliers (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE api_tokens DROP CONSTRAINT FK_2CAD560EA76ED395');
        $this->addSql('ALTER TABLE api_tokens ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE project_bom_entries DROP CONSTRAINT FK_1AA2DD312F180363');
        $this->addSql('ALTER TABLE project_bom_entries ALTER id_device DROP NOT NULL');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
