<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200308204758 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE attachment_types ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE categories ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE currencies ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE devices ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE footprints ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE manufacturers ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE measurement_units ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE storelocations ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE suppliers ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE groups ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE parts ADD specifications JSON NOT NULL COMMENT \'(DC2Type:json_document)\'');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `attachment_types` DROP specifications');
        $this->addSql('ALTER TABLE `categories` DROP specifications');
        $this->addSql('ALTER TABLE currencies DROP specifications');
        $this->addSql('ALTER TABLE `devices` DROP specifications');
        $this->addSql('ALTER TABLE `footprints` DROP specifications');
        $this->addSql('ALTER TABLE `groups` DROP specifications');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE `manufacturers` DROP specifications');
        $this->addSql('ALTER TABLE `measurement_units` DROP specifications');
        $this->addSql('ALTER TABLE `parts` DROP specifications');
        $this->addSql('ALTER TABLE `storelocations` DROP specifications');
        $this->addSql('ALTER TABLE `suppliers` DROP specifications');
    }
}
