<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250321141740 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts ADD id_part_custom_state INT DEFAULT NULL AFTER id_part_unit');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEA3ED1215 FOREIGN KEY (id_part_custom_state) REFERENCES `part_custom_states` (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FEA3ED1215 ON parts (id_part_custom_state)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FEA3ED1215');
        $this->addSql('DROP INDEX IDX_6940A7FEA3ED1215 ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP id_part_custom_state');
    }
}
