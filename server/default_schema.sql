DROP SCHEMA IF EXISTS `main`;
CREATE SCHEMA IF NOT EXISTS `main` DEFAULT CHARACTER SET utf8mb4 ;

USE `main`;

CREATE TABLE `user` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email_hash` VARCHAR(32) NULL,
  `created_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`));
