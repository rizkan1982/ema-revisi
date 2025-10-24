-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 24, 2025 at 07:44 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ema`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendances`
--

CREATE TABLE `attendances` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `status` enum('present','late','absent','excused') NOT NULL DEFAULT 'absent',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `martial_art_type` enum('savate','kickboxing','boxing') NOT NULL,
  `class_type` enum('regular','private_6x','private_8x','private_10x') NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `max_participants` int(11) DEFAULT 20,
  `duration_minutes` int(11) DEFAULT 60,
  `monthly_fee` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `class_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `martial_art_type`, `class_type`, `trainer_id`, `max_participants`, `duration_minutes`, `monthly_fee`, `description`, `is_active`, `class_date`, `start_time`, `end_time`) VALUES
(1, 'Kickboxing Regular', 'kickboxing', 'regular', 1, 20, 90, 250000.00, 'Kelas kickboxing reguler untuk semua level', 1, NULL, NULL, NULL),
(2, 'Boxing Regular', 'boxing', 'regular', 1, 15, 60, 200000.00, 'Kelas boxing reguler untuk pemula hingga advanced', 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `event_type` enum('tournament','belt_test','seminar','camp','other') NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `registration_fee` decimal(10,2) DEFAULT 0.00,
  `max_participants` int(11) DEFAULT NULL,
  `registration_deadline` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_name`, `event_type`, `description`, `event_date`, `start_time`, `end_time`, `location`, `registration_fee`, `max_participants`, `registration_deadline`, `created_by`, `is_active`) VALUES
(1, 'Tanding', 'tournament', 'Tanding', '2025-12-01', '20:00:00', '12:00:00', 'EMA Camp', 150000.00, 1, '2025-12-10', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_history`
--

CREATE TABLE `inventory_history` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'ID barang',
  `transaction_type` enum('in','out','adjustment','initial') NOT NULL COMMENT 'Jenis transaksi',
  `quantity` int(11) NOT NULL COMMENT 'Jumlah',
  `stock_before` int(11) NOT NULL COMMENT 'Stok sebelum',
  `stock_after` int(11) NOT NULL COMMENT 'Stok setelah',
  `notes` text DEFAULT NULL COMMENT 'Catatan',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'Referensi',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID referensi',
  `performed_by` int(11) DEFAULT NULL COMMENT 'Dilakukan oleh',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_history`
--

INSERT INTO `inventory_history` (`id`, `item_id`, `transaction_type`, `quantity`, `stock_before`, `stock_after`, `notes`, `reference_type`, `reference_id`, `performed_by`, `created_at`) VALUES
(1, 7, 'initial', 10, 0, 10, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(2, 12, 'initial', 12, 0, 12, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(3, 4, 'initial', 15, 0, 15, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(4, 6, 'initial', 20, 0, 20, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(5, 8, 'initial', 25, 0, 25, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(6, 5, 'initial', 30, 0, 30, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(7, 10, 'initial', 35, 0, 35, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(8, 9, 'initial', 40, 0, 40, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(9, 2, 'initial', 50, 0, 50, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(10, 11, 'initial', 50, 0, 50, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(11, 3, 'initial', 75, 0, 75, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(12, 1, 'initial', 100, 0, 100, 'Stok awal sistem', NULL, NULL, 1, '2025-10-10 13:47:26'),
(16, 9, 'out', 3, 40, 37, 'Request disetujui untuk wefcwdw: eccec', NULL, NULL, 2, '2025-10-10 17:29:14'),
(17, 4, 'out', 3, 15, 12, 'Request disetujui untuk wefcwdw: perlu woi', NULL, NULL, 2, '2025-10-10 17:36:17'),
(18, 1, 'in', 150, 100, 250, 'ecd', NULL, NULL, 2, '2025-10-10 18:05:40');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL COMMENT 'Kode barang unik',
  `item_name` varchar(200) NOT NULL COMMENT 'Nama barang',
  `category` enum('beverage','equipment','supplement','merchandise','other') NOT NULL DEFAULT 'other' COMMENT 'Kategori barang',
  `description` text DEFAULT NULL COMMENT 'Deskripsi barang',
  `unit` varchar(50) DEFAULT 'pcs' COMMENT 'Satuan',
  `current_stock` int(11) DEFAULT 0 COMMENT 'Stok saat ini',
  `min_stock` int(11) DEFAULT 10 COMMENT 'Minimum stok',
  `unit_price` decimal(10,2) DEFAULT 0.00 COMMENT 'Harga satuan',
  `location` varchar(100) DEFAULT NULL COMMENT 'Lokasi penyimpanan',
  `expiry_date` date DEFAULT NULL COMMENT 'Tanggal kadaluarsa',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Status aktif',
  `created_by` int(11) DEFAULT NULL COMMENT 'Dibuat oleh user_id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_code`, `item_name`, `category`, `description`, `unit`, `current_stock`, `min_stock`, `unit_price`, `location`, `expiry_date`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'BEV001', 'Aqua Botol 600ml', 'beverage', 'Air mineral kemasan botol', 'botol', 250, 20, 3500.00, 'Gudang A - Rak 1', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 18:05:40'),
(2, 'BEV002', 'Pocari Sweat 500ml', 'beverage', 'Minuman isotonik', 'botol', 50, 15, 8000.00, 'Gudang A - Rak 1', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 13:47:26'),
(3, 'BEV003', 'Teh Botol Sosro 500ml', 'beverage', 'Teh dalam kemasan', 'botol', 75, 20, 5000.00, 'Gudang A - Rak 1', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-11 03:05:22'),
(4, 'EQP001', 'Sarung Tinju 12oz', 'equipment', 'Sarung tinju untuk latihan', 'pasang', 12, 5, 350000.00, 'Gudang B - Equipment', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 17:36:17'),
(5, 'EQP002', 'Hand Wrap 3m', 'equipment', 'Hand wrap boxing', 'buah', 30, 10, 50000.00, 'Gudang B - Equipment', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 13:47:26'),
(6, 'EQP003', 'Shin Guard', 'equipment', 'Pelindung kaki kickboxing', 'pasang', 20, 8, 250000.00, 'Gudang B - Equipment', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 13:47:26'),
(7, 'EQP004', 'Focus Pad', 'equipment', 'Pad target latihan', 'pasang', 10, 3, 400000.00, 'Gudang B - Equipment', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-11 03:04:21'),
(8, 'SUP001', 'Protein Whey 1kg', 'supplement', 'Suplemen protein recovery', 'box', 25, 5, 450000.00, 'Gudang A - Suplemen', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 13:47:26'),
(9, 'MER001', 'Kaos EMA Camp Size M', 'merchandise', 'Merchandise official', 'pcs', 37, 10, 100000.00, 'Gudang C - Merchandise', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 17:29:14'),
(10, 'MER002', 'Kaos EMA Camp Size L', 'merchandise', 'Merchandise official', 'pcs', 35, 10, 100000.00, 'Gudang C - Merchandise', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 13:47:26'),
(11, 'OTH001', 'Handuk Olahraga', 'other', 'Handuk gym', 'pcs', 50, 15, 25000.00, 'Gudang C - Lain-lain', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 13:47:26'),
(12, 'OTH002', 'Matras Yoga', 'other', 'Matras stretching', 'pcs', 12, 5, 150000.00, 'Gudang C - Lain-lain', NULL, 1, 1, '2025-10-10 13:47:26', '2025-10-10 13:47:26');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_requests`
--

CREATE TABLE `inventory_requests` (
  `id` int(11) NOT NULL,
  `request_code` varchar(50) NOT NULL COMMENT 'Kode request',
  `item_id` int(11) NOT NULL COMMENT 'ID barang',
  `requested_quantity` int(11) NOT NULL COMMENT 'Jumlah request',
  `request_type` enum('restock','purchase','usage') NOT NULL DEFAULT 'usage' COMMENT 'Jenis request',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium' COMMENT 'Prioritas',
  `reason` text DEFAULT NULL COMMENT 'Alasan',
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Status',
  `requested_by` int(11) NOT NULL COMMENT 'User yang request',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'Reviewer',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL COMMENT 'Catatan reviewer',
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `member_code` varchar(20) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(15) DEFAULT NULL,
  `join_date` date NOT NULL,
  `martial_art_type` enum('savate','kickboxing','boxing') NOT NULL,
  `class_type` enum('regular','private_6x','private_8x','private_10x') NOT NULL,
  `belt_level` varchar(50) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `user_id`, `member_code`, `birth_date`, `address`, `emergency_contact`, `join_date`, `martial_art_type`, `class_type`, `belt_level`, `medical_notes`) VALUES
(0, 0, 'MBR510792', '2025-10-18', 'iyah', '084343243243', '2025-10-18', 'kickboxing', 'regular', NULL, NULL),
(1, 0, 'MBR849932', '2006-05-05', 'Perumahan Rhabayu Garden', '-', '2025-10-13', 'kickboxing', 'regular', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `member_classes`
--

CREATE TABLE `member_classes` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `class_name` varchar(255) NOT NULL DEFAULT 'General Class',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('payment_reminder','schedule_change','event_announcement','general','stock_alert','stock_request','system') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `related_table` varchar(50) DEFAULT NULL COMMENT 'Tabel terkait',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID record terkait',
  `action_url` varchar(255) DEFAULT NULL COMMENT 'URL untuk action button'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `recipient_id`, `title`, `message`, `type`, `is_read`, `sent_at`, `related_table`, `related_id`, `action_url`) VALUES
(0, 1, 'Registrasi Baru', 'User baru testtttt (staff) telah mendaftar dan menunggu aktivasi.', 'system', 0, '2025-10-18 05:17:55', 'users', 0, 'http://localhost/ema/modules/users/'),
(1, 0, 'Pembayaran Diterima', 'Pembayaran Anda sebesar Rp 100.000 untuk Registration telah diterima. No. Receipt: RCP02187642', 'payment_reminder', 0, '2025-10-13 12:46:04', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` enum('monthly_fee','registration','equipment','tournament','other') NOT NULL,
  `payment_method` enum('cash','transfer','e_wallet','credit_card') NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','paid','overdue','cancelled') DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `member_id`, `class_id`, `amount`, `payment_type`, `payment_method`, `payment_date`, `notes`, `due_date`, `status`, `description`, `receipt_number`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 100000.00, 'registration', 'cash', '2025-10-13', NULL, '2025-10-13', 'paid', 'Pendaftaran', 'RCP02187642', 2, '2025-10-13 12:46:04', '2025-10-18 05:14:55');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `class_id`, `day_of_week`, `start_time`, `end_time`, `is_active`) VALUES
(1, 2, 'monday', '18:00:00', '19:30:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL COMMENT 'Key',
  `setting_value` text DEFAULT NULL COMMENT 'Value',
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string' COMMENT 'Tipe',
  `description` text DEFAULT NULL COMMENT 'Deskripsi',
  `is_editable` tinyint(1) DEFAULT 1 COMMENT 'Editable',
  `updated_by` int(11) DEFAULT NULL COMMENT 'Updated by',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_editable`, `updated_by`, `updated_at`) VALUES
(1, 'low_stock_notification', '1', 'boolean', 'Enable notifikasi stok menipis', 1, NULL, '2025-10-10 13:47:26'),
(2, 'low_stock_threshold', '10', 'number', 'Threshold stok minimum default', 1, NULL, '2025-10-10 13:47:26'),
(3, 'auto_approve_small_requests', '0', 'boolean', 'Auto-approve request kecil', 1, NULL, '2025-10-10 13:47:26'),
(4, 'realtime_refresh_interval', '30000', 'number', 'Interval refresh data (ms)', 1, NULL, '2025-10-10 13:47:26'),
(5, 'enable_public_registration', '1', 'boolean', 'Enable registrasi publik member', 1, NULL, '2025-10-10 13:47:26'),
(6, 'enable_staff_registration', '0', 'boolean', 'Enable registrasi publik staff', 1, NULL, '2025-10-10 13:47:26'),
(7, 'app_maintenance_mode', '0', 'boolean', 'Mode maintenance', 1, NULL, '2025-10-10 13:47:26'),
(8, 'backup_retention_days', '30', 'number', 'Lama penyimpanan backup', 1, NULL, '2025-10-10 13:47:26');

-- --------------------------------------------------------

--
-- Table structure for table `trainers`
--

CREATE TABLE `trainers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trainer_code` varchar(20) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `certification` text DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trainers`
--

INSERT INTO `trainers` (`id`, `user_id`, `trainer_code`, `specialization`, `experience_years`, `certification`, `hourly_rate`, `hire_date`) VALUES
(1, 5, 'TRN398283', 'wecewcwe', 2, 'ecewcew2', 23.00, '2025-10-10'),
(0, 0, 'TRN639541', 'kickboxing', 2, 'atlet', 0.00, '2025-10-18'),
(0, 0, 'TRN672497', 'kickboxing', 2, 'atlet', 0.00, '2025-10-18');

-- --------------------------------------------------------

--
-- Table structure for table `trainer_ratings`
--

CREATE TABLE `trainer_ratings` (
  `id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `rating` decimal(2,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `role` enum('super_admin','admin','staff','member') NOT NULL COMMENT 'super_admin=Pak Michael, admin=operational admin, staff=pelatih/staff, member=member',
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID yang membuat user ini',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Login terakhir',
  `can_manage_users` tinyint(1) DEFAULT 0 COMMENT 'Hak untuk manage user (only super_admin)',
  `can_manage_stock` tinyint(1) DEFAULT 1 COMMENT 'Hak untuk manage stok barang',
  `can_view_reports` tinyint(1) DEFAULT 1 COMMENT 'Hak untuk view laporan',
  `can_manage_finance` tinyint(1) DEFAULT 0 COMMENT 'Hak untuk manage keuangan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `profile_picture`, `created_at`, `updated_at`, `is_active`, `created_by`, `last_login`, `can_manage_users`, `can_manage_stock`, `can_view_reports`, `can_manage_finance`) VALUES
(1, 'superadmin', 'superadmin@emacamp.com', '$2y$10$ng2q6HkO9TkCbquHcY4vSu8IA5ZNPvvFBPrBGYH3ktBvwshApyVc2', 'Super Administrator', '081234567890', 'super_admin', NULL, '2025-10-10 13:47:25', '2025-10-18 05:30:38', 1, NULL, '2025-10-18 05:30:38', 1, 1, 1, 1),
(2, 'admin', 'admin@emacamp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', NULL, 'admin', NULL, '2025-10-10 13:47:25', '2025-10-18 05:28:34', 1, NULL, '2025-10-18 05:28:34', 0, 1, 1, 1),
(5, 'rizkan1982', 'ewcdxed@gmail.com', '$2y$10$PStTBW//LHp0d4Vwr03hiuNBjNosqL2TS2UBRgzzcUTvuwOx.NpSG', 'cwwedcwe', '089089080', '', NULL, '2025-10-10 13:57:54', '2025-10-10 17:19:20', 1, NULL, '2025-10-10 17:19:20', 0, 0, 0, 0),
(6, 'Adel', 'adelhutabarat75@gmail.com', '$2y$10$5pu325DFMNJo1zm/4IT1G.4yUMNSJqeQAo167e.5beNeFaaUIZefm', 'Adel Hutabarat', '083178463278', 'member', NULL, '2025-10-13 12:43:05', '2025-10-18 05:14:55', 1, NULL, NULL, 0, 1, 1, 0),
(0, 'test123', 'test@gmail.com', '$2y$10$4rmL66gA.1ScKzQ64rQcNuLe94prFDYnyAhakzGgxorJdZr4Xt4bi', 'testpelatih', '08123243314', 'staff', NULL, '2025-10-18 05:07:22', '2025-10-18 05:27:17', 1, NULL, NULL, 0, 0, 0, 0),
(0, 'test456', 'test@gmail.com', '$2y$10$gvnoYipK/98hBXlVl818OeQ2lbbmy/WquBurBSgUsnjbWFF15vHxS', 'testpelatih', '08123243314', 'staff', NULL, '2025-10-18 05:17:55', '2025-10-18 05:27:17', 1, NULL, NULL, 0, 0, 0, 0),
(0, 'iyah1982', 'iyah@gmail.com', '$2y$10$N96B7jcAgK3DYkUoVlzj8ub5msiue0NqXUanzk2DDrMHwr1BKVa7e', 'iyah', '083543542525', 'member', NULL, '2025-10-18 05:31:30', '2025-10-18 05:31:30', 1, NULL, NULL, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_logs`
--

CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'User yang melakukan',
  `action_type` varchar(100) NOT NULL COMMENT 'Jenis aksi',
  `table_name` varchar(100) DEFAULT NULL COMMENT 'Tabel',
  `record_id` int(11) DEFAULT NULL COMMENT 'ID record',
  `old_values` text DEFAULT NULL COMMENT 'Nilai lama',
  `new_values` text DEFAULT NULL COMMENT 'Nilai baru',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP Address',
  `user_agent` text DEFAULT NULL COMMENT 'Browser info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_logs`
--

INSERT INTO `user_activity_logs` (`id`, `user_id`, `action_type`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(0, 2, 'adjust_stock', 'inventory_items', 1, '{\"stock\":100}', '{\"stock\":250,\"type\":\"in\"}', '36.77.4.78', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-10 18:05:40'),
(0, 2, 'login', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:17:01'),
(0, 2, 'login', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:17:15'),
(0, 2, 'logout', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:24:07'),
(0, 2, 'logout', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:28:34'),
(0, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:30:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_member_date` (`member_id`,`created_at`),
  ADD KEY `idx_date_status` (`created_at`,`status`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trainer_id` (`trainer_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_performed_by` (`performed_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_stock_alert` (`current_stock`,`min_stock`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `inventory_requests`
--
ALTER TABLE `inventory_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_by` (`requested_by`),
  ADD KEY `idx_reviewed_by` (`reviewed_by`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `member_classes`
--
ALTER TABLE `member_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_class_id` (`class_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
