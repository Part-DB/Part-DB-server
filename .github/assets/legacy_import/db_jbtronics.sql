-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 07. Mai 2023 um 01:58
-- Server-Version: 10.6.5-MariaDB-log
-- PHP-Version: 8.1.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `partdb_demo`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `attachements`
--

CREATE TABLE `attachements` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `class_name` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `element_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `filename` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `show_in_table` tinyint(1) NOT NULL DEFAULT 0,
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `attachements`
--

INSERT INTO `attachements` (`id`, `name`, `class_name`, `element_id`, `type_id`, `filename`, `show_in_table`, `last_modified`) VALUES
(1, 'BC547', 'Part', 2, 2, '%BASE%/data/media/bc547.pdf', 1, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `attachement_types`
--

CREATE TABLE `attachement_types` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `comment` text COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `attachement_types`
--

INSERT INTO `attachement_types` (`id`, `name`, `parent_id`, `comment`, `datetime_added`, `last_modified`) VALUES
(1, 'Bilder', NULL, NULL, '2017-10-21 17:58:48', '0000-00-00 00:00:00'),
(2, 'Datenblätter', NULL, NULL, '2017-10-21 17:58:48', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `disable_footprints` tinyint(1) NOT NULL DEFAULT 0,
  `disable_manufacturers` tinyint(1) NOT NULL DEFAULT 0,
  `disable_autodatasheets` tinyint(1) NOT NULL DEFAULT 0,
  `disable_properties` tinyint(1) NOT NULL DEFAULT 0,
  `partname_regex` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `partname_hint` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `default_description` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `default_comment` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `categories`
--

INSERT INTO `categories` (`id`, `name`, `parent_id`, `disable_footprints`, `disable_manufacturers`, `disable_autodatasheets`, `disable_properties`, `partname_regex`, `partname_hint`, `default_description`, `default_comment`, `comment`, `datetime_added`, `last_modified`) VALUES
(1, 'aktive Bauteile', NULL, 0, 0, 0, 0, '', '', '', '', NULL, '2017-10-21 17:58:49', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `order_quantity` int(11) NOT NULL DEFAULT 0,
  `order_only_missing_parts` tinyint(1) NOT NULL DEFAULT 0,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment` text COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `devices`
--

INSERT INTO `devices` (`id`, `name`, `parent_id`, `order_quantity`, `order_only_missing_parts`, `datetime_added`, `last_modified`, `comment`) VALUES
(1, 'Test', NULL, 0, 0, '2015-04-16 15:08:56', '0000-00-00 00:00:00', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `device_parts`
--

CREATE TABLE `device_parts` (
  `id` int(11) NOT NULL,
  `id_part` int(11) NOT NULL DEFAULT 0,
  `id_device` int(11) NOT NULL DEFAULT 0,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `mountnames` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `device_parts`
--

INSERT INTO `device_parts` (`id`, `id_part`, `id_device`, `quantity`, `mountnames`) VALUES
(1, 2, 1, 1, '');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `footprints`
--

CREATE TABLE `footprints` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `filename` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `filename_3d` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `comment` text COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `footprints`
--

INSERT INTO `footprints` (`id`, `name`, `filename`, `filename_3d`, `parent_id`, `comment`, `datetime_added`, `last_modified`) VALUES
(1, 'LEDs', '%BASE%/img/footprints/Optik/LEDs/Bedrahtet/LED-GELB_3MM.png', '', NULL, NULL, '2017-10-21 17:58:49', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `comment` mediumtext DEFAULT NULL,
  `perms_system` int(11) NOT NULL,
  `perms_groups` int(11) NOT NULL,
  `perms_users` int(11) NOT NULL,
  `perms_self` int(11) NOT NULL,
  `perms_system_config` int(11) NOT NULL,
  `perms_system_database` int(11) NOT NULL,
  `perms_parts` bigint(11) NOT NULL,
  `perms_parts_name` smallint(6) NOT NULL,
  `perms_parts_description` smallint(6) NOT NULL,
  `perms_parts_instock` smallint(6) NOT NULL,
  `perms_parts_mininstock` smallint(6) NOT NULL,
  `perms_parts_footprint` smallint(6) NOT NULL,
  `perms_parts_storelocation` smallint(6) NOT NULL,
  `perms_parts_manufacturer` smallint(6) NOT NULL,
  `perms_parts_comment` smallint(6) NOT NULL,
  `perms_parts_order` smallint(6) NOT NULL,
  `perms_parts_orderdetails` smallint(6) NOT NULL,
  `perms_parts_prices` smallint(6) NOT NULL,
  `perms_parts_attachements` smallint(6) NOT NULL,
  `perms_devices` int(11) NOT NULL,
  `perms_devices_parts` int(11) NOT NULL,
  `perms_storelocations` int(11) NOT NULL,
  `perms_footprints` int(11) NOT NULL,
  `perms_categories` int(11) NOT NULL,
  `perms_suppliers` int(11) NOT NULL,
  `perms_manufacturers` int(11) NOT NULL,
  `perms_attachement_types` int(11) NOT NULL,
  `perms_tools` int(11) NOT NULL,
  `perms_labels` smallint(6) NOT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Daten für Tabelle `groups`
--

INSERT INTO `groups` (`id`, `name`, `parent_id`, `comment`, `perms_system`, `perms_groups`, `perms_users`, `perms_self`, `perms_system_config`, `perms_system_database`, `perms_parts`, `perms_parts_name`, `perms_parts_description`, `perms_parts_instock`, `perms_parts_mininstock`, `perms_parts_footprint`, `perms_parts_storelocation`, `perms_parts_manufacturer`, `perms_parts_comment`, `perms_parts_order`, `perms_parts_orderdetails`, `perms_parts_prices`, `perms_parts_attachements`, `perms_devices`, `perms_devices_parts`, `perms_storelocations`, `perms_footprints`, `perms_categories`, `perms_suppliers`, `perms_manufacturers`, `perms_attachement_types`, `perms_tools`, `perms_labels`, `datetime_added`, `last_modified`) VALUES
(1, 'admins', NULL, 'Users of this group can do everything: Read, Write and Administrative actions.', 21, 1365, 87381, 85, 85, 21, 1431655765, 5, 5, 5, 5, 5, 5, 5, 5, 5, 325, 325, 325, 5461, 325, 5461, 5461, 5461, 5461, 5461, 1365, 1365, 85, '2017-10-21 17:58:46', '2018-10-08 17:27:41'),
(2, 'readonly', NULL, 'Users of this group can only read informations, use tools, and don\'t have access to administrative tools.', 2, 2730, 43690, 25, 170, 42, 2778027689, 9, 9, 9, 9, 9, 9, 9, 9, 9, 649, 649, 649, 1705, 649, 1705, 1705, 1705, 1705, 1705, 681, 1366, 165, '2017-10-21 17:58:46', '2018-10-08 17:28:35'),
(3, 'users', NULL, 'Users of this group, can edit part informations, create new ones, etc. but are not allowed to use administrative tools. (But can read current configuration, and see Server status)', 42, 2730, 43689, 89, 105, 41, 1431655765, 5, 5, 5, 5, 5, 5, 5, 5, 5, 325, 325, 325, 5461, 325, 5461, 5461, 5461, 5461, 5461, 1365, 1365, 85, '2017-10-21 17:58:46', '2018-10-08 17:28:17');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `internal`
--

CREATE TABLE `internal` (
  `keyName` char(30) CHARACTER SET ascii NOT NULL,
  `keyValue` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `internal`
--

INSERT INTO `internal` (`keyName`, `keyValue`) VALUES
('dbVersion', '26');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `log`
--

CREATE TABLE `log` (
  `id` int(11) NOT NULL,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_user` int(11) NOT NULL,
  `level` tinyint(4) NOT NULL,
  `type` smallint(6) NOT NULL,
  `target_id` int(11) NOT NULL,
  `target_type` smallint(6) NOT NULL,
  `extra` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `manufacturers`
--

CREATE TABLE `manufacturers` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `address` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `phone_number` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `fax_number` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `email_address` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `website` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `auto_product_url` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `comment` text COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `manufacturers`
--

INSERT INTO `manufacturers` (`id`, `name`, `parent_id`, `address`, `phone_number`, `fax_number`, `email_address`, `website`, `auto_product_url`, `datetime_added`, `comment`, `last_modified`) VALUES
(1, 'Atmel', NULL, '', '', '', '', '', '', '2015-03-01 11:27:10', NULL, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `orderdetails`
--

CREATE TABLE `orderdetails` (
  `id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `id_supplier` int(11) NOT NULL DEFAULT 0,
  `supplierpartnr` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `obsolete` tinyint(1) DEFAULT 0,
  `supplier_product_url` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `parts`
--

CREATE TABLE `parts` (
  `id` int(11) NOT NULL,
  `id_category` int(11) NOT NULL DEFAULT 0,
  `name` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `instock` int(11) NOT NULL DEFAULT 0,
  `mininstock` int(11) NOT NULL DEFAULT 0,
  `comment` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL,
  `id_footprint` int(11) DEFAULT NULL,
  `id_storelocation` int(11) DEFAULT NULL,
  `order_orderdetails_id` int(11) DEFAULT NULL,
  `order_quantity` int(11) NOT NULL DEFAULT 1,
  `manual_order` tinyint(1) NOT NULL DEFAULT 0,
  `id_manufacturer` int(11) DEFAULT NULL,
  `id_master_picture_attachement` int(11) DEFAULT NULL,
  `manufacturer_product_url` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `favorite` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `parts`
--

INSERT INTO `parts` (`id`, `id_category`, `name`, `description`, `instock`, `mininstock`, `comment`, `visible`, `id_footprint`, `id_storelocation`, `order_orderdetails_id`, `order_quantity`, `manual_order`, `id_manufacturer`, `id_master_picture_attachement`, `manufacturer_product_url`, `datetime_added`, `last_modified`, `favorite`) VALUES
(2, 1, 'BC547C', 'NPN 45V 0,1A 0,5W', 59, 0, '', 0, 1, 1, NULL, 1, 0, NULL, NULL, '', '2015-03-01 10:40:31', '2016-12-26 10:48:49', 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pricedetails`
--

CREATE TABLE `pricedetails` (
  `id` int(11) NOT NULL,
  `orderdetails_id` int(11) NOT NULL,
  `price` decimal(11,5) DEFAULT NULL,
  `price_related_quantity` int(11) NOT NULL DEFAULT 1,
  `min_discount_quantity` int(11) NOT NULL DEFAULT 1,
  `manual_input` tinyint(1) NOT NULL DEFAULT 1,
  `last_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `storelocations`
--

CREATE TABLE `storelocations` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_full` tinyint(1) NOT NULL DEFAULT 0,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `comment` text COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `storelocations`
--

INSERT INTO `storelocations` (`id`, `name`, `parent_id`, `is_full`, `datetime_added`, `comment`, `last_modified`) VALUES
(1, 'Halbleiter I', NULL, 0, '2015-03-01 11:26:37', NULL, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `address` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `phone_number` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `fax_number` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `email_address` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `website` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `auto_product_url` tinytext COLLATE utf8mb3_unicode_ci NOT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `comment` text COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `parent_id`, `address`, `phone_number`, `fax_number`, `email_address`, `website`, `auto_product_url`, `datetime_added`, `comment`, `last_modified`) VALUES
(1, 'Test', NULL, '', '', '', '', '', 'Test', '2015-03-01 10:37:23', NULL, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_name` tinytext DEFAULT NULL,
  `last_name` tinytext DEFAULT NULL,
  `department` tinytext DEFAULT NULL,
  `email` tinytext DEFAULT NULL,
  `need_pw_change` tinyint(1) NOT NULL DEFAULT 0,
  `group_id` int(11) DEFAULT NULL,
  `config_language` tinytext DEFAULT NULL,
  `config_timezone` tinytext DEFAULT NULL,
  `config_theme` tinytext DEFAULT NULL,
  `config_currency` tinytext DEFAULT NULL,
  `config_image_path` text NOT NULL,
  `config_instock_comment_w` text NOT NULL,
  `config_instock_comment_a` text NOT NULL,
  `perms_system` int(11) NOT NULL,
  `perms_groups` int(11) NOT NULL,
  `perms_users` int(11) NOT NULL,
  `perms_self` int(11) NOT NULL,
  `perms_system_config` int(11) NOT NULL,
  `perms_system_database` int(11) NOT NULL,
  `perms_parts` bigint(11) NOT NULL,
  `perms_parts_name` smallint(6) NOT NULL,
  `perms_parts_description` smallint(6) NOT NULL,
  `perms_parts_instock` smallint(6) NOT NULL,
  `perms_parts_mininstock` smallint(6) NOT NULL,
  `perms_parts_footprint` smallint(6) NOT NULL,
  `perms_parts_storelocation` smallint(6) NOT NULL,
  `perms_parts_manufacturer` smallint(6) NOT NULL,
  `perms_parts_comment` smallint(6) NOT NULL,
  `perms_parts_order` smallint(6) NOT NULL,
  `perms_parts_orderdetails` smallint(6) NOT NULL,
  `perms_parts_prices` smallint(6) NOT NULL,
  `perms_parts_attachements` smallint(6) NOT NULL,
  `perms_devices` int(11) NOT NULL,
  `perms_devices_parts` int(11) NOT NULL,
  `perms_storelocations` int(11) NOT NULL,
  `perms_footprints` int(11) NOT NULL,
  `perms_categories` int(11) NOT NULL,
  `perms_suppliers` int(11) NOT NULL,
  `perms_manufacturers` int(11) NOT NULL,
  `perms_attachement_types` int(11) NOT NULL,
  `perms_tools` int(11) NOT NULL,
  `perms_labels` smallint(6) NOT NULL,
  `datetime_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `name`, `password`, `first_name`, `last_name`, `department`, `email`, `need_pw_change`, `group_id`, `config_language`, `config_timezone`, `config_theme`, `config_currency`, `config_image_path`, `config_instock_comment_w`, `config_instock_comment_a`, `perms_system`, `perms_groups`, `perms_users`, `perms_self`, `perms_system_config`, `perms_system_database`, `perms_parts`, `perms_parts_name`, `perms_parts_description`, `perms_parts_instock`, `perms_parts_mininstock`, `perms_parts_footprint`, `perms_parts_storelocation`, `perms_parts_manufacturer`, `perms_parts_comment`, `perms_parts_order`, `perms_parts_orderdetails`, `perms_parts_prices`, `perms_parts_attachements`, `perms_devices`, `perms_devices_parts`, `perms_storelocations`, `perms_footprints`, `perms_categories`, `perms_suppliers`, `perms_manufacturers`, `perms_attachement_types`, `perms_tools`, `perms_labels`, `datetime_added`, `last_modified`) VALUES
(1, 'anonymous', '', '', '', '', '', 0, 2, '', '', '', NULL, '', '', '', 21848, 20480, 0, 0, 0, 0, 0, 21840, 21840, 21840, 21840, 21840, 21840, 21840, 21840, 21840, 21520, 21520, 21520, 20480, 21520, 20480, 20480, 20480, 20480, 20480, 21504, 20480, 0, '2017-10-21 17:58:46', '2018-02-18 12:46:58'),
(2, 'admin', '$2a$12$j0RKrKlx60bzX1DWMyXwjeaW.pe3bFjAK8ByIGnvjrRnET2JtsFoe', 'Admin', 'Ad', NULL, 'admin@ras.pi', 0, 1, '', '', '', NULL, '', '', '', 21845, 21845, 21845, 21, 85, 21, 349525, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 0, '2017-10-21 17:58:46', '2017-12-23 11:04:48');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `attachements`
--
ALTER TABLE `attachements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attachements_class_name_k` (`class_name`),
  ADD KEY `attachements_element_id_k` (`element_id`),
  ADD KEY `attachements_type_id_fk` (`type_id`);

--
-- Indizes für die Tabelle `attachement_types`
--
ALTER TABLE `attachement_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attachement_types_parent_id_k` (`parent_id`);

--
-- Indizes für die Tabelle `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categories_parent_id_k` (`parent_id`);

--
-- Indizes für die Tabelle `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `devices_parent_id_k` (`parent_id`);

--
-- Indizes für die Tabelle `device_parts`
--
ALTER TABLE `device_parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_parts_combination_uk` (`id_part`,`id_device`),
  ADD KEY `device_parts_id_part_k` (`id_part`),
  ADD KEY `device_parts_id_device_k` (`id_device`);

--
-- Indizes für die Tabelle `footprints`
--
ALTER TABLE `footprints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `footprints_parent_id_k` (`parent_id`);

--
-- Indizes für die Tabelle `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indizes für die Tabelle `internal`
--
ALTER TABLE `internal`
  ADD UNIQUE KEY `keyName` (`keyName`);

--
-- Indizes für die Tabelle `log`
--
ALTER TABLE `log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Indizes für die Tabelle `manufacturers`
--
ALTER TABLE `manufacturers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manufacturers_parent_id_k` (`parent_id`);

--
-- Indizes für die Tabelle `orderdetails`
--
ALTER TABLE `orderdetails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orderdetails_part_id_k` (`part_id`),
  ADD KEY `orderdetails_id_supplier_k` (`id_supplier`);

--
-- Indizes für die Tabelle `parts`
--
ALTER TABLE `parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parts_id_category_k` (`id_category`),
  ADD KEY `parts_id_footprint_k` (`id_footprint`),
  ADD KEY `parts_id_storelocation_k` (`id_storelocation`),
  ADD KEY `parts_order_orderdetails_id_k` (`order_orderdetails_id`),
  ADD KEY `parts_id_manufacturer_k` (`id_manufacturer`),
  ADD KEY `favorite` (`favorite`);

--
-- Indizes für die Tabelle `pricedetails`
--
ALTER TABLE `pricedetails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pricedetails_combination_uk` (`orderdetails_id`,`min_discount_quantity`),
  ADD KEY `pricedetails_orderdetails_id_k` (`orderdetails_id`);

--
-- Indizes für die Tabelle `storelocations`
--
ALTER TABLE `storelocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `storelocations_parent_id_k` (`parent_id`);

--
-- Indizes für die Tabelle `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suppliers_parent_id_k` (`parent_id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `attachements`
--
ALTER TABLE `attachements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT für Tabelle `attachement_types`
--
ALTER TABLE `attachement_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT für Tabelle `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `device_parts`
--
ALTER TABLE `device_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT für Tabelle `footprints`
--
ALTER TABLE `footprints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT für Tabelle `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `log`
--
ALTER TABLE `log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=218;

--
-- AUTO_INCREMENT für Tabelle `manufacturers`
--
ALTER TABLE `manufacturers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `orderdetails`
--
ALTER TABLE `orderdetails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=650;

--
-- AUTO_INCREMENT für Tabelle `parts`
--
ALTER TABLE `parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1171;

--
-- AUTO_INCREMENT für Tabelle `pricedetails`
--
ALTER TABLE `pricedetails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=437;

--
-- AUTO_INCREMENT für Tabelle `storelocations`
--
ALTER TABLE `storelocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=340;

--
-- AUTO_INCREMENT für Tabelle `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `attachements`
--
ALTER TABLE `attachements`
  ADD CONSTRAINT `attachements_type_id_fk` FOREIGN KEY (`type_id`) REFERENCES `attachement_types` (`id`);

--
-- Constraints der Tabelle `attachement_types`
--
ALTER TABLE `attachement_types`
  ADD CONSTRAINT `attachement_types_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `attachement_types` (`id`);

--
-- Constraints der Tabelle `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`);

--
-- Constraints der Tabelle `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `devices` (`id`);

--
-- Constraints der Tabelle `footprints`
--
ALTER TABLE `footprints`
  ADD CONSTRAINT `footprints_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `footprints` (`id`);

--
-- Constraints der Tabelle `manufacturers`
--
ALTER TABLE `manufacturers`
  ADD CONSTRAINT `manufacturers_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `manufacturers` (`id`);

--
-- Constraints der Tabelle `parts`
--
ALTER TABLE `parts`
  ADD CONSTRAINT `parts_id_footprint_fk` FOREIGN KEY (`id_footprint`) REFERENCES `footprints` (`id`),
  ADD CONSTRAINT `parts_id_manufacturer_fk` FOREIGN KEY (`id_manufacturer`) REFERENCES `manufacturers` (`id`),
  ADD CONSTRAINT `parts_id_storelocation_fk` FOREIGN KEY (`id_storelocation`) REFERENCES `storelocations` (`id`),
  ADD CONSTRAINT `parts_order_orderdetails_id_fk` FOREIGN KEY (`order_orderdetails_id`) REFERENCES `orderdetails` (`id`);

--
-- Constraints der Tabelle `storelocations`
--
ALTER TABLE `storelocations`
  ADD CONSTRAINT `storelocations_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `storelocations` (`id`);

--
-- Constraints der Tabelle `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `suppliers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
