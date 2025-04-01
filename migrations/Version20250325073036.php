<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250325073036 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories ADD part_ipn_prefix VARCHAR(255) NOT NULL AFTER partname_regex');
        $this->addSql('DROP INDEX UNIQ_6940A7FE3D721C14 ON parts');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `categories` DROP part_ipn_prefix');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON `parts` (ipn)');
    }
}
