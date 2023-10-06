--
-- Online Module Management Platform
-- 
-- SQL installation file for ommp module
-- 
-- Author: The OMMP Team
-- Version: 1.0
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Creates the config table
DROP TABLE IF EXISTS `{PREFIX}config`;
CREATE TABLE IF NOT EXISTS `{PREFIX}config` (
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Creates the groups table
DROP TABLE IF EXISTS `{PREFIX}groups`;
CREATE TABLE IF NOT EXISTS `{PREFIX}groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Creates default groups
INSERT INTO `{PREFIX}groups` (`id`, `name`, `description`) VALUES
(1, '{L:ADMINISTRATORS}', '{L:ADMINISTRATORS_DESCRIPTION}'),
(2, '{L:CLASSIC_USERS}', '{L:CLASSIC_USERS_DESCRIPTION}'),
(3, '{L:VISITORS}', '{L:VISITORS_DESCRIPTION}');

-- Creates the groups_members table
DROP TABLE IF EXISTS `{PREFIX}groups_members`;
CREATE TABLE IF NOT EXISTS `{PREFIX}groups_members` (
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Add visitor to it's group
INSERT INTO `{PREFIX}groups_members` (`group_id`, `user_id`) VALUES
(3, 0);

-- Creates the modules table
DROP TABLE IF EXISTS `{PREFIX}modules`;
CREATE TABLE IF NOT EXISTS `{PREFIX}modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `priority` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Creates the rights table
DROP TABLE IF EXISTS `{PREFIX}rights`;
CREATE TABLE IF NOT EXISTS `{PREFIX}rights` (
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL,
  `protected` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
