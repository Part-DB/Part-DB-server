<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250325073036 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add part_ipn_prefix column to categories table and remove unique constraint from parts table';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories ADD COLUMN part_ipn_prefix VARCHAR(255) NOT NULL DEFAULT \'\'');
        $this->addSql('DROP INDEX UNIQ_6940A7FE3D721C14 ON parts');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `categories` DROP part_ipn_prefix');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON `parts` (ipn)');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__categories AS 
                SELECT 
                    id,
                    parent_id,
                    id_preview_attachment,
                    partname_hint,
                    partname_regex,
                    disable_footprints,
                    disable_manufacturers,
                    disable_autodatasheets,
                    disable_properties,
                    default_description,
                    default_comment,
                    comment,
                    not_selectable,
                    name,
                    last_modified,
                    datetime_added,
                    alternative_names,
                    eda_info_reference_prefix,
                    eda_info_invisible,
                    eda_info_exclude_from_bom,
                    eda_info_exclude_from_board,
                    eda_info_exclude_from_sim,
                    eda_info_kicad_symbol
                FROM categories
        SQL);

        $this->addSql('DROP TABLE categories');

        $this->addSql(<<<'SQL'
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                parent_id INTEGER DEFAULT NULL,
                id_preview_attachment INTEGER DEFAULT NULL,
                partname_hint CLOB NOT NULL,
                partname_regex CLOB NOT NULL,
                part_ipn_prefix VARCHAR(255) DEFAULT '' NOT NULL,
                disable_footprints BOOLEAN NOT NULL,
                disable_manufacturers BOOLEAN NOT NULL,
                disable_autodatasheets BOOLEAN NOT NULL,
                disable_properties BOOLEAN NOT NULL,
                default_description CLOB NOT NULL,
                default_comment CLOB NOT NULL,
                comment CLOB NOT NULL,
                not_selectable BOOLEAN NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                alternative_names CLOB DEFAULT NULL,
                eda_info_reference_prefix VARCHAR(255) DEFAULT NULL,
                eda_info_invisible BOOLEAN DEFAULT NULL,
                eda_info_exclude_from_bom BOOLEAN DEFAULT NULL,
                eda_info_exclude_from_board BOOLEAN DEFAULT NULL,
                eda_info_exclude_from_sim BOOLEAN DEFAULT NULL,
                eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL,
                CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_3AF34668EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO categories (
                id,
                parent_id,
                id_preview_attachment,
                partname_hint,
                partname_regex,
                disable_footprints,
                disable_manufacturers,
                disable_autodatasheets,
                disable_properties,
                default_description,
                default_comment,
                comment,
                not_selectable,
                name,
                last_modified,
                datetime_added,
                alternative_names,
                eda_info_reference_prefix,
                eda_info_invisible,
                eda_info_exclude_from_bom,
                eda_info_exclude_from_board,
                eda_info_exclude_from_sim,
                eda_info_kicad_symbol
            ) SELECT 
                id,
                parent_id,
                id_preview_attachment,
                partname_hint,
                partname_regex,
                disable_footprints,
                disable_manufacturers,
                disable_autodatasheets,
                disable_properties,
                default_description,
                default_comment,
                comment,
                not_selectable,
                name,
                last_modified,
                datetime_added,
                alternative_names,
                eda_info_reference_prefix,
                eda_info_invisible,
                eda_info_exclude_from_bom,
                eda_info_exclude_from_board,
                eda_info_exclude_from_sim,
                eda_info_kicad_symbol
            FROM __temp__categories
        SQL);

        $this->addSql('DROP TABLE __temp__categories');

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3AF34668EA7100A1 ON categories (id_preview_attachment)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX category_idx_name ON categories (name)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX category_idx_parent_name ON categories (parent_id, name)
        SQL);
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__categories AS 
                SELECT 
                    id,
                    parent_id,
                    id_preview_attachment,
                    partname_hint,
                    partname_regex,
                    disable_footprints,
                    disable_manufacturers,
                    disable_autodatasheets,
                    disable_properties,
                    default_description,
                    default_comment,
                    comment,
                    not_selectable,
                    name,
                    last_modified,
                    datetime_added,
                    alternative_names,
                    eda_info_reference_prefix,
                    eda_info_invisible,
                    eda_info_exclude_from_bom,
                    eda_info_exclude_from_board,
                    eda_info_exclude_from_sim,
                    eda_info_kicad_symbol
                FROM categories
        SQL);

        $this->addSql('DROP TABLE categories');

        $this->addSql(<<<'SQL'
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                parent_id INTEGER DEFAULT NULL,
                id_preview_attachment INTEGER DEFAULT NULL,
                partname_hint CLOB NOT NULL,
                partname_regex CLOB NOT NULL,
                disable_footprints BOOLEAN NOT NULL,
                disable_manufacturers BOOLEAN NOT NULL,
                disable_autodatasheets BOOLEAN NOT NULL,
                disable_properties BOOLEAN NOT NULL,
                default_description CLOB NOT NULL,
                default_comment CLOB NOT NULL,
                comment CLOB NOT NULL,
                not_selectable BOOLEAN NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                alternative_names CLOB DEFAULT NULL,
                eda_info_reference_prefix VARCHAR(255) DEFAULT NULL,
                eda_info_invisible BOOLEAN DEFAULT NULL,
                eda_info_exclude_from_bom BOOLEAN DEFAULT NULL,
                eda_info_exclude_from_board BOOLEAN DEFAULT NULL,
                eda_info_exclude_from_sim BOOLEAN DEFAULT NULL,
                eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL,
                CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_3AF34668EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO categories (
                id,
                parent_id,
                id_preview_attachment,
                partname_hint,
                partname_regex,
                disable_footprints,
                disable_manufacturers,
                disable_autodatasheets,
                disable_properties,
                default_description,
                default_comment,
                comment,
                not_selectable,
                name,
                last_modified,
                datetime_added,
                alternative_names,
                eda_info_reference_prefix,
                eda_info_invisible,
                eda_info_exclude_from_bom,
                eda_info_exclude_from_board,
                eda_info_exclude_from_sim,
                eda_info_kicad_symbol
            ) SELECT 
                id,
                parent_id,
                id_preview_attachment,
                partname_hint,
                partname_regex,
                disable_footprints,
                disable_manufacturers,
                disable_autodatasheets,
                disable_properties,
                default_description,
                default_comment,
                comment,
                not_selectable,
                name,
                last_modified,
                datetime_added,
                alternative_names,
                eda_info_reference_prefix,
                eda_info_invisible,
                eda_info_exclude_from_bom,
                eda_info_exclude_from_board,
                eda_info_exclude_from_sim,
                eda_info_kicad_symbol
            FROM __temp__categories
        SQL);

        $this->addSql('DROP TABLE __temp__categories');

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3AF34668EA7100A1 ON categories (id_preview_attachment)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX category_idx_name ON categories (name)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX category_idx_parent_name ON categories (parent_id, name)
        SQL);
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE categories ADD part_ipn_prefix VARCHAR(255) DEFAULT '' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_6940a7fe3d721c14
        SQL);
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE "categories" DROP part_ipn_prefix
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_6940a7fe3d721c14 ON "parts" (ipn)
        SQL);
    }
}
