<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221204004815 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add IPN to part';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts ADD ipn VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON parts (ipn)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON parts (ipn)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_6940A7FE3D721C14 ON `parts`');
        $this->addSql('DROP INDEX parts_idx_ipn ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP ipn');
    }

    public function sqLiteUp(Schema $schema): void
    {
        // TODO: Implement sqLiteUp() method.
    }

    public function sqLiteDown(Schema $schema): void
    {
        // TODO: Implement sqLiteDown() method.
    }
}
