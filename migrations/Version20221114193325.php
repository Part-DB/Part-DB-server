<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221114193325 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Update the permission system to the new system. Please note that all permissions will be reset!';
    }

    private function addDataMigrationAndWarning(): void
    {
        //Reset the permissions of the predefined groups, when their name was not changed
        $this->addSql(<<<'SQL'
            UPDATE `groups` SET permissions_data = '{"parts":{"read":true,"edit":true,"create":true,"delete":true,"change_favorite":true,"show_history":true,"revert_element":true},"tools":{"statistics":true,"label_scanner":true,"reel_calculator":true,"lastActivity":true},"attachments":{"list_attachments":true,"show_private":true},"self":{"show_permissions":true,"edit_infos":true},"labels":{"create_labels":true,"edit_options":true,"read_profiles":true,"edit_profiles":true,"create_profiles":true,"delete_profiles":true,"show_history":true,"revert_element":true},"categories":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"storelocations":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"footprints":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"manufacturers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"attachment_types":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"currencies":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"measurement_units":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"suppliers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"users":{"read":true,"create":true,"delete":true,"edit_username":true,"edit_infos":true,"edit_permissions":true,"set_password":true,"change_user_settings":true,"show_history":true,"revert_element":true},"groups":{"read":true,"edit":true,"create":true,"delete":true,"edit_permissions":true,"show_history":true,"revert_element":true},"system":{"show_logs":true,"server_infos":true},"devices":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true}}'
            WHERE id = 1 AND name = 'admins';
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE `groups` SET permissions_data = '{"parts":{"read":true},"tools":{"statistics":true,"label_scanner":true,"reel_calculator":true},"attachments":{"list_attachments":true},"self":{"show_permissions":true},"labels":{"create_labels":true,"edit_options":true},"storelocations":{"read":true},"footprints":{"read":true},"categories":{"read":true},"suppliers":{"read":true},"manufacturers":{"read":true},"currencies":{"read":true},"attachment_types":{"read":true},"measurement_units":{"read":true},"devices":{"read":true}}'
            WHERE id = 2 AND name = 'readonly';
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE `groups` SET permissions_data = '{"parts":{"read":true,"edit":true,"create":true,"delete":true,"change_favorite":true,"show_history":true,"revert_element":true},"tools":{"statistics":true,"label_scanner":true,"reel_calculator":true,"lastActivity":true},"attachments":{"list_attachments":true,"show_private":true},"self":{"show_permissions":true,"edit_infos":true},"labels":{"create_labels":true,"edit_options":true,"read_profiles":true,"edit_profiles":true,"create_profiles":true,"delete_profiles":true,"show_history":true,"revert_element":true},"categories":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"storelocations":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"footprints":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"manufacturers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"attachment_types":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"currencies":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"measurement_units":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"suppliers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"devices":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true}}'
            WHERE id = 3 AND name = 'users';
        SQL);

        //Disable login of all users with ID > 2 (meaning all except the anonymous and admin user)
        $this->addSql(<<<'SQL'
            UPDATE `users` SET disabled = 1            
            WHERE id > 2;
        SQL);

        //Reset the permissions of the admin user, to allow admin permissions (like the admins group)
        $this->addSql(<<<'SQL'
            UPDATE `users` SET permissions_data = '{"parts":{"read":true,"edit":true,"create":true,"delete":true,"change_favorite":true,"show_history":true,"revert_element":true},"tools":{"statistics":true,"label_scanner":true,"reel_calculator":true,"lastActivity":true},"attachments":{"list_attachments":true,"show_private":true},"self":{"show_permissions":true,"edit_infos":true},"labels":{"create_labels":true,"edit_options":true,"read_profiles":true,"edit_profiles":true,"create_profiles":true,"delete_profiles":true,"show_history":true,"revert_element":true},"categories":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"storelocations":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"footprints":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"manufacturers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"attachment_types":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"currencies":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"measurement_units":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"suppliers":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true},"users":{"read":true,"create":true,"delete":true,"edit_username":true,"edit_infos":true,"edit_permissions":true,"set_password":true,"change_user_settings":true,"show_history":true,"revert_element":true},"groups":{"read":true,"edit":true,"create":true,"delete":true,"edit_permissions":true,"show_history":true,"revert_element":true},"system":{"show_logs":true,"server_infos":true},"devices":{"read":true,"edit":true,"create":true,"delete":true,"show_history":true,"revert_element":true}}'
            WHERE id = 2;
        SQL);


        $this->warnIf(true, "\x1b[1;37;43m\n!!! All permissions were reset! Please change them to the desired state, immediately !!!\x1b[0;39;49m");
        $this->warnIf(true, "\x1b[1;37;43m\n!!! For security reasons all users (except the admin user) were disabled. Login with admin user and reenable other users after checking their permissions !!!\x1b[0;39;49m");
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE groups ADD permissions_data LONGTEXT DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', DROP perms_system, DROP perms_groups, DROP perms_users, DROP perms_self, DROP perms_system_config, DROP perms_system_database, DROP perms_parts, DROP perms_parts_name, DROP perms_parts_description, DROP perms_parts_footprint, DROP perms_parts_manufacturer, DROP perms_parts_comment, DROP perms_parts_order, DROP perms_parts_orderdetails, DROP perms_parts_prices, DROP perms_parts_attachements, DROP perms_devices, DROP perms_devices_parts, DROP perms_storelocations, DROP perms_footprints, DROP perms_categories, DROP perms_suppliers, DROP perms_manufacturers, DROP perms_attachement_types, DROP perms_tools, DROP perms_labels, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units, DROP perms_parts_parameters');
        $this->addSql('ALTER TABLE users ADD permissions_data LONGTEXT DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', DROP perms_system, DROP perms_groups, DROP perms_users, DROP perms_self, DROP perms_system_config, DROP perms_system_database, DROP perms_parts, DROP perms_parts_name, DROP perms_parts_description, DROP perms_parts_footprint, DROP perms_parts_manufacturer, DROP perms_parts_comment, DROP perms_parts_order, DROP perms_parts_orderdetails, DROP perms_parts_prices, DROP perms_parts_attachements, DROP perms_devices, DROP perms_devices_parts, DROP perms_storelocations, DROP perms_footprints, DROP perms_categories, DROP perms_suppliers, DROP perms_manufacturers, DROP perms_attachement_types, DROP perms_tools, DROP perms_labels, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units, DROP perms_parts_parameters');

        $this->addDataMigrationAndWarning();
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `groups` ADD perms_system INT NOT NULL, ADD perms_groups INT NOT NULL, ADD perms_users INT NOT NULL, ADD perms_self INT NOT NULL, ADD perms_system_config INT NOT NULL, ADD perms_system_database INT NOT NULL, ADD perms_parts BIGINT NOT NULL, ADD perms_parts_name SMALLINT NOT NULL, ADD perms_parts_description SMALLINT NOT NULL, ADD perms_parts_footprint SMALLINT NOT NULL, ADD perms_parts_manufacturer SMALLINT NOT NULL, ADD perms_parts_comment SMALLINT NOT NULL, ADD perms_parts_order SMALLINT NOT NULL, ADD perms_parts_orderdetails SMALLINT NOT NULL, ADD perms_parts_prices SMALLINT NOT NULL, ADD perms_parts_attachements SMALLINT NOT NULL, ADD perms_devices INT NOT NULL, ADD perms_devices_parts INT NOT NULL, ADD perms_storelocations INT NOT NULL, ADD perms_footprints INT NOT NULL, ADD perms_categories INT NOT NULL, ADD perms_suppliers INT NOT NULL, ADD perms_manufacturers INT NOT NULL, ADD perms_attachement_types INT NOT NULL, ADD perms_tools INT NOT NULL, ADD perms_labels INT NOT NULL, ADD perms_parts_category SMALLINT NOT NULL, ADD perms_parts_minamount SMALLINT NOT NULL, ADD perms_parts_lots SMALLINT NOT NULL, ADD perms_parts_tags SMALLINT NOT NULL, ADD perms_parts_unit SMALLINT NOT NULL, ADD perms_parts_mass SMALLINT NOT NULL, ADD perms_parts_status SMALLINT NOT NULL, ADD perms_parts_mpn SMALLINT NOT NULL, ADD perms_currencies INT NOT NULL, ADD perms_measurement_units INT NOT NULL, ADD perms_parts_parameters SMALLINT NOT NULL, DROP permissions_data');
        $this->addSql('ALTER TABLE `users` ADD perms_system INT NOT NULL, ADD perms_groups INT NOT NULL, ADD perms_users INT NOT NULL, ADD perms_self INT NOT NULL, ADD perms_system_config INT NOT NULL, ADD perms_system_database INT NOT NULL, ADD perms_parts BIGINT NOT NULL, ADD perms_parts_name SMALLINT NOT NULL, ADD perms_parts_description SMALLINT NOT NULL, ADD perms_parts_footprint SMALLINT NOT NULL, ADD perms_parts_manufacturer SMALLINT NOT NULL, ADD perms_parts_comment SMALLINT NOT NULL, ADD perms_parts_order SMALLINT NOT NULL, ADD perms_parts_orderdetails SMALLINT NOT NULL, ADD perms_parts_prices SMALLINT NOT NULL, ADD perms_parts_attachements SMALLINT NOT NULL, ADD perms_devices INT NOT NULL, ADD perms_devices_parts INT NOT NULL, ADD perms_storelocations INT NOT NULL, ADD perms_footprints INT NOT NULL, ADD perms_categories INT NOT NULL, ADD perms_suppliers INT NOT NULL, ADD perms_manufacturers INT NOT NULL, ADD perms_attachement_types INT NOT NULL, ADD perms_tools INT NOT NULL, ADD perms_labels INT NOT NULL, ADD perms_parts_category SMALLINT NOT NULL, ADD perms_parts_minamount SMALLINT NOT NULL, ADD perms_parts_lots SMALLINT NOT NULL, ADD perms_parts_tags SMALLINT NOT NULL, ADD perms_parts_unit SMALLINT NOT NULL, ADD perms_parts_mass SMALLINT NOT NULL, ADD perms_parts_status SMALLINT NOT NULL, ADD perms_parts_mpn SMALLINT NOT NULL, ADD perms_currencies INT NOT NULL, ADD perms_measurement_units INT NOT NULL, ADD perms_parts_parameters SMALLINT NOT NULL, DROP permissions_data');

    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added FROM groups');
        $this->addSql('DROP TABLE groups');
        $this->addSql('CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data DEFAULT \'[]\', CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO groups (id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX group_idx_parent_name ON groups (parent_id, name)');
        $this->addSql('CREATE INDEX group_idx_name ON groups (name)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON groups (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON groups (id_preview_attachement)');


        $this->addDataMigrationAndWarning();
    }

    public function sqLiteDown(Schema $schema): void
    {

    }
}
