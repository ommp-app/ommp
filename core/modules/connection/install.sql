--
-- Online Module Management Platform
-- 
-- SQL installation file for connection module
-- 
-- Author: The OMMP Team
-- Version: 1.0
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Creates the sessions table
DROP TABLE IF EXISTS `{PREFIX}sessions`;
CREATE TABLE IF NOT EXISTS `{PREFIX}sessions` (
  `user_id` int(11) NOT NULL,
  `session_key` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `expire` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
COMMIT;
