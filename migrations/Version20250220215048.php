<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250220215048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split $path property for attachments into $internal_path and $external_path';
    }

    public function up(Schema $schema): void
    {
        //Create the new columns as nullable (that is easier modifying them)
        $this->addSql('ALTER TABLE attachments ADD internal_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE attachments ADD external_path VARCHAR(255) DEFAULT NULL');

        //Copy the data from path to external_path and remove the path column
        $this->addSql('UPDATE attachments SET external_path=path');
        $this->addSql('ALTER TABLE attachments DROP COLUMN path');


        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%MEDIA#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%BASE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%SECURE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS3D#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET external_path=NULL WHERE internal_path IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE attachments SET external_path=internal_path WHERE internal_path IS NOT NULL');
        $this->addSql('ALTER TABLE attachments DROP COLUMN internal_path');
        $this->addSql('ALTER TABLE attachments RENAME COLUMN external_path TO path');
    }
}
