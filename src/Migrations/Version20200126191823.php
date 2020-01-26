<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200126191823 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Improve the schema of the log table';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE log CHANGE datetime datetime DATETIME NOT NULL, CHANGE level level TINYINT, CHANGE extra extra LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('DROP INDEX id_user ON log');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES `users` (id)');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C56B3CA4B');
        $this->addSql('ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C56B3CA4B');
        $this->addSql('ALTER TABLE log CHANGE datetime datetime DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE level level TINYINT(1) NOT NULL, CHANGE extra extra MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_general_ci`');
        $this->addSql('DROP INDEX idx_8f3f68c56b3ca4b ON log');
        $this->addSql('CREATE INDEX id_user ON log (id_user)');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES `users` (id)');
    }
}
