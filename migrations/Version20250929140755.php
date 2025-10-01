<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250929140755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add designator to assembly_bom_entries for free identifier text';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries ADD designator LONGTEXT NOT NULL AFTER mountnames');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries DROP designator');
    }
}
