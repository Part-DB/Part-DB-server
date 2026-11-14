<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260929120000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add barcode size option to label profiles';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD options_barcode_size DOUBLE PRECISION DEFAULT NULL");
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_barcode_size');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_barcode_size DOUBLE PRECISION DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_barcode_size');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD options_barcode_size DOUBLE PRECISION DEFAULT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_barcode_size');
    }
}
