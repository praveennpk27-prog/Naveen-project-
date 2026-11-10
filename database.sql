-- ============================================================================
-- Hostinger MySQL Database Schema for Anaesthesiologist Logbook
-- Supports 3-Tier Plans:
--   1. 'free'    -> Local storage only (credentials stored in `doctors`)
--   2. 'elite'   -> Google Drive sync enabled
--   3. 'premium' -> Full patient case storage in Hostinger MySQL (`doctors.cases_json` & `logbook_cases`)
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- ----------------------------------------------------------------------------
-- 1. Table structure for `doctors`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_name` VARCHAR(150) NOT NULL,
  `mobile_number` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `plan` VARCHAR(20) NOT NULL DEFAULT 'free',
  `requested_plan` VARCHAR(20) DEFAULT '',
  `plan_status` VARCHAR(20) NOT NULL DEFAULT 'none',
  `profile_json` LONGTEXT DEFAULT NULL,
  `cases_json` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mobile_number` (`mobile_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table structure for `logbook_cases` (Used for Premium Plan doctors)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logbook_cases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` INT UNSIGNED NOT NULL,
  `case_uid` VARCHAR(64) NOT NULL,
  `case_date` DATE DEFAULT NULL,
  `hospital` VARCHAR(200) DEFAULT NULL,
  `patient_id` VARCHAR(100) DEFAULT NULL,
  `age` VARCHAR(20) DEFAULT NULL,
  `sex` VARCHAR(20) DEFAULT NULL,
  `priority` VARCHAR(30) DEFAULT NULL,
  `supervision` VARCHAR(60) DEFAULT NULL,
  `specialty` VARCHAR(120) DEFAULT NULL,
  `diagnosis` VARCHAR(255) DEFAULT NULL,
  `procedure_name` VARCHAR(255) DEFAULT NULL,
  `asa_status` VARCHAR(20) DEFAULT NULL,
  `technique` VARCHAR(200) DEFAULT NULL,
  `case_json` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doctor_case` (`doctor_id`, `case_uid`),
  KEY `idx_doctor_id` (`doctor_id`),
  CONSTRAINT `fk_cases_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
