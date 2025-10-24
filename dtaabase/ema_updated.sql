-- Additional tables for the revised EMA system
-- Run this after the existing ema.sql

-- Update inventory_items table to include categories
ALTER TABLE `inventory_items` 
MODIFY COLUMN `category` ENUM('ema_camp','cafe','mami_lc_laundry','inventaris_harian') NOT NULL DEFAULT 'inventaris_harian';

-- Update classes table for new martial arts and class types
ALTER TABLE `classes` 
MODIFY COLUMN `martial_art_type` ENUM('kickboxing','boxing','savate','taekwondo','mix') NOT NULL,
MODIFY COLUMN `class_type` ENUM('walk_in_no_coach','walk_in_with_coach','regular_6x','regular_8x','regular_10x','private_6x','private_8x','private_10x') NOT NULL;

-- Add address and emergency contact to users table
ALTER TABLE `users` 
ADD COLUMN `address` TEXT DEFAULT NULL COMMENT 'Alamat lengkap',
ADD COLUMN `emergency_contact_name` VARCHAR(100) DEFAULT NULL COMMENT 'Nama kontak darurat',
ADD COLUMN `emergency_contact_phone` VARCHAR(15) DEFAULT NULL COMMENT 'No telp kontak darurat',
ADD COLUMN `default_username` VARCHAR(50) DEFAULT NULL COMMENT 'Username default saat pendaftaran',
ADD COLUMN `default_password` VARCHAR(255) DEFAULT NULL COMMENT 'Password default saat pendaftaran',
ADD COLUMN `terms_accepted` TINYINT(1) DEFAULT 0 COMMENT 'Syarat dan ketentuan';

-- Create attendance system table
CREATE TABLE IF NOT EXISTS `member_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_trainer_id` (`trainer_id`),
  KEY `idx_attendance_date` (`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create trainer attendance table
CREATE TABLE IF NOT EXISTS `trainer_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trainer_id` (`trainer_id`),
  KEY `idx_attendance_date` (`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create shift change requests table
CREATE TABLE IF NOT EXISTS `shift_change_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `target_trainer_id` int(11) DEFAULT NULL,
  `request_date` date NOT NULL,
  `original_schedule` text NOT NULL,
  `requested_schedule` text DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_trainer_id` (`trainer_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create outgoing payments table for operational expenses
CREATE TABLE IF NOT EXISTS `outgoing_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_category` enum('trainer_salary','electricity','water','rent','maintenance','supplies','other') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','transfer','e_wallet','qris') NOT NULL,
  `payment_date` date NOT NULL,
  `recipient` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payment_category` (`payment_category`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create business category payments table
CREATE TABLE IF NOT EXISTS `category_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` enum('ema_camp','mami_lc_laundry','cafe') NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` enum('membership','service','product','other') NOT NULL,
  `payment_method` enum('cash','transfer','e_wallet','qris') NOT NULL,
  `payment_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'paid',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `idx_category` (`category`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Update payments table to support QRIS
ALTER TABLE `payments` 
MODIFY COLUMN `payment_method` ENUM('cash','transfer','e_wallet','credit_card','qris') NOT NULL;

-- Create manual events table for schedule activities
CREATE TABLE IF NOT EXISTS `manual_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(200) NOT NULL,
  `event_type` enum('manual') DEFAULT 'manual',
  `category` enum('ema_camp','mami_lc_laundry','cafe','general') DEFAULT 'general',
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create profit loss reports table
CREATE TABLE IF NOT EXISTS `financial_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` enum('profit','loss','income','outcome') NOT NULL,
  `category` enum('ema_camp','mami_lc_laundry','cafe','general') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `report_date` date NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_table` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report_type` (`report_type`),
  KEY `idx_category` (`category`),
  KEY `idx_date` (`report_date`),
  KEY `idx_month_year` (`month`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create user registration terms table
CREATE TABLE IF NOT EXISTS `registration_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_text` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default registration terms
INSERT INTO `registration_terms` (`term_text`, `is_active`, `created_by`) VALUES 
('Saya setuju untuk mematuhi semua peraturan dan tata tertib EMA Camp', 1, 1),
('Saya bertanggung jawab penuh atas keamanan barang pribadi saya', 1, 1),
('Saya memahami risiko cedera dalam olahraga bela diri dan bersedia menanggung risiko tersebut', 1, 1),
('Saya setuju untuk membayar iuran tepat waktu sesuai dengan ketentuan yang berlaku', 1, 1),
('Saya akan menjaga kebersihan dan kenyamanan fasilitas bersama', 1, 1);

-- Update inventory_items categories with proper enum
UPDATE `inventory_items` SET `category` = 'inventaris_harian' WHERE `category` IN ('beverage','equipment','supplement','merchandise','other');

-- Add some sample data for new categories
INSERT INTO `inventory_items` (`item_code`, `item_name`, `category`, `description`, `unit`, `current_stock`, `min_stock`, `unit_price`, `location`, `is_active`, `created_by`) VALUES
-- EMA Camp items
('EMA001', 'Sarung Tinju 14oz', 'ema_camp', 'Sarung tinju profesional', 'pasang', 15, 5, 450000.00, 'Gudang EMA - Equipment', 1, 1),
('EMA002', 'Pelindung Kaki Kickboxing', 'ema_camp', 'Shin guard kickboxing', 'pasang', 20, 8, 300000.00, 'Gudang EMA - Equipment', 1, 1),
('EMA003', 'Mouth Guard', 'ema_camp', 'Pelindung mulut', 'pcs', 50, 10, 75000.00, 'Gudang EMA - Equipment', 1, 1),

-- Cafe items
('CAFE001', 'Kopi Arabica 1kg', 'cafe', 'Kopi arabica premium', 'kg', 25, 5, 120000.00, 'Gudang Cafe - Bahan', 1, 1),
('CAFE002', 'Gula Pasir 1kg', 'cafe', 'Gula pasir putih', 'kg', 40, 10, 15000.00, 'Gudang Cafe - Bahan', 1, 1),
('CAFE003', 'Susu UHT 1L', 'cafe', 'Susu segar ultra', 'kotak', 60, 20, 18000.00, 'Gudang Cafe - Bahan', 1, 1),

-- Mami LC Laundry items
('LAUN001', 'Deterjen Powder 5kg', 'mami_lc_laundry', 'Deterjen bubuk industri', 'sak', 10, 3, 85000.00, 'Gudang Laundry - Chemical', 1, 1),
('LAUN002', 'Softener 5L', 'mami_lc_laundry', 'Pelembut pakaian', 'jerigen', 8, 2, 45000.00, 'Gudang Laundry - Chemical', 1, 1),
('LAUN003', 'Pemutih Pakaian 1L', 'mami_lc_laundry', 'Pemutih untuk pakaian putih', 'botol', 25, 8, 12000.00, 'Gudang Laundry - Chemical', 1, 1);