<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231112211329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE part_association (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, other_id INT NOT NULL, type SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_61B952E07E3C61F9 (owner_id), INDEX IDX_61B952E0998D9879 (other_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE part_association ADD CONSTRAINT FK_61B952E07E3C61F9 FOREIGN KEY (owner_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE part_association ADD CONSTRAINT FK_61B952E0998D9879 FOREIGN KEY (other_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE part_lots ADD vendor_barcode VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE part_association DROP FOREIGN KEY FK_61B952E07E3C61F9');
        $this->addSql('ALTER TABLE part_association DROP FOREIGN KEY FK_61B952E0998D9879');
        $this->addSql('DROP TABLE part_association');
        $this->addSql('DROP INDEX part_lots_idx_barcode ON part_lots');
        $this->addSql('ALTER TABLE part_lots DROP vendor_barcode');
    }
}
