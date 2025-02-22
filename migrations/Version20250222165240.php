<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250222165240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate the old attachment class discriminator values from legacy Part-DB to the modern format, so that there is just one unified value';
    }

    public function up(Schema $schema): void
    {
        //Change the old discriminator values to the new ones
        $this->addSql('UPDATE attachments SET class_name = "Part" WHERE class_name = "PartDB\Part"');
        $this->addSql('UPDATE attachments SET class_name = "Device" WHERE class_name = "PartDB\Device"');
    }

    public function down(Schema $schema): void
    {
        //No down required, as the new format can also be read by older Part-DB version
    }
}
