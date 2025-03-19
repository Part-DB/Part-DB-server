<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250304154507 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts ADD built_assembly_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FECC660B3C FOREIGN KEY (built_assembly_id) REFERENCES assemblies (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FECC660B3C ON parts (built_assembly_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FECC660B3C');
        $this->addSql('DROP INDEX UNIQ_6940A7FECC660B3C ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP built_assembly_id');
    }
}
