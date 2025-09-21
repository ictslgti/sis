-- Schema for group-based timetable storage used by timetable/GroupTimetable.php
-- Safe to run multiple times
CREATE TABLE IF NOT EXISTS `group_timetable` (
  `timetable_id` INT NOT NULL AUTO_INCREMENT,
  `group_id` INT NOT NULL,
  `module_id` VARCHAR(64) NOT NULL,
  `staff_id` VARCHAR(64) NOT NULL,
  `weekday` TINYINT(1) NOT NULL COMMENT '1=Mon ... 7=Sun',
  `period` ENUM('P1','P2','P3','P4') NOT NULL,
  `classroom` VARCHAR(64) DEFAULT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`timetable_id`),
  KEY `idx_group_day_period_active` (`group_id`,`weekday`,`period`,`active`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_staff_period` (`staff_id`,`weekday`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
