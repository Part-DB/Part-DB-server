<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231129231240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added columns for EDA integration';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories ADD eda_info JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE footprints ADD eda_info JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE parts ADD eda_info JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `categories` DROP eda_info');
        $this->addSql('ALTER TABLE `footprints` DROP eda_info');
        $this->addSql('ALTER TABLE `parts` DROP eda_info');
    }
}
