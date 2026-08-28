<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260827164156 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add access_method, request_id and transaction_id columns to the log table (with indexes on all three), and backfill access_method/username for legacy CLI log entries';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log ADD access_method SMALLINT DEFAULT NULL, ADD request_id BINARY(16) DEFAULT NULL, ADD transaction_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE log ADD INDEX log_idx_access_method (access_method), ADD INDEX log_idx_request_id (request_id), ADD INDEX log_idx_transaction_id (transaction_id)');
        $this->addSql("UPDATE log SET access_method = 2, username = SUBSTR(username, 8) WHERE username LIKE '!!!CLI %'");
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log DROP INDEX log_idx_access_method, DROP INDEX log_idx_request_id, DROP INDEX log_idx_transaction_id');
        $this->addSql('ALTER TABLE log DROP COLUMN access_method, DROP COLUMN request_id, DROP COLUMN transaction_id');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log ADD COLUMN access_method SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE log ADD COLUMN request_id BLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE log ADD COLUMN transaction_id BLOB DEFAULT NULL');
        $this->addSql('CREATE INDEX log_idx_access_method ON log (access_method)');
        $this->addSql('CREATE INDEX log_idx_request_id ON log (request_id)');
        $this->addSql('CREATE INDEX log_idx_transaction_id ON log (transaction_id)');
        $this->addSql("UPDATE log SET access_method = 2, username = SUBSTR(username, 8) WHERE username LIKE '!!!CLI %'");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP INDEX log_idx_access_method');
        $this->addSql('DROP INDEX log_idx_request_id');
        $this->addSql('DROP INDEX log_idx_transaction_id');
        $this->addSql('ALTER TABLE log DROP COLUMN access_method');
        $this->addSql('ALTER TABLE log DROP COLUMN request_id');
        $this->addSql('ALTER TABLE log DROP COLUMN transaction_id');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log ADD access_method SMALLINT DEFAULT NULL, ADD request_id UUID DEFAULT NULL, ADD transaction_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX log_idx_access_method ON log (access_method)');
        $this->addSql('CREATE INDEX log_idx_request_id ON log (request_id)');
        $this->addSql('CREATE INDEX log_idx_transaction_id ON log (transaction_id)');
        $this->addSql("UPDATE log SET access_method = 2, username = SUBSTR(username, 8) WHERE username LIKE '!!!CLI %'");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('DROP INDEX log_idx_access_method');
        $this->addSql('DROP INDEX log_idx_request_id');
        $this->addSql('DROP INDEX log_idx_transaction_id');
        $this->addSql('ALTER TABLE log DROP COLUMN access_method, DROP COLUMN request_id, DROP COLUMN transaction_id');
    }
}
