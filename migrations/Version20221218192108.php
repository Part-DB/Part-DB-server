<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221218192108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device_parts ADD name VARCHAR(255) NULL, ADD comment LONGTEXT NOT NULL, ADD last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, ADD datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE quantity quantity DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE devices ADD description LONGTEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devices DROP description');
        $this->addSql('ALTER TABLE device_parts DROP name, DROP comment, DROP last_modified, DROP datetime_added, CHANGE quantity quantity INT NOT NULL');
    }
}
