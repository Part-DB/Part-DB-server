<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250220215048 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Split $path property for attachments into $internal_path and $external_path';
    }

    public function mySQLUp(Schema $schema): void
    {
        //Create the new columns as nullable (that is easier modifying them)
        $this->addSql('ALTER TABLE attachments ADD internal_path VARCHAR(255) DEFAULT NULL, ADD external_path VARCHAR(255) DEFAULT NULL');

        //Copy the data from path to external_path and remove the path column
        $this->addSql('UPDATE attachments SET external_path=path');
        $this->addSql('ALTER TABLE attachments DROP path');


        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%MEDIA#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%BASE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%SECURE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS3D#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET external_path=NULL WHERE internal_path IS NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('UPDATE attachments SET external_path=internal_path WHERE internal_path IS NOT NULL');
        $this->addSql('ALTER TABLE attachments DROP internal_path');
        $this->addSql('ALTER TABLE attachments RENAME COLUMN external_path TO path');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        //We can use the same SQL for PostgreSQL as for MySQL
        $this->mySQLUp($schema);
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //We can use the same SQL for PostgreSQL as for MySQL
        $this->mySQLDown($schema);
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
