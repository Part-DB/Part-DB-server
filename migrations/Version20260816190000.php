<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260816190000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add global parameter definitions and optional parameter definition references.';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE parameter_definitions (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                normalized_name VARCHAR(255) NOT NULL,
                input_type VARCHAR(16) DEFAULT 'text' NOT NULL,
                choices JSON DEFAULT NULL,
                symbol VARCHAR(20) NOT NULL,
                unit VARCHAR(50) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                UNIQUE INDEX parameter_definition_normalized_name_unique (normalized_name),
                INDEX parameter_definition_name_idx (name),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE parameters ADD definition_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parameters ADD CONSTRAINT FK_69348FED11EA911 FOREIGN KEY (definition_id) REFERENCES parameter_definitions (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_69348FED11EA911 ON parameters (definition_id)');
        $this->addSql('CREATE INDEX parameter_definition_value_idx ON parameters (definition_id, value_text, type, element_id)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP FOREIGN KEY FK_69348FED11EA911');
        $this->addSql('DROP INDEX IDX_69348FED11EA911 ON parameters');
        $this->addSql('DROP INDEX parameter_definition_value_idx ON parameters');
        $this->addSql('ALTER TABLE parameters DROP definition_id');
        $this->addSql('DROP TABLE parameter_definitions');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE parameter_definitions (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                normalized_name VARCHAR(255) NOT NULL,
                input_type VARCHAR(16) DEFAULT 'text' NOT NULL,
                choices CLOB DEFAULT NULL,
                symbol VARCHAR(20) NOT NULL,
                unit VARCHAR(50) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX parameter_definition_name_idx ON parameter_definitions (name)');
        $this->addSql('CREATE UNIQUE INDEX parameter_definition_normalized_name_unique ON parameter_definitions (normalized_name)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__parameters AS SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id, eda_visibility, eda_symbol_visibility FROM parameters');
        $this->addSql('DROP TABLE parameters');
        $this->addSql(<<<'SQL'
            CREATE TABLE parameters (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                symbol VARCHAR(255) NOT NULL,
                value_min DOUBLE PRECISION DEFAULT NULL,
                value_typical DOUBLE PRECISION DEFAULT NULL,
                value_max DOUBLE PRECISION DEFAULT NULL,
                unit VARCHAR(255) NOT NULL,
                value_text VARCHAR(255) NOT NULL,
                param_group VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                type SMALLINT NOT NULL,
                element_id INTEGER NOT NULL,
                eda_visibility BOOLEAN DEFAULT NULL,
                eda_symbol_visibility BOOLEAN DEFAULT NULL,
                definition_id INTEGER DEFAULT NULL,
                CONSTRAINT FK_69348FED11EA911 FOREIGN KEY (definition_id) REFERENCES parameter_definitions (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('INSERT INTO parameters (id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id, eda_visibility, eda_symbol_visibility) SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id, eda_visibility, eda_symbol_visibility FROM __temp__parameters');
        $this->addSql('DROP TABLE __temp__parameters');
        $this->createSQLiteParameterIndexes();
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__parameters AS SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id, eda_visibility, eda_symbol_visibility FROM parameters');
        $this->addSql('DROP TABLE parameters');
        $this->addSql(<<<'SQL'
            CREATE TABLE parameters (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                symbol VARCHAR(255) NOT NULL,
                value_min DOUBLE PRECISION DEFAULT NULL,
                value_typical DOUBLE PRECISION DEFAULT NULL,
                value_max DOUBLE PRECISION DEFAULT NULL,
                unit VARCHAR(255) NOT NULL,
                value_text VARCHAR(255) NOT NULL,
                param_group VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                type SMALLINT NOT NULL,
                element_id INTEGER NOT NULL,
                eda_visibility BOOLEAN DEFAULT NULL,
                eda_symbol_visibility BOOLEAN DEFAULT NULL
            )
        SQL);
        $this->addSql('INSERT INTO parameters (id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id, eda_visibility, eda_symbol_visibility) SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id, eda_visibility, eda_symbol_visibility FROM __temp__parameters');
        $this->addSql('DROP TABLE __temp__parameters');
        $this->addSql('CREATE INDEX parameter_type_element_idx ON parameters (type, element_id)');
        $this->addSql('CREATE INDEX parameter_group_idx ON parameters (param_group)');
        $this->addSql('CREATE INDEX parameter_name_idx ON parameters (name)');
        $this->addSql('CREATE INDEX IDX_69348FE1F1F2A24 ON parameters (element_id)');
        $this->addSql('DROP TABLE parameter_definitions');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE parameter_definitions (
                id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
                name VARCHAR(255) NOT NULL,
                normalized_name VARCHAR(255) NOT NULL,
                input_type VARCHAR(16) DEFAULT 'text' NOT NULL,
                choices JSON DEFAULT NULL,
                symbol VARCHAR(20) NOT NULL,
                unit VARCHAR(50) NOT NULL,
                last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX parameter_definition_name_idx ON parameter_definitions (name)');
        $this->addSql('CREATE UNIQUE INDEX parameter_definition_normalized_name_unique ON parameter_definitions (normalized_name)');
        $this->addSql('ALTER TABLE parameters ADD definition_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parameters ADD CONSTRAINT FK_69348FED11EA911 FOREIGN KEY (definition_id) REFERENCES parameter_definitions (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_69348FED11EA911 ON parameters (definition_id)');
        $this->addSql('CREATE INDEX parameter_definition_value_idx ON parameters (definition_id, value_text, type, element_id)');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP CONSTRAINT FK_69348FED11EA911');
        $this->addSql('DROP INDEX IDX_69348FED11EA911');
        $this->addSql('DROP INDEX parameter_definition_value_idx');
        $this->addSql('ALTER TABLE parameters DROP definition_id');
        $this->addSql('DROP TABLE parameter_definitions');
    }

    private function createSQLiteParameterIndexes(): void
    {
        $this->addSql('CREATE INDEX parameter_type_element_idx ON parameters (type, element_id)');
        $this->addSql('CREATE INDEX parameter_group_idx ON parameters (param_group)');
        $this->addSql('CREATE INDEX parameter_name_idx ON parameters (name)');
        $this->addSql('CREATE INDEX IDX_69348FE1F1F2A24 ON parameters (element_id)');
        $this->addSql('CREATE INDEX IDX_69348FED11EA911 ON parameters (definition_id)');
        $this->addSql('CREATE INDEX parameter_definition_value_idx ON parameters (definition_id, value_text, type, element_id)');
    }
}
