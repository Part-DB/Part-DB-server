<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\UserSystem\PermissionData;
use App\Migration\AbstractMultiPlatformMigration;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Services\UserSystem\PermissionPresetsHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221114193325 extends AbstractMultiPlatformMigration implements ContainerAwareInterface
{
    private ?ContainerInterface $container = null;
    private ?PermissionPresetsHelper $permission_presets_helper = null;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string
    {
        return 'Update the permission system to the new system. Please note that all permissions will be reset!';
    }

    private function getJSONPermDataFromPreset(string $preset): string
    {
        if ($this->permission_presets_helper === null) {
            throw new \RuntimeException('PermissionPresetsHelper not set! There seems to be some issue with the dependency injection!');
        }

        //Create a virtual user on which we can apply the preset
        $user = new class implements HasPermissionsInterface {

            public PermissionData $perm_data;

            public function __construct()
            {
                $this->perm_data = new PermissionData();
            }

            public function getPermissions(): PermissionData
            {
                return $this->perm_data;
            }
        };

        //Apply the preset to the virtual user
        $this->permission_presets_helper->applyPreset($user, $preset);

        //And return the json data
        return json_encode($user->getPermissions());
    }

    private function addDataMigrationAndWarning(): void
    {
        //Retrieve the json representations of the presets
        $admin = $this->getJSONPermDataFromPreset(PermissionPresetsHelper::PRESET_ADMIN);
        $editor = $this->getJSONPermDataFromPreset(PermissionPresetsHelper::PRESET_EDITOR);
        $read_only = $this->getJSONPermDataFromPreset(PermissionPresetsHelper::PRESET_READ_ONLY);

        //Reset the permissions of the predefined groups, when their name was not changed
        $this->addSql("UPDATE `groups` SET permissions_data = '$admin' WHERE id = 1 AND name = 'admins';");
        $this->addSql("UPDATE `groups` SET permissions_data = '$read_only' WHERE id = 2 AND name = 'readonly';");
        $this->addSql("UPDATE `groups` SET permissions_data = '$editor' WHERE id = 3 AND name = 'users';");

        //Disable login of all users with ID > 2 (meaning all except the anonymous and admin user)
        $this->addSql(<<<'SQL'
            UPDATE `users` SET disabled = 1            
            WHERE id > 2;
        SQL);

        //Reset the permissions of the admin user, to allow admin permissions (like the admins group)
        $this->addSql("UPDATE `users` SET permissions_data = '$admin' WHERE id = 2;");

        //This warning should not be needed, anymore, as almost everybody should have updated to the new version by now, and this warning would just irritate new users of the software
        /*
        $this->logger->warning('<bg=cyan;fg=black>!!! All permissions were reset! Please change them to the desired state, immediately !!!</>');
        $this->logger->warning('<bg=cyan;fg=black>!!! For security reasons all users (except the admin user) were disabled. Login with admin user and reenable other users after checking their permissions !!!</>');
        $this->logger->warning('<bg=cyan;fg=black>!!! For more infos see: https://github.com/Part-DB/Part-DB-symfony/discussions/193 !!!</>');
        */
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `groups` ADD permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', DROP perms_system, DROP perms_groups, DROP perms_users, DROP perms_self, DROP perms_system_config, DROP perms_system_database, DROP perms_parts, DROP perms_parts_name, DROP perms_parts_description, DROP perms_parts_footprint, DROP perms_parts_manufacturer, DROP perms_parts_comment, DROP perms_parts_order, DROP perms_parts_orderdetails, DROP perms_parts_prices, DROP perms_parts_attachements, DROP perms_devices, DROP perms_devices_parts, DROP perms_storelocations, DROP perms_footprints, DROP perms_categories, DROP perms_suppliers, DROP perms_manufacturers, DROP perms_attachement_types, DROP perms_tools, DROP perms_labels, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units, DROP perms_parts_parameters');
        $this->addSql('ALTER TABLE `users` ADD permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', DROP perms_system, DROP perms_groups, DROP perms_users, DROP perms_self, DROP perms_system_config, DROP perms_system_database, DROP perms_parts, DROP perms_parts_name, DROP perms_parts_description, DROP perms_parts_footprint, DROP perms_parts_manufacturer, DROP perms_parts_comment, DROP perms_parts_order, DROP perms_parts_orderdetails, DROP perms_parts_prices, DROP perms_parts_attachements, DROP perms_devices, DROP perms_devices_parts, DROP perms_storelocations, DROP perms_footprints, DROP perms_categories, DROP perms_suppliers, DROP perms_manufacturers, DROP perms_attachement_types, DROP perms_tools, DROP perms_labels, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units, DROP perms_parts_parameters');

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
        $this->addSql('CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO groups (id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX group_idx_parent_name ON groups (parent_id, name)');
        $this->addSql('CREATE INDEX group_idx_name ON groups (name)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON groups (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON groups (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E96DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO users (id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added) SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE INDEX user_idx_username ON users (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON users (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON users (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON users (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON users (id_preview_attachement)');


        $this->addDataMigrationAndWarning();
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added FROM "groups"');
        $this->addSql('DROP TABLE "groups"');
        $this->addSql('CREATE TABLE "groups" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL, CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "groups" (id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON "groups" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON "groups" (id_preview_attachement)');
        $this->addSql('CREATE INDEX group_idx_name ON "groups" (name)');
        $this->addSql('CREATE INDEX group_idx_parent_name ON "groups" (parent_id, name)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added FROM "users"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --
(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --
(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL, CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E96DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "users" (id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added) SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON "users" (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON "users" (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON "users" (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON "users" (id_preview_attachement)');
        $this->addSql('CREATE INDEX user_idx_username ON "users" (name)');
    }

    public function setContainer(ContainerInterface $container = null)
    {
        if ($container) {
            $this->container = $container;
            $this->permission_presets_helper = $container->get(PermissionPresetsHelper::class);
        }
    }
}
