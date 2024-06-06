<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240606203053 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for Postgres';
    }

    public function postgreSQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE api_tokens_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "attachment_types_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "attachments_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "categories_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE currencies_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "footprints_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "groups_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE label_profiles_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE log_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "manufacturers_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "measurement_units_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE oauth_tokens_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "orderdetails_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE parameters_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE part_association_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE part_lots_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "parts_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "pricedetails_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE project_bom_entries_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE projects_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "storelocations_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "suppliers_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE u2f_keys_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "users_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE webauthn_keys_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE api_tokens (id INT NOT NULL, user_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, valid_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, token VARCHAR(68) NOT NULL, level SMALLINT NOT NULL, last_time_used TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CAD560E5F37A13B ON api_tokens (token)');
        $this->addSql('CREATE INDEX IDX_2CAD560EA76ED395 ON api_tokens (user_id)');
        $this->addSql('CREATE TABLE "attachment_types" (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, filetype_filter TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON "attachment_types" (parent_id)');
        $this->addSql('CREATE INDEX IDX_EFAED719EA7100A1 ON "attachment_types" (id_preview_attachment)');
        $this->addSql('CREATE INDEX attachment_types_idx_name ON "attachment_types" (name)');
        $this->addSql('CREATE INDEX attachment_types_idx_parent_name ON "attachment_types" (parent_id, name)');
        $this->addSql('CREATE TABLE "attachments" (id INT NOT NULL, type_id INT NOT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, path VARCHAR(255) NOT NULL, show_in_table BOOLEAN NOT NULL, class_name VARCHAR(255) NOT NULL, element_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON "attachments" (type_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON "attachments" (element_id)');
        $this->addSql('CREATE INDEX attachments_idx_id_element_id_class_name ON "attachments" (id, element_id, class_name)');
        $this->addSql('CREATE INDEX attachments_idx_class_name_id ON "attachments" (class_name, id)');
        $this->addSql('CREATE INDEX attachment_name_idx ON "attachments" (name)');
        $this->addSql('CREATE INDEX attachment_element_idx ON "attachments" (class_name, element_id)');
        $this->addSql('CREATE TABLE "categories" (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, partname_hint TEXT NOT NULL, partname_regex TEXT NOT NULL, disable_footprints BOOLEAN NOT NULL, disable_manufacturers BOOLEAN NOT NULL, disable_autodatasheets BOOLEAN NOT NULL, disable_properties BOOLEAN NOT NULL, default_description TEXT NOT NULL, default_comment TEXT NOT NULL, eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, eda_info_invisible BOOLEAN DEFAULT NULL, eda_info_exclude_from_bom BOOLEAN DEFAULT NULL, eda_info_exclude_from_board BOOLEAN DEFAULT NULL, eda_info_exclude_from_sim BOOLEAN DEFAULT NULL, eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON "categories" (parent_id)');
        $this->addSql('CREATE INDEX IDX_3AF34668EA7100A1 ON "categories" (id_preview_attachment)');
        $this->addSql('CREATE INDEX category_idx_name ON "categories" (name)');
        $this->addSql('CREATE INDEX category_idx_parent_name ON "categories" (parent_id, name)');
        $this->addSql('CREATE TABLE currencies (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL, iso_code VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX IDX_37C44693EA7100A1 ON currencies (id_preview_attachment)');
        $this->addSql('CREATE INDEX currency_idx_name ON currencies (name)');
        $this->addSql('CREATE INDEX currency_idx_parent_name ON currencies (parent_id, name)');
        $this->addSql('COMMENT ON COLUMN currencies.exchange_rate IS \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE TABLE "footprints" (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, id_footprint_3d INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON "footprints" (parent_id)');
        $this->addSql('CREATE INDEX IDX_A34D68A2EA7100A1 ON "footprints" (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_A34D68A232A38C34 ON "footprints" (id_footprint_3d)');
        $this->addSql('CREATE INDEX footprint_idx_name ON "footprints" (name)');
        $this->addSql('CREATE INDEX footprint_idx_parent_name ON "footprints" (parent_id, name)');
        $this->addSql('CREATE TABLE "groups" (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, permissions_data JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON "groups" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D3970EA7100A1 ON "groups" (id_preview_attachment)');
        $this->addSql('CREATE INDEX group_idx_name ON "groups" (name)');
        $this->addSql('CREATE INDEX group_idx_parent_name ON "groups" (parent_id, name)');
        $this->addSql('CREATE TABLE label_profiles (id INT NOT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, show_in_dropdown BOOLEAN NOT NULL, options_width DOUBLE PRECISION NOT NULL, options_height DOUBLE PRECISION NOT NULL, options_barcode_type VARCHAR(255) NOT NULL, options_picture_type VARCHAR(255) NOT NULL, options_supported_element VARCHAR(255) NOT NULL, options_additional_css TEXT NOT NULL, options_lines_mode VARCHAR(255) NOT NULL, options_lines TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C93E9CF5EA7100A1 ON label_profiles (id_preview_attachment)');
        $this->addSql('CREATE TABLE log (id INT NOT NULL, id_user INT DEFAULT NULL, username VARCHAR(255) NOT NULL, datetime TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, level SMALLINT NOT NULL, target_id INT NOT NULL, target_type SMALLINT NOT NULL, extra JSON NOT NULL, type SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('COMMENT ON COLUMN log.level IS \'(DC2Type:tinyint)\'');
        $this->addSql('CREATE TABLE "manufacturers" (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON "manufacturers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_94565B12EA7100A1 ON "manufacturers" (id_preview_attachment)');
        $this->addSql('CREATE INDEX manufacturer_name ON "manufacturers" (name)');
        $this->addSql('CREATE INDEX manufacturer_idx_parent_name ON "manufacturers" (parent_id, name)');
        $this->addSql('CREATE TABLE "measurement_units" (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer BOOLEAN NOT NULL, use_si_prefix BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F5AF83CF727ACA70 ON "measurement_units" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F5AF83CFEA7100A1 ON "measurement_units" (id_preview_attachment)');
        $this->addSql('CREATE INDEX unit_idx_name ON "measurement_units" (name)');
        $this->addSql('CREATE INDEX unit_idx_parent_name ON "measurement_units" (parent_id, name)');
        $this->addSql('CREATE TABLE oauth_tokens (id INT NOT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, token TEXT DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, refresh_token TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX oauth_tokens_unique_name ON oauth_tokens (name)');
        $this->addSql('COMMENT ON COLUMN oauth_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "orderdetails" (id INT NOT NULL, part_id INT NOT NULL, id_supplier INT DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url TEXT NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON "orderdetails" (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON "orderdetails" (id_supplier)');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON "orderdetails" (supplierpartnr)');
        $this->addSql('CREATE TABLE parameters (id INT NOT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, symbol VARCHAR(255) NOT NULL, value_min DOUBLE PRECISION DEFAULT NULL, value_typical DOUBLE PRECISION DEFAULT NULL, value_max DOUBLE PRECISION DEFAULT NULL, unit VARCHAR(255) NOT NULL, value_text VARCHAR(255) NOT NULL, param_group VARCHAR(255) NOT NULL, type SMALLINT NOT NULL, element_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_69348FE1F1F2A24 ON parameters (element_id)');
        $this->addSql('CREATE INDEX parameter_name_idx ON parameters (name)');
        $this->addSql('CREATE INDEX parameter_group_idx ON parameters (param_group)');
        $this->addSql('CREATE INDEX parameter_type_element_idx ON parameters (type, element_id)');
        $this->addSql('CREATE TABLE part_association (id INT NOT NULL, owner_id INT NOT NULL, other_id INT NOT NULL, type SMALLINT NOT NULL, other_type VARCHAR(255) DEFAULT NULL, comment TEXT DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_61B952E07E3C61F9 ON part_association (owner_id)');
        $this->addSql('CREATE INDEX IDX_61B952E0998D9879 ON part_association (other_id)');
        $this->addSql('CREATE TABLE part_lots (id INT NOT NULL, id_store_location INT DEFAULT NULL, id_part INT NOT NULL, id_owner INT DEFAULT NULL, description TEXT NOT NULL, comment TEXT NOT NULL, expiration_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, vendor_barcode VARCHAR(255) DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F94321E5A74C ON part_lots (id_owner)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
        $this->addSql('CREATE TABLE "parts" (id INT NOT NULL, id_preview_attachment INT DEFAULT NULL, id_category INT NOT NULL, id_footprint INT DEFAULT NULL, id_part_unit INT DEFAULT NULL, id_manufacturer INT DEFAULT NULL, order_orderdetails_id INT DEFAULT NULL, built_project_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags TEXT NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, ipn VARCHAR(100) DEFAULT NULL, description TEXT NOT NULL, comment TEXT NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url TEXT NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INT NOT NULL, manual_order BOOLEAN NOT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(255) DEFAULT NULL, provider_reference_last_updated TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, eda_info_value VARCHAR(255) DEFAULT NULL, eda_info_invisible BOOLEAN DEFAULT NULL, eda_info_exclude_from_bom BOOLEAN DEFAULT NULL, eda_info_exclude_from_board BOOLEAN DEFAULT NULL, eda_info_exclude_from_sim BOOLEAN DEFAULT NULL, eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON "parts" (ipn)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON "parts" (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON "parts" (built_project_id)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON "parts" (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_name ON "parts" (name)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON "parts" (ipn)');
        $this->addSql('CREATE TABLE "pricedetails" (id INT NOT NULL, id_currency INT DEFAULT NULL, orderdetails_id INT NOT NULL, price NUMERIC(11, 5) NOT NULL, price_related_quantity DOUBLE PRECISION NOT NULL, min_discount_quantity DOUBLE PRECISION NOT NULL, manual_input BOOLEAN NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON "pricedetails" (id_currency)');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON "pricedetails" (orderdetails_id)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount ON "pricedetails" (min_discount_quantity)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount_price_qty ON "pricedetails" (min_discount_quantity, price_related_quantity)');
        $this->addSql('COMMENT ON COLUMN "pricedetails".price IS \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE TABLE project_bom_entries (id INT NOT NULL, id_device INT DEFAULT NULL, id_part INT DEFAULT NULL, price_currency_id INT DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, mountnames TEXT NOT NULL, name VARCHAR(255) DEFAULT NULL, comment TEXT NOT NULL, price NUMERIC(11, 5) DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)');
        $this->addSql('CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)');
        $this->addSql('COMMENT ON COLUMN project_bom_entries.price IS \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE TABLE projects (id INT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, order_quantity INT NOT NULL, status VARCHAR(64) DEFAULT NULL, order_only_missing_parts BOOLEAN NOT NULL, description TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5C93B3A4727ACA70 ON projects (parent_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A4EA7100A1 ON projects (id_preview_attachment)');
        $this->addSql('CREATE TABLE "storelocations" (id INT NOT NULL, parent_id INT DEFAULT NULL, storage_type_id INT DEFAULT NULL, id_owner INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, is_full BOOLEAN NOT NULL, only_single_part BOOLEAN NOT NULL, limit_to_existing_parts BOOLEAN NOT NULL, part_owner_must_match BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7517020727ACA70 ON "storelocations" (parent_id)');
        $this->addSql('CREATE INDEX IDX_7517020B270BFF1 ON "storelocations" (storage_type_id)');
        $this->addSql('CREATE INDEX IDX_751702021E5A74C ON "storelocations" (id_owner)');
        $this->addSql('CREATE INDEX IDX_7517020EA7100A1 ON "storelocations" (id_preview_attachment)');
        $this->addSql('CREATE INDEX location_idx_name ON "storelocations" (name)');
        $this->addSql('CREATE INDEX location_idx_parent_name ON "storelocations" (parent_id, name)');
        $this->addSql('CREATE TABLE "suppliers" (id INT NOT NULL, parent_id INT DEFAULT NULL, default_currency_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, comment TEXT NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names TEXT DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON "suppliers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON "suppliers" (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CEA7100A1 ON "suppliers" (id_preview_attachment)');
        $this->addSql('CREATE INDEX supplier_idx_name ON "suppliers" (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON "suppliers" (parent_id, name)');
        $this->addSql('COMMENT ON COLUMN "suppliers".shipping_costs IS \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE TABLE u2f_keys (id INT NOT NULL, user_id INT DEFAULT NULL, key_handle VARCHAR(128) NOT NULL, public_key VARCHAR(255) NOT NULL, certificate TEXT NOT NULL, counter VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4F4ADB4BA76ED395 ON u2f_keys (user_id)');
        $this->addSql('CREATE UNIQUE INDEX user_unique ON u2f_keys (user_id, key_handle)');
        $this->addSql('CREATE TABLE "users" (id INT NOT NULL, group_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, currency_id INT DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a TEXT NOT NULL, config_instock_comment_w TEXT NOT NULL, about_me TEXT NOT NULL, trusted_device_cookie_version INT NOT NULL, backup_codes JSON NOT NULL, google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, show_email_on_profile BOOLEAN DEFAULT false NOT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, settings JSON NOT NULL, backup_codes_generation_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, pw_reset_expires TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, saml_user BOOLEAN NOT NULL, name VARCHAR(180) NOT NULL, permissions_data JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON "users" (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON "users" (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9EA7100A1 ON "users" (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON "users" (currency_id)');
        $this->addSql('CREATE INDEX user_idx_username ON "users" (name)');
        $this->addSql('CREATE TABLE webauthn_keys (id INT NOT NULL, user_id INT DEFAULT NULL, public_key_credential_id TEXT NOT NULL, type VARCHAR(255) NOT NULL, transports TEXT NOT NULL, attestation_type VARCHAR(255) NOT NULL, trust_path JSON NOT NULL, aaguid TEXT NOT NULL, credential_public_key TEXT NOT NULL, user_handle VARCHAR(255) NOT NULL, counter INT NOT NULL, other_ui TEXT DEFAULT NULL, backup_eligible BOOLEAN DEFAULT NULL, backup_status BOOLEAN DEFAULT NULL, uv_initialized BOOLEAN DEFAULT NULL, name VARCHAR(255) NOT NULL, last_time_used TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_799FD143A76ED395 ON webauthn_keys (user_id)');
        $this->addSql('COMMENT ON COLUMN webauthn_keys.public_key_credential_id IS \'(DC2Type:base64)\'');
        $this->addSql('COMMENT ON COLUMN webauthn_keys.transports IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN webauthn_keys.trust_path IS \'(DC2Type:trust_path)\'');
        $this->addSql('COMMENT ON COLUMN webauthn_keys.aaguid IS \'(DC2Type:aaguid)\'');
        $this->addSql('COMMENT ON COLUMN webauthn_keys.credential_public_key IS \'(DC2Type:base64)\'');
        $this->addSql('COMMENT ON COLUMN webauthn_keys.other_ui IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN webauthn_keys.last_time_used IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "attachment_types" ADD CONSTRAINT FK_EFAED719727ACA70 FOREIGN KEY (parent_id) REFERENCES "attachment_types" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "attachment_types" ADD CONSTRAINT FK_EFAED719EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "attachments" ADD CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES "attachment_types" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "categories" ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "categories" ADD CONSTRAINT FK_3AF34668EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C44693EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "footprints" ADD CONSTRAINT FK_A34D68A2727ACA70 FOREIGN KEY (parent_id) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "footprints" ADD CONSTRAINT FK_A34D68A2EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "footprints" ADD CONSTRAINT FK_A34D68A232A38C34 FOREIGN KEY (id_footprint_3d) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "groups" ADD CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "groups" ADD CONSTRAINT FK_F06D3970EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE label_profiles ADD CONSTRAINT FK_C93E9CF5EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "manufacturers" ADD CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "manufacturers" ADD CONSTRAINT FK_94565B12EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "measurement_units" ADD CONSTRAINT FK_F5AF83CF727ACA70 FOREIGN KEY (parent_id) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "measurement_units" ADD CONSTRAINT FK_F5AF83CFEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "orderdetails" ADD CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "orderdetails" ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE part_association ADD CONSTRAINT FK_61B952E07E3C61F9 FOREIGN KEY (owner_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE part_association ADD CONSTRAINT FK_61B952E0998D9879 FOREIGN KEY (other_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F94321E5A74C FOREIGN KEY (id_owner) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "parts" ADD CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "parts" ADD CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "parts" ADD CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "parts" ADD CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "parts" ADD CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "parts" ADD CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "parts" ADD CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "pricedetails" ADD CONSTRAINT FK_C68C4459398D64AA FOREIGN KEY (id_currency) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "pricedetails" ADD CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES "orderdetails" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD31C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4727ACA70 FOREIGN KEY (parent_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "storelocations" ADD CONSTRAINT FK_7517020727ACA70 FOREIGN KEY (parent_id) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "storelocations" ADD CONSTRAINT FK_7517020B270BFF1 FOREIGN KEY (storage_type_id) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "storelocations" ADD CONSTRAINT FK_751702021E5A74C FOREIGN KEY (id_owner) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "storelocations" ADD CONSTRAINT FK_7517020EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "suppliers" ADD CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "suppliers" ADD CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "suppliers" ADD CONSTRAINT FK_AC28B95CEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE u2f_keys ADD CONSTRAINT FK_4F4ADB4BA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "users" ADD CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "users" ADD CONSTRAINT FK_1483A5E9EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "users" ADD CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE webauthn_keys ADD CONSTRAINT FK_799FD143A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE api_tokens_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "attachment_types_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "attachments_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "categories_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE currencies_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "footprints_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "groups_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE label_profiles_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE log_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "manufacturers_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "measurement_units_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE oauth_tokens_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "orderdetails_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE parameters_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE part_association_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE part_lots_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "parts_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "pricedetails_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE project_bom_entries_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE projects_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "storelocations_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "suppliers_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE u2f_keys_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "users_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE webauthn_keys_id_seq CASCADE');
        $this->addSql('ALTER TABLE api_tokens DROP CONSTRAINT FK_2CAD560EA76ED395');
        $this->addSql('ALTER TABLE "attachment_types" DROP CONSTRAINT FK_EFAED719727ACA70');
        $this->addSql('ALTER TABLE "attachment_types" DROP CONSTRAINT FK_EFAED719EA7100A1');
        $this->addSql('ALTER TABLE "attachments" DROP CONSTRAINT FK_47C4FAD6C54C8C93');
        $this->addSql('ALTER TABLE "categories" DROP CONSTRAINT FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE "categories" DROP CONSTRAINT FK_3AF34668EA7100A1');
        $this->addSql('ALTER TABLE currencies DROP CONSTRAINT FK_37C44693727ACA70');
        $this->addSql('ALTER TABLE currencies DROP CONSTRAINT FK_37C44693EA7100A1');
        $this->addSql('ALTER TABLE "footprints" DROP CONSTRAINT FK_A34D68A2727ACA70');
        $this->addSql('ALTER TABLE "footprints" DROP CONSTRAINT FK_A34D68A2EA7100A1');
        $this->addSql('ALTER TABLE "footprints" DROP CONSTRAINT FK_A34D68A232A38C34');
        $this->addSql('ALTER TABLE "groups" DROP CONSTRAINT FK_F06D3970727ACA70');
        $this->addSql('ALTER TABLE "groups" DROP CONSTRAINT FK_F06D3970EA7100A1');
        $this->addSql('ALTER TABLE label_profiles DROP CONSTRAINT FK_C93E9CF5EA7100A1');
        $this->addSql('ALTER TABLE log DROP CONSTRAINT FK_8F3F68C56B3CA4B');
        $this->addSql('ALTER TABLE "manufacturers" DROP CONSTRAINT FK_94565B12727ACA70');
        $this->addSql('ALTER TABLE "manufacturers" DROP CONSTRAINT FK_94565B12EA7100A1');
        $this->addSql('ALTER TABLE "measurement_units" DROP CONSTRAINT FK_F5AF83CF727ACA70');
        $this->addSql('ALTER TABLE "measurement_units" DROP CONSTRAINT FK_F5AF83CFEA7100A1');
        $this->addSql('ALTER TABLE "orderdetails" DROP CONSTRAINT FK_489AFCDC4CE34BEC');
        $this->addSql('ALTER TABLE "orderdetails" DROP CONSTRAINT FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE part_association DROP CONSTRAINT FK_61B952E07E3C61F9');
        $this->addSql('ALTER TABLE part_association DROP CONSTRAINT FK_61B952E0998D9879');
        $this->addSql('ALTER TABLE part_lots DROP CONSTRAINT FK_EBC8F9435D8F4B37');
        $this->addSql('ALTER TABLE part_lots DROP CONSTRAINT FK_EBC8F943C22F6CC4');
        $this->addSql('ALTER TABLE part_lots DROP CONSTRAINT FK_EBC8F94321E5A74C');
        $this->addSql('ALTER TABLE "parts" DROP CONSTRAINT FK_6940A7FEEA7100A1');
        $this->addSql('ALTER TABLE "parts" DROP CONSTRAINT FK_6940A7FE5697F554');
        $this->addSql('ALTER TABLE "parts" DROP CONSTRAINT FK_6940A7FE7E371A10');
        $this->addSql('ALTER TABLE "parts" DROP CONSTRAINT FK_6940A7FE2626CEF9');
        $this->addSql('ALTER TABLE "parts" DROP CONSTRAINT FK_6940A7FE1ECB93AE');
        $this->addSql('ALTER TABLE "parts" DROP CONSTRAINT FK_6940A7FE81081E9B');
        $this->addSql('ALTER TABLE "parts" DROP CONSTRAINT FK_6940A7FEE8AE70D9');
        $this->addSql('ALTER TABLE "pricedetails" DROP CONSTRAINT FK_C68C4459398D64AA');
        $this->addSql('ALTER TABLE "pricedetails" DROP CONSTRAINT FK_C68C44594A01DDC7');
        $this->addSql('ALTER TABLE project_bom_entries DROP CONSTRAINT FK_1AA2DD312F180363');
        $this->addSql('ALTER TABLE project_bom_entries DROP CONSTRAINT FK_1AA2DD31C22F6CC4');
        $this->addSql('ALTER TABLE project_bom_entries DROP CONSTRAINT FK_1AA2DD313FFDCD60');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A4727ACA70');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A4EA7100A1');
        $this->addSql('ALTER TABLE "storelocations" DROP CONSTRAINT FK_7517020727ACA70');
        $this->addSql('ALTER TABLE "storelocations" DROP CONSTRAINT FK_7517020B270BFF1');
        $this->addSql('ALTER TABLE "storelocations" DROP CONSTRAINT FK_751702021E5A74C');
        $this->addSql('ALTER TABLE "storelocations" DROP CONSTRAINT FK_7517020EA7100A1');
        $this->addSql('ALTER TABLE "suppliers" DROP CONSTRAINT FK_AC28B95C727ACA70');
        $this->addSql('ALTER TABLE "suppliers" DROP CONSTRAINT FK_AC28B95CECD792C0');
        $this->addSql('ALTER TABLE "suppliers" DROP CONSTRAINT FK_AC28B95CEA7100A1');
        $this->addSql('ALTER TABLE u2f_keys DROP CONSTRAINT FK_4F4ADB4BA76ED395');
        $this->addSql('ALTER TABLE "users" DROP CONSTRAINT FK_1483A5E9FE54D947');
        $this->addSql('ALTER TABLE "users" DROP CONSTRAINT FK_1483A5E9EA7100A1');
        $this->addSql('ALTER TABLE "users" DROP CONSTRAINT FK_1483A5E938248176');
        $this->addSql('ALTER TABLE webauthn_keys DROP CONSTRAINT FK_799FD143A76ED395');
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql('DROP TABLE "attachment_types"');
        $this->addSql('DROP TABLE "attachments"');
        $this->addSql('DROP TABLE "categories"');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('DROP TABLE "footprints"');
        $this->addSql('DROP TABLE "groups"');
        $this->addSql('DROP TABLE label_profiles');
        $this->addSql('DROP TABLE log');
        $this->addSql('DROP TABLE "manufacturers"');
        $this->addSql('DROP TABLE "measurement_units"');
        $this->addSql('DROP TABLE oauth_tokens');
        $this->addSql('DROP TABLE "orderdetails"');
        $this->addSql('DROP TABLE parameters');
        $this->addSql('DROP TABLE part_association');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('DROP TABLE "pricedetails"');
        $this->addSql('DROP TABLE project_bom_entries');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE "storelocations"');
        $this->addSql('DROP TABLE "suppliers"');
        $this->addSql('DROP TABLE u2f_keys');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('DROP TABLE webauthn_keys');
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for MySQL");
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for MySQL");
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Sqlite");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Sqlite");
    }
}
