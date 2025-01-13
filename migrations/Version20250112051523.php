<?php /** @noinspection SqlNoDataSourceInspection */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250112051523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split $path property for attachments into $internal_path and $external_path';
    }

    public function up(Schema $schema): void
    {   $this->addSql('ALTER TABLE attachments ADD internal_path VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE attachments RENAME COLUMN path TO external_path');

        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%MEDIA#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%BASE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%SECURE#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET internal_path=external_path WHERE external_path LIKE \'#%FOOTPRINTS3D#%%\' ESCAPE \'#\'');
        $this->addSql('UPDATE attachments SET external_path=\'\' WHERE internal_path <> \'\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE attachments SET external_path=internal_path WHERE internal_path <> \'\'');

        $this->addSql('ALTER TABLE attachments DROP internal_path');
        $this->addSql('ALTER TABLE attachments RENAME COLUMN external_path TO path');
    }
}
