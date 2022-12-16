<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221216224745 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Allow to delete users';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `log` DROP FOREIGN KEY FK_8F3F68C56B3CA4B');
        $this->addSql('ALTER TABLE `log` ADD username VARCHAR(255) NOT NULL, CHANGE id_user id_user INT DEFAULT NULL, CHANGE level level TINYINT(4) NOT NULL');
        $this->addSql('ALTER TABLE `log` ADD CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES `users` (id) ON DELETE SET NULL');

        //Fill the username column for existing logs
        $this->addSql('UPDATE `log` LEFT JOIN `users` ON `log`.id_user = `users`.id SET `log`.username = `users`.name');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `log` DROP FOREIGN KEY FK_8F3F68C56B3CA4B');
        $this->addSql('ALTER TABLE `log` DROP username, CHANGE id_user id_user INT NOT NULL, CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE `log` ADD CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES users (id)');
    }


    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER DEFAULT NULL, datetime DATETIME NOT NULL, level TINYINT(4) NOT NULL, target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --(DC2Type:json)
        , type SMALLINT NOT NULL, username VARCHAR(255) DEFAULT "" NOT NULL, CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO log (id, id_user, datetime, level, target_id, target_type, extra, type) SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');

        //Fill the username column for existing logs
        $this->addSql('UPDATE log SET username = (SELECT name FROM users WHERE id = log.id_user)');

        //$this->addSql('UPDATE log JOIN `users` ON log.id_user = `users`.id SET log.username = `users`.name');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER NOT NULL, datetime DATETIME NOT NULL, level BOOLEAN NOT NULL, target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --(DC2Type:json)
        , type SMALLINT NOT NULL, CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO log (id, id_user, datetime, level, target_id, target_type, extra, type) SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');

    }
}
