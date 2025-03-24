<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250321075747 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `part_custom_states` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, alternative_names LONGTEXT DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_F552745D727ACA70 (parent_id), INDEX part_custom_state_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE `part_custom_states` ADD CONSTRAINT FK_F552745D727ACA70 FOREIGN KEY (parent_id) REFERENCES `part_custom_states` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `part_custom_states` DROP FOREIGN KEY FK_F552745D727ACA70');
        $this->addSql('DROP TABLE `part_custom_states`');
    }
}
