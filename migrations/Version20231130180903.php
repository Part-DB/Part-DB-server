<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231130180903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories ADD eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, ADD eda_info_invisible TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_bom TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_board TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_sim TINYINT(1) DEFAULT NULL, ADD eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE footprints ADD eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, ADD eda_info_value VARCHAR(255) DEFAULT NULL, ADD eda_info_invisible TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_bom TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_board TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_sim TINYINT(1) DEFAULT NULL, ADD eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, ADD eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `categories` DROP eda_info_reference_prefix, DROP eda_info_invisible, DROP eda_info_exclude_from_bom, DROP eda_info_exclude_from_board, DROP eda_info_exclude_from_sim, DROP eda_info_kicad_symbol');
        $this->addSql('ALTER TABLE `footprints` DROP eda_info_kicad_footprint');
        $this->addSql('ALTER TABLE `parts` DROP eda_info_reference_prefix, DROP eda_info_value, DROP eda_info_invisible, DROP eda_info_exclude_from_bom, DROP eda_info_exclude_from_board, DROP eda_info_exclude_from_sim, DROP eda_info_kicad_symbol, DROP eda_info_kicad_footprint');
    }
}
