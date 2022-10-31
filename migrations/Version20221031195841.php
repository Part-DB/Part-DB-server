<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221031195841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `groups` ADD permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE users ADD permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');

        //Set permissions of the admin user to allow all permissions
        $this->addSql(<<<'SQL'
            UPDATE `users` SET permissions_data = '{"parts":{"read":true,"edit":true,"create":true,"delete":true,"change_favorite":true,"show_history":true,"revert_element":true},"storelocations":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"footprints":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"categories":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"suppliers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"manufacturers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"tools":{"statistics":true,"lastActivity":true,"timetravel":true,"label_scanner":true,"reel_calculator":true},"self":{"edit_infos":true,"edit_username":true,"show_permissions":true,"show_logs":true},"labels":{"create_labels":true,"edit_options":true,"read_profiles":true,"edit_profiles":true,"create_profiles":true,"delete_profiles":true,"use_twig":true,"show_history":true,"revert_element":true},"groups":{"read":true,"edit":true,"create":true,"delete":true,"edit_permissions":true,"show_history":true,"revert_element":true},"users":{"read":true,"create":true,"delete":true,"edit_username":true,"edit_infos":true,"edit_permissions":true,"set_password":true,"change_user_settings":true,"show_history":true,"revert_element":true},"system":{"show_logs":true,"delete_logs":true},"devices":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"attachment_types":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"currencies":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"measurement_units":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true}}'
            WHERE id = 2;
        SQL);

        $this->warnIf(true, "\x1b[1;37;43m\n!!! All permissions were reset! Please change them to the desired state, immediately !!!\n\x1b[0;39;49m");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `groups` DROP permissions_data');
        $this->addSql('ALTER TABLE `users` DROP permissions_data');
    }
}
