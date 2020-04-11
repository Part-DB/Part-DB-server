<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200411145120 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE label_profiles (id INT AUTO_INCREMENT NOT NULL, id_preview_attachement INT DEFAULT NULL, comment LONGTEXT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, options_width DOUBLE PRECISION NOT NULL, options_height DOUBLE PRECISION NOT NULL, options_barcode_type VARCHAR(255) NOT NULL, options_barcode_position VARCHAR(255) NOT NULL, options_picture_type VARCHAR(255) NOT NULL, options_picture_position VARCHAR(255) NOT NULL, options_supported_element VARCHAR(255) NOT NULL, options_font VARCHAR(255) NOT NULL, options_lines LONGTEXT NOT NULL, INDEX IDX_C93E9CF56DEDCEC2 (id_preview_attachement), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE label_profiles ADD CONSTRAINT FK_C93E9CF56DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(4) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE label_profiles');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) DEFAULT NULL');
    }
}
