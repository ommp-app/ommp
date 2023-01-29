--
-- Online Module Management Platform
-- 
-- SQL uninstallation file for ommp module
-- 
-- Author: The OMMP Team
-- Version: 1.0
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Delete the tables
DROP TABLE IF EXISTS `{PREFIX}config`;
DROP TABLE IF EXISTS `{PREFIX}groups`;
DROP TABLE IF EXISTS `{PREFIX}groups_members`;
DROP TABLE IF EXISTS `{PREFIX}modules`;
DROP TABLE IF EXISTS `{PREFIX}rights`;
COMMIT;
