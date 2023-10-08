--
-- Online Module Management Platform
-- 
-- SQL installation file for registration module
-- 
-- Author: The OMMP Team
-- Version: 1.0
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Creates the users table
DROP TABLE IF EXISTS `{PREFIX}users`;
CREATE TABLE IF NOT EXISTS `{PREFIX}users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `longname` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_time` int(11) NOT NULL DEFAULT '0',
  `lang` varchar(2) COLLATE utf8mb4_bin NOT NULL,
  `certified` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
COMMIT;
