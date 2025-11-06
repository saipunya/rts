-- Run this in your MySQL (e.g., phpMyAdmin) after creating the database.
-- Change the database used if needed with: USE rts;

CREATE TABLE IF NOT EXISTS `tbl_member` (
  `mem_id` INT(11) NOT NULL AUTO_INCREMENT,
  `mem_group` VARCHAR(255) NOT NULL,
  `mem_number` VARCHAR(255) NOT NULL,
  `mem_fullname` VARCHAR(255) NOT NULL,
  `mem_class` VARCHAR(255) NOT NULL,
  `mem_saveby` VARCHAR(255) NOT NULL,
  `mem_savedate` DATE NOT NULL,
  PRIMARY KEY (`mem_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
