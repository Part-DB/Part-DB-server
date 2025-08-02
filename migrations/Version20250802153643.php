<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250802153643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bulk info provider import jobs table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bulk_info_provider_import_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name CLOB NOT NULL, part_ids CLOB NOT NULL, field_mappings CLOB NOT NULL, search_results CLOB NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, prefetch_details BOOLEAN NOT NULL, progress CLOB NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_7F58C1EDB03A8386 FOREIGN KEY (created_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7F58C1EDB03A8386 ON bulk_info_provider_import_jobs (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE bulk_info_provider_import_jobs');
    }
}
