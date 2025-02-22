<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

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
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachments AS SELECT id, type_id, original_filename, show_in_table, name, last_modified, datetime_added, class_name, element_id, path FROM attachments');
        $this->addSql('DROP TABLE attachments');
        $this->addSql('CREATE TABLE attachments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_id INTEGER NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, show_in_table BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, class_name VARCHAR(255) NOT NULL, element_id INTEGER NOT NULL, internal_path VARCHAR(255) DEFAULT NULL, external_path VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES attachment_types (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO attachments (id, type_id, original_filename, show_in_table, name, last_modified, datetime_added, class_name, element_id, external_path) SELECT id, type_id, original_filename, show_in_table, name, last_modified, datetime_added, class_name, element_id, path FROM __temp__attachments');
        $this->addSql('DROP TABLE __temp__attachments');
        $this->addSql('CREATE INDEX attachment_element_idx ON attachments (class_name, element_id)');
        $this->addSql('CREATE INDEX attachment_name_idx ON attachments (name)');
        $this->addSql('CREATE INDEX attachments_idx_class_name_id ON attachments (class_name, id)');
        $this->addSql('CREATE INDEX attachments_idx_id_element_id_class_name ON attachments (id, element_id, class_name)');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON attachments (type_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON attachments (element_id)');

        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%MEDIA#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%BASE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%SECURE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS3D#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET external_path=NULL WHERE internal_path IS NOT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        //Reuse the MySQL down migration:
        $this->mySQLDown($schema);
    }


}
