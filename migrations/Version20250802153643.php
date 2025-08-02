<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250802153643 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add bulk info provider import jobs table';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bulk_info_provider_import_jobs (id INT AUTO_INCREMENT NOT NULL, name LONGTEXT NOT NULL, part_ids LONGTEXT NOT NULL, field_mappings LONGTEXT NOT NULL, search_results LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, prefetch_details TINYINT(1) NOT NULL, progress LONGTEXT NOT NULL, created_by_id INT NOT NULL, CONSTRAINT FK_7F58C1EDB03A8386 FOREIGN KEY (created_by_id) REFERENCES `users` (id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_7F58C1EDB03A8386 ON bulk_info_provider_import_jobs (created_by_id)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE bulk_info_provider_import_jobs');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bulk_info_provider_import_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name CLOB NOT NULL, part_ids CLOB NOT NULL, field_mappings CLOB NOT NULL, search_results CLOB NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, prefetch_details BOOLEAN NOT NULL, progress CLOB NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_7F58C1EDB03A8386 FOREIGN KEY (created_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7F58C1EDB03A8386 ON bulk_info_provider_import_jobs (created_by_id)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE bulk_info_provider_import_jobs');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bulk_info_provider_import_jobs (id SERIAL PRIMARY KEY NOT NULL, name TEXT NOT NULL, part_ids TEXT NOT NULL, field_mappings TEXT NOT NULL, search_results TEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, prefetch_details BOOLEAN NOT NULL, progress TEXT NOT NULL, created_by_id INT NOT NULL, CONSTRAINT FK_7F58C1EDB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7F58C1EDB03A8386 ON bulk_info_provider_import_jobs (created_by_id)');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE bulk_info_provider_import_jobs');
    }
}
