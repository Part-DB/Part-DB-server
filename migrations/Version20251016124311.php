<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20251016124311 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Remove built_assembly_id from parts table';
    }

    public function mySQLUp(Schema $schema): void
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

    public function mySQLDown(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required
    }

    public function sqLiteUp(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required, already removed from Version20250304154507
    }

    public function sqLiteDown(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required, already removed from Version20250304154507
    }

    public function postgreSQLUp(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required, already removed from Version20250304154507
    }

    public function postgreSQLDown(Schema $schema): void
    {
        // nothing do to, built_assembly_id not required, already removed from Version20250304154507
    }
}
