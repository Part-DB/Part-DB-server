<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250813214628 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Migrate webauthn_keys transports and other_ui fields to JSON type';
    }

    public function convertArrayToJson(): void
    {
        $connection = $this->connection;
        $rows = $connection->fetchAllAssociative('SELECT id, transports, other_ui FROM webauthn_keys');

        foreach ($rows as $row) {
            $id = $row['id'];
            $new_transports = json_encode(unserialize($row['transports'], ['allowed_classes' => false]),
                JSON_THROW_ON_ERROR);
            $new_other_ui = json_encode(unserialize($row['other_ui'], ['allowed_classes' => false]),
                JSON_THROW_ON_ERROR);

            $connection->executeStatement(
                'UPDATE webauthn_keys SET transports = :transports, other_ui = :other_ui WHERE id = :id',
                [
                    'transports' => $new_transports,
                    'other_ui' => $new_other_ui,
                    'id' => $id,
                ]
            );
        }
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->convertArrayToJson();
        $this->addSql('ALTER TABLE webauthn_keys CHANGE transports transports JSON NOT NULL, CHANGE other_ui other_ui JSON DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webauthn_keys CHANGE transports transports LONGTEXT NOT NULL, CHANGE other_ui other_ui LONGTEXT DEFAULT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        //As there is no JSON type in SQLite, we only need to convert the data.
        $this->convertArrayToJson();
    }

    public function sqLiteDown(Schema $schema): void
    {
        //Nothing to do here, as SQLite does not support JSON type and we are not changing the column type.
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->convertArrayToJson();
        $this->addSql('ALTER TABLE webauthn_keys ALTER transports TYPE JSON USING transports::JSON');
        $this->addSql('ALTER TABLE webauthn_keys ALTER other_ui TYPE JSON USING other_ui::JSON');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webauthn_keys ALTER transports TYPE TEXT');
        $this->addSql('ALTER TABLE webauthn_keys ALTER other_ui TYPE TEXT');
    }
}
