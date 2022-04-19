-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Erstellungszeit: 19. Apr 2022 um 23:42
-- Server-Version: 10.5.15-MariaDB-0+deb11u1
-- PHP-Version: 7.4.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `glasshousedb`
--
CREATE DATABASE IF NOT EXISTS `glasshousedb` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `glasshousedb`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tbl_hosts`
--

CREATE TABLE IF NOT EXISTS `tbl_hosts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Host ID - Primary Key',
  `name` varchar(255) NOT NULL COMMENT 'Host Name',
  `version` varchar(255) NOT NULL COMMENT 'Glasshouse version the host is currently running.',
  `mac` varchar(17) NOT NULL COMMENT 'Host Mac Address',
  `local_ip` int(10) UNSIGNED NOT NULL COMMENT 'Hosts Local IP Address',
  `gateway_ip` int(10) UNSIGNED NOT NULL COMMENT 'Hosts Gateway IP Address',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tbl_weatherdata`
--

CREATE TABLE IF NOT EXISTS `tbl_weatherdata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Internal entry id - primary key',
  `host_id` bigint(20) DEFAULT NULL COMMENT 'Host id from which the data came',
  `humidity` float(10,8) NOT NULL COMMENT 'Humidity inside the glasshouse.',
  `soil_moisture` int(3) NOT NULL COMMENT 'Soil moisture from the soil moisture sensor.',
  `temperature` float(10,8) NOT NULL COMMENT 'Temperature inside the glasshouse.',
  `heat_index` float(10,8) NOT NULL COMMENT 'Heat index inside the glasshouse.',
  `time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cst_host_id` (`host_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
