<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260211000000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add eda_visibility nullable boolean column to parameters and orderdetails tables';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD eda_visibility TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE `orderdetails` ADD eda_visibility TINYINT(1) DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN eda_visibility');
        $this->addSql('ALTER TABLE `orderdetails` DROP COLUMN eda_visibility');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD COLUMN eda_visibility BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD COLUMN eda_visibility BOOLEAN DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN eda_visibility');
        $this->addSql('ALTER TABLE orderdetails DROP COLUMN eda_visibility');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD eda_visibility BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD eda_visibility BOOLEAN DEFAULT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN eda_visibility');
        $this->addSql('ALTER TABLE orderdetails DROP COLUMN eda_visibility');
    }
}
