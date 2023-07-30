<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230730131708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE oauth_tokens CHANGE token token LONGTEXT DEFAULT NULL, CHANGE refresh_token refresh_token LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails CHANGE supplier_product_url supplier_product_url LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE parts CHANGE manufacturer_product_url manufacturer_product_url LONGTEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE oauth_tokens CHANGE token token VARCHAR(255) DEFAULT NULL, CHANGE refresh_token refresh_token VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `orderdetails` CHANGE supplier_product_url supplier_product_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `parts` CHANGE manufacturer_product_url manufacturer_product_url VARCHAR(255) NOT NULL');
    }
}
