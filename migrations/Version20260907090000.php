<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260907090000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add available_amount and available_amount_updated_at columns to the orderdetails table, to store the stock a supplier had for a part and when that was retrieved';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderdetails ADD available_amount DOUBLE PRECISION DEFAULT NULL, ADD available_amount_updated_at DATETIME DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderdetails DROP COLUMN available_amount, DROP COLUMN available_amount_updated_at');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderdetails ADD COLUMN available_amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD COLUMN available_amount_updated_at DATETIME DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderdetails DROP COLUMN available_amount');
        $this->addSql('ALTER TABLE orderdetails DROP COLUMN available_amount_updated_at');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderdetails ADD available_amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD available_amount_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderdetails DROP COLUMN available_amount');
        $this->addSql('ALTER TABLE orderdetails DROP COLUMN available_amount_updated_at');
    }
}
