<?php
// ======================================
// Konfigurasi Aplikasi
// ======================================
define('APP_NAME', 'EMA Camp Management System');
define('APP_VERSION', '1.0.0');

// Sesuaikan URL utama project Anda (pakai https kalau domain sudah SSL)
define('BASE_URL', 'http://localhost/emacamp-baru/');

// Path untuk upload file
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');

// ======================================
// Timezone
// ======================================
date_default_timezone_set('Asia/Jakarta');

// ======================================
// Session
// ======================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ======================================
// Database
// ======================================
require_once __DIR__ . '/database.php';

// Buat instance database
$db = new Database();

// ======================================
// Helper Functions
// ======================================

/**
 * Redirect ke halaman tertentu
 */
function redirect($url) {
    header("Location: " . BASE_URL . ltrim($url, '/'));
    exit();
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Ambil role user
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Wajib login sebelum akses halaman
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('modules/auth/login.php');
    }
}

/**
 * Wajib punya role tertentu untuk akses halaman
 */
function requireRole($roles) {
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array(getUserRole(), $roles)) {
        redirect('modules/dashboard/index.php?error=unauthorized');
    }
}

/**
 * Format angka ke Rupiah
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format tanggal (d/m/Y)
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Format tanggal & jam (d/m/Y H:i)
 */
function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Generate kode unik
 */
function generateCode($prefix, $length = 6) {
    return $prefix . str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}
