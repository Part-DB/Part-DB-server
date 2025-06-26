<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250624095045 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add IPN to assemblies';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies ADD ipn VARCHAR(100) DEFAULT NULL AFTER status
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_5F3832C03D721C14 ON assemblies (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX assembly_idx_ipn ON assemblies (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries RENAME INDEX idx_8c74887e2f180363 TO IDX_8C74887E4AD2039E
        SQL);
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_5F3832C03D721C14 ON assemblies
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX assembly_idx_ipn ON assemblies
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies DROP ipn
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries RENAME INDEX idx_8c74887e4ad2039e TO IDX_8C74887E2F180363
        SQL);
    }

    public function sqLiteUp(Schema $schema): void
    {
        //nothing to do. Done via Version20250304081039
    }

    public function sqLiteDown(Schema $schema): void
    {
        //nothing to do. Done via Version20250304081039
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies ADD ipn VARCHAR(100) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_5F3832C03D721C14 ON assemblies (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX assembly_idx_ipn ON assemblies (ipn)
        SQL);
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_5F3832C03D721C14
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX assembly_idx_ipn
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies DROP ipn
        SQL);
    }
}
