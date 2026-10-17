<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260929120000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Create entity tables for planned projects and part lot reservations';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE planned_projects (
                id INT AUTO_INCREMENT NOT NULL,
                id_project INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                comment LONGTEXT NOT NULL,
                number_of_builds INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                built_parts JSON DEFAULT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                INDEX IDX_7D676FF6F12E799E (id_project),
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE planned_projects ADD CONSTRAINT FK_planned_projects_project FOREIGN KEY (id_project) REFERENCES projects (id) ON DELETE SET NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE part_lot_reservations (
                id INT AUTO_INCREMENT NOT NULL,
                id_planned_project INT NOT NULL,
                id_bom_entry INT NOT NULL,
                id_part_lot INT NOT NULL,
                amount DOUBLE PRECISION NOT NULL,
                INDEX IDX_4FF13DBD86C5F452 (id_planned_project),
                INDEX IDX_4FF13DBDE7D84ED6 (id_bom_entry),
                INDEX IDX_4FF13DBD997EE005 (id_part_lot),
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE part_lot_reservations ADD CONSTRAINT FK_part_lot_reservations_planned_project FOREIGN KEY (id_planned_project) REFERENCES planned_projects (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE part_lot_reservations ADD CONSTRAINT FK_part_lot_reservations_bom_entry FOREIGN KEY (id_bom_entry) REFERENCES project_bom_entries (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE part_lot_reservations ADD CONSTRAINT FK_part_lot_reservations_part_lot FOREIGN KEY (id_part_lot) REFERENCES part_lots (id)
        SQL);
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE part_lot_reservations DROP FOREIGN KEY FK_part_lot_reservations_planned_project
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE part_lot_reservations DROP FOREIGN KEY FK_part_lot_reservations_bom_entry
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE part_lot_reservations DROP FOREIGN KEY FK_part_lot_reservations_part_lot
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE part_lot_reservations
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE planned_projects DROP FOREIGN KEY FK_planned_projects_project
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE planned_projects
        SQL);
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "planned_projects" (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                id_project INTEGER DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                comment CLOB NOT NULL,
                number_of_builds INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL,
                built_parts CLOB DEFAULT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                CONSTRAINT FK_planned_projects_project FOREIGN KEY (id_project) REFERENCES "projects" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D676FF6F12E799E ON "planned_projects" (id_project)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE "part_lot_reservations" (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                id_planned_project INTEGER NOT NULL,
                id_bom_entry INTEGER NOT NULL,
                id_part_lot INTEGER NOT NULL,
                amount DOUBLE PRECISION NOT NULL,
                CONSTRAINT FK_part_lot_reservations_planned_project FOREIGN KEY (id_planned_project) REFERENCES "planned_projects" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_part_lot_reservations_bom_entry FOREIGN KEY (id_bom_entry) REFERENCES "project_bom_entries" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_part_lot_reservations_part_lot FOREIGN KEY (id_part_lot) REFERENCES "part_lots" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_4FF13DBD86C5F452 ON "part_lot_reservations" (id_planned_project)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_4FF13DBDE7D84ED6 ON "part_lot_reservations" (id_bom_entry)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_4FF13DBD997EE005 ON "part_lot_reservations" (id_part_lot)
        SQL);
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE "part_lot_reservations"
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "planned_projects"
        SQL);
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "planned_projects" (
                id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
                id_project INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                comment TEXT NOT NULL,
                number_of_builds INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                built_parts JSON DEFAULT NULL,
                last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7D676FF6F12E799E ON "planned_projects" (id_project)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "planned_projects" ADD CONSTRAINT FK_planned_projects_project FOREIGN KEY (id_project) REFERENCES "projects" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE "part_lot_reservations" (
                id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
                id_planned_project INT NOT NULL,
                id_bom_entry INT NOT NULL,
                id_part_lot INT NOT NULL,
                amount DOUBLE PRECISION NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_4FF13DBD86C5F452 ON "part_lot_reservations" (id_planned_project)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_4FF13DBDE7D84ED6 ON "part_lot_reservations" (id_bom_entry)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_4FF13DBD997EE005 ON "part_lot_reservations" (id_part_lot)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "part_lot_reservations" ADD CONSTRAINT FK_part_lot_reservations_planned_project FOREIGN KEY (id_planned_project) REFERENCES "planned_projects" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "part_lot_reservations" ADD CONSTRAINT FK_part_lot_reservations_bom_entry FOREIGN KEY (id_bom_entry) REFERENCES "project_bom_entries" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "part_lot_reservations" ADD CONSTRAINT FK_part_lot_reservations_part_lot FOREIGN KEY (id_part_lot) REFERENCES "part_lots" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE "part_lot_reservations" DROP CONSTRAINT FK_part_lot_reservations_planned_project
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "part_lot_reservations" DROP CONSTRAINT FK_part_lot_reservations_bom_entry
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "part_lot_reservations" DROP CONSTRAINT FK_part_lot_reservations_part_lot
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "part_lot_reservations"
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "planned_projects" DROP CONSTRAINT FK_planned_projects_project
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "planned_projects"
        SQL);
    }
}
