-- =====================================================
-- Student Season System - Simplified Schema (2 Tables)
-- FOREIGN KEYS REMOVED (student_id compatibility issue fixed)
-- =====================================================

-- Table 1: Season Requests
CREATE TABLE IF NOT EXISTS `season_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(64) NOT NULL,
  `season_year` VARCHAR(20) NOT NULL COMMENT 'e.g., 2025/2026',
  `season_name` VARCHAR(100) NOT NULL COMMENT 'e.g., 2025-2026 Bus Season',
  `route_from` VARCHAR(255) NOT NULL,
  `route_to` VARCHAR(255) NOT NULL,
  `change_point` VARCHAR(255) DEFAULT NULL,
  `distance_km` DECIMAL(6,2) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` VARCHAR(64) DEFAULT NULL COMMENT 'HOD, Administration, or Director',
  `approved_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_season` (`student_id`,`season_year`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_season` (`season_year`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- Table 2: Season Payments
CREATE TABLE IF NOT EXISTS `season_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT UNSIGNED NOT NULL,
  `student_id` VARCHAR(64) NOT NULL,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total paid so far',
  `season_rate` DECIMAL(10,2) NOT NULL COMMENT 'Total season rate amount',
  `total_amount` DECIMAL(10,2) NOT NULL COMMENT 'Same as season_rate',
  `student_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Student portion paid',
  `slgti_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'SLGTI portion paid',
  `ctb_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'CTB portion paid',
  `remaining_balance` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Paid','Completed') NOT NULL DEFAULT 'Paid',
  `payment_date` DATE DEFAULT NULL,
  `payment_method` ENUM('Cash','Bank Transfer') DEFAULT NULL,
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `collected_by` VARCHAR(64) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_request_payment` (`request_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
