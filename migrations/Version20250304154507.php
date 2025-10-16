<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250304154507 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add built_assembly_id to parts table';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE parts ADD built_assembly_id INT DEFAULT NULL AFTER built_project_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parts ADD CONSTRAINT FK_6940A7FECC660B3C FOREIGN KEY (built_assembly_id) REFERENCES assemblies (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FECC660B3C ON parts (built_assembly_id)
        SQL);

        // reverted in Version20251016124311, because built_assembly_id isn't required after testing time
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FECC660B3C
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_6940A7FECC660B3C ON parts
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `parts` DROP built_assembly_id
        SQL);
    }

    public function sqLiteUp(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required
    }

    public function sqLiteDown(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required
    }

    public function postgreSQLUp(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required
    }

    public function postgreSQLDown(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required
    }
}
