<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20240905085300 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Added order fields';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts ADD orderamount DOUBLE PRECISION NOT NULL DEFAULT 0, ADD orderDelivery DATETIME');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `parts` DROP orderamount, DROP orderDelivery');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts ADD COLUMN orderamount DOUBLE PRECISION NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE parts ADD COLUMN orderDelivery DATETIME');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $error;
        // TODO: implement backwards migration for SQlite
    }
}
