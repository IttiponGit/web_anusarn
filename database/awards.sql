-- Awards table for the information portal.
-- Import this file when the existing database does not already have awards.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `awards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `award_group` ENUM('student', 'staff', 'institution') NOT NULL,
  `award_year` VARCHAR(10) NULL,
  `category` VARCHAR(255) NULL,
  `organizer` VARCHAR(255) NULL,
  `level` VARCHAR(100) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_awards_active_group_order` (`is_active`, `award_group`, `sort_order`, `id`),
  KEY `idx_awards_year` (`award_year`),
  KEY `idx_awards_category` (`category`),
  KEY `idx_awards_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
