<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230402170923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_lots ADD id_owner INT DEFAULT NULL');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F94321E5A74C FOREIGN KEY (id_owner) REFERENCES `users` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_EBC8F94321E5A74C ON part_lots (id_owner)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4727ACA70 FOREIGN KEY (parent_id) REFERENCES projects (id)');
        $this->addSql('ALTER TABLE storelocations ADD id_owner INT DEFAULT NULL, ADD part_owner_must_match TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE storelocations ADD CONSTRAINT FK_751702021E5A74C FOREIGN KEY (id_owner) REFERENCES `users` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_751702021E5A74C ON storelocations (id_owner)');
        $this->addSql('ALTER TABLE users ADD about_me LONGTEXT DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE part_lots DROP FOREIGN KEY FK_EBC8F94321E5A74C');
        $this->addSql('DROP INDEX IDX_EBC8F94321E5A74C ON part_lots');
        $this->addSql('ALTER TABLE part_lots DROP id_owner');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4727ACA70');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_751702021E5A74C');
        $this->addSql('DROP INDEX IDX_751702021E5A74C ON `storelocations`');
        $this->addSql('ALTER TABLE `storelocations` DROP id_owner, DROP part_owner_must_match');
        $this->addSql('ALTER TABLE `users` DROP about_me');
    }
}
