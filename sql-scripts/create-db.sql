CREATE SCHEMA `job_manager` ;
CREATE TABLE `job_manager`.`jobs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NULL,
  `interval_minutes` INT DEFAULT '0' NOT NULL,
  `last_start` DATETIME NULL,
  `last_end` DATETIME NULL,
  `last_error` VARCHAR(2000) NULL,
  `state` VARCHAR(45) default 'idle' NULL,
  `command` VARCHAR(2000) NULL,
  `command_params` VARCHAR(2000) NULL,
  `cleanup_script` VARCHAR(2000) NULL,
  `recovery_script` VARCHAR(2000) NULL,
  `next_run` DATETIME default now() NULL,
  PRIMARY KEY (`id`));

