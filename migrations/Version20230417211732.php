<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230417211732 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Fix class names in attachments table for databases migrated from legacy Part-DB';
    }

    public function mySQLUp(Schema $schema): void
    {
        //Delete all attachments where the corresponding part or device was deleted in legacy Part-DB (and therefore does not exist in the new Part-DB
        $this->addSql('DELETE FROM attachments WHERE class_name = "PartDB\\\\Part" AND NOT EXISTS (SELECT id FROM parts WHERE id = attachments.element_id)');
        $this->addSql('DELETE FROM attachments WHERE class_name = "PartDB\\\\Device" AND NOT EXISTS (SELECT id FROM projects WHERE id = attachments.element_id)');

        // Replace all attachments where class_name is the legacy "PartDB\Part" with the new version "Part"
        //We have to use 4 backslashes here, as PHP reduces them to 2 backslashes, which MySQL interprets as an escaped backslash.
        $this->addSql('UPDATE attachments SET class_name = "Part" WHERE class_name = "PartDB\\\\Part"');
        //Do the same with PartDB\Device and Device
        $this->addSql('UPDATE attachments SET class_name = "Device" WHERE class_name = "PartDB\\\\Device"');


    }

    public function mySQLDown(Schema $schema): void
    {
        // We can not revert this migration, because we don't know the old class name.
    }

    public function sqLiteUp(Schema $schema): void
    {
        //As legacy database can only be migrated to MySQL, we don't need to implement this method.
        //Dont skip here, as this causes this migration always to be executed. Do nothing instead.
    }

    public function sqLiteDown(Schema $schema): void
    {
        //As we done nothing, we don't need to implement this method.
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }
}
