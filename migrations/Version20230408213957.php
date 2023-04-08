<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230408213957 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Fixed some minor issues in the database schema and indifference between new and legacy-migrated databases';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE groups CHANGE permissions_data permissions_data LONGTEXT DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(4) NOT NULL');
        if ($this->doesFKExists('projects', 'FK_11074E9A727ACA70')){
            $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_11074E9A727ACA70');
        }
        $this->addSql('ALTER TABLE projects CHANGE description description LONGTEXT DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE permissions_data permissions_data LONGTEXT DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', CHANGE saml_user saml_user TINYINT(1) NOT NULL, CHANGE about_me about_me LONGTEXT DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT NOT NULL COMMENT \'(DC2Type:tinyint)\'');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `groups` CHANGE permissions_data permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE `users` CHANGE about_me about_me LONGTEXT NOT NULL, CHANGE saml_user saml_user TINYINT(1) DEFAULT 0 NOT NULL, CHANGE permissions_data permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) NOT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        // TODO: Implement sqLiteUp() method.
    }

    public function sqLiteDown(Schema $schema): void
    {
        // TODO: Implement sqLiteDown() method.
    }
}
