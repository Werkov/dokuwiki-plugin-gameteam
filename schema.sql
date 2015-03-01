-- Adminer 4.1.0 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `login`;
CREATE TABLE `login` (
  `login_id` int(11) NOT NULL AUTO_INCREMENT,
  `login_name` varchar(20) COLLATE utf8_czech_ci DEFAULT NULL,
  `email` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `password` char(40) COLLATE utf8_czech_ci NOT NULL COMMENT 'SHA1 hash',
  PRIMARY KEY (`login_id`),
  UNIQUE KEY `volume_id_login` (`email`),
  KEY `volume_id_user_id` (`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


DROP TABLE IF EXISTS `player`;
CREATE TABLE `player` (
  `player_id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `display_name` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `phone` varchar(16) COLLATE utf8_czech_ci DEFAULT NULL,
  `email` varchar(64) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`player_id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `player_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `team` (`team_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


DROP TABLE IF EXISTS `team`;
CREATE TABLE `team` (
  `team_id` int(11) NOT NULL AUTO_INCREMENT,
  `volume_id` int(11) NOT NULL,
  `login_id` int(11) DEFAULT NULL,
  `name` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `registration_order` int(11) NOT NULL COMMENT 'pořadí registrace per ročník, ID pro platby',
  `phone` varchar(16) COLLATE utf8_czech_ci NOT NULL,
  `state` enum('00','10','90') COLLATE utf8_czech_ci NOT NULL COMMENT '00: registered, 10: paid, 90: cancelled',
  `sleeping` tinyint(1) NOT NULL COMMENT '1 iff nevadí jim spát na zemi',
  PRIMARY KEY (`team_id`),
  UNIQUE KEY `volume_id_name` (`volume_id`,`name`),
  UNIQUE KEY `volume_id_registration_order` (`volume_id`,`registration_order`),
  KEY `volume_id` (`volume_id`,`login_id`),
  KEY `login_id` (`login_id`),
  CONSTRAINT `team_ibfk_1` FOREIGN KEY (`login_id`) REFERENCES `login` (`login_id`) ON DELETE SET NULL,
  CONSTRAINT `team_ibfk_2` FOREIGN KEY (`volume_id`) REFERENCES `volume` (`volume_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


DROP TABLE IF EXISTS `volume`;
CREATE TABLE `volume` (
  `volume_id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_begin` datetime NOT NULL,
  `registration_end` datetime NOT NULL,
  `game_begin` date NOT NULL,
  `game_end` date NOT NULL,
  `team_capacity` tinyint(2) NOT NULL COMMENT 'počet členů týmu',
  `game_capacity` int(4) NOT NULL COMMENT 'počet týmů ve hře',
  `label` varchar(8) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`volume_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


-- 2015-03-01 10:17:51
