-- ==============================================================================
-- Optical Shop Management CMS (optical-mgt)
-- 03_prescriptions_schema.sql - Database Schema for Prescription Module
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+ / phpMyAdmin
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table: prescriptions
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `prescriptions`;

CREATE TABLE `prescriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `prescription_date` DATE NOT NULL,
  
  -- Right Eye (OD - Oculus Dexter) Measurements
  `right_sph` DECIMAL(5,2) NULL,
  `right_cyl` DECIMAL(5,2) NULL,
  `right_axis` SMALLINT UNSIGNED NULL,
  `right_add` DECIMAL(4,2) NULL,
  `right_pd` DECIMAL(4,1) NULL,
  
  -- Left Eye (OS - Oculus Sinister) Measurements
  `left_sph` DECIMAL(5,2) NULL,
  `left_cyl` DECIMAL(5,2) NULL,
  `left_axis` SMALLINT UNSIGNED NULL,
  `left_add` DECIMAL(4,2) NULL,
  `left_pd` DECIMAL(4,1) NULL,
  
  -- Clinical & Examination Information
  `doctor_name` VARCHAR(150) NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_prescriptions_customer_id` (`customer_id`),
  KEY `idx_prescriptions_date` (`prescription_date`),
  KEY `idx_prescriptions_created_by` (`created_by`),
  
  CONSTRAINT `fk_prescriptions_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_prescriptions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
