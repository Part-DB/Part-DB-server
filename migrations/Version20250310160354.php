<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250310160354 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add assembly_id to project_bom_entries';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries RENAME INDEX idx_8c74887e2f180363 TO IDX_8C74887E4AD2039E');
        $this->addSql('ALTER TABLE project_bom_entries ADD id_assembly INT DEFAULT NULL AFTER id_part');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD314AD2039E FOREIGN KEY (id_assembly) REFERENCES assemblies (id)');
        $this->addSql('CREATE INDEX IDX_1AA2DD314AD2039E ON project_bom_entries (id_assembly)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries RENAME INDEX idx_8c74887e4ad2039e TO IDX_8C74887E2F180363');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_1AA2DD314AD2039E');
        $this->addSql('DROP INDEX IDX_1AA2DD314AD2039E ON project_bom_entries');
        $this->addSql('ALTER TABLE project_bom_entries DROP id_assembly');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__project_bom_entries AS 
                SELECT 
                    id,
                    id_device,
                    id_part,
                    price_currency_id,
                    quantity,
                    mountnames,
                    name,
                    comment,
                    price,
                    last_modified,
                    datetime_added
                FROM project_bom_entries
        SQL);

        $this->addSql('DROP TABLE project_bom_entries');

        $this->addSql(<<<'SQL'
            CREATE TABLE project_bom_entries
            (
                id                INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                id_device         INTEGER        DEFAULT NULL,
                id_assembly       INTEGER        DEFAULT NULL,
                id_part           INTEGER        DEFAULT NULL,
                price_currency_id INTEGER        DEFAULT NULL,
                quantity          DOUBLE PRECISION                         NOT NULL,
                mountnames        CLOB                                     NOT NULL,
                name              VARCHAR(255)   DEFAULT NULL,
                comment           CLOB                                     NOT NULL,
                price             NUMERIC(11, 5) DEFAULT NULL,
                last_modified     DATETIME       DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added    DATETIME       DEFAULT CURRENT_TIMESTAMP NOT NULL,
                CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_1AA2DD314AD2039E FOREIGN KEY (id_assembly) REFERENCES assemblies (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_1AA2DD31C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )            
        SQL);


        $this->addSql(<<<'SQL'
            INSERT INTO project_bom_entries (
                id,
                id_device,
                id_part,
                price_currency_id,                             
                quantity,
                mountnames,
                name,
                comment,
                price,
                last_modified,
                datetime_added
            ) SELECT * FROM __temp__project_bom_entries
        SQL);
        $this->addSql('DROP TABLE __temp__project_bom_entries');

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AA2DD314AD2039E ON project_bom_entries (id_assembly)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)
        SQL);
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        CREATE TEMPORARY TABLE __temp__project_bom_entries AS 
            SELECT 
                id,
                id_device,
                id_part,
                price_currency_id,
                quantity,
                mountnames,
                name,
                comment,
                price,
                last_modified,
                datetime_added
            FROM project_bom_entries
        SQL);

        $this->addSql('DROP TABLE project_bom_entries');

        $this->addSql(<<<'SQL'
        CREATE TABLE project_bom_entries
        (
            id                INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            id_device         INTEGER        DEFAULT NULL,
            id_part           INTEGER        DEFAULT NULL,
            price_currency_id INTEGER        DEFAULT NULL,
            quantity          DOUBLE PRECISION                         NOT NULL,
            mountnames        CLOB                                     NOT NULL,
            name              VARCHAR(255)   DEFAULT NULL,
            comment           CLOB                                     NOT NULL,
            price             NUMERIC(11, 5) DEFAULT NULL,
            last_modified     DATETIME       DEFAULT CURRENT_TIMESTAMP NOT NULL,
            datetime_added    DATETIME       DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_1AA2DD31C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )
        SQL);

        $this->addSql(<<<'SQL'
        INSERT INTO project_bom_entries (
            id,
            id_device,
            id_part,
            price_currency_id,                             
            quantity,
            mountnames,
            name,
            comment,
            price,
            last_modified,
            datetime_added
        ) SELECT * FROM __temp__project_bom_entries
        SQL);

        $this->addSql('DROP TABLE __temp__project_bom_entries');

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)
        SQL);
    }

    public function postgreSQLUp(Schema $schema): void
    {
        //Not needed
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //Not needed
    }
}
