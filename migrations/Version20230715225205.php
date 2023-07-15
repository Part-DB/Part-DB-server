<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230715225205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE oauth_tokens (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', refresh_token VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX oauth_tokens_unique_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parts ADD provider_reference_provider_key VARCHAR(255) DEFAULT NULL, ADD provider_reference_provider_id VARCHAR(255) DEFAULT NULL, ADD provider_reference_provider_url VARCHAR(255) DEFAULT NULL, ADD provider_reference_last_updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE oauth_tokens');
        $this->addSql('ALTER TABLE `parts` DROP provider_reference_provider_key, DROP provider_reference_provider_id, DROP provider_reference_provider_url, DROP provider_reference_last_updated');
    }
}
