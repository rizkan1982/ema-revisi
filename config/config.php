<?php
// ======================================
// Konfigurasi Aplikasi
// ======================================
define('APP_NAME', 'EMA Camp Management System');
define('APP_VERSION', '1.0.0');

// Sesuaikan URL utama project Anda (pakai https kalau domain sudah SSL)
define('BASE_URL', 'http://localhost/ema/');

// Path untuk upload file
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');

// ======================================
// Timezone
// ======================================
date_default_timezone_set('Asia/Jakarta');

// ======================================
// ANTI-CACHE HEADERS - STRONGEST - Harus SEBELUM output apapun!
// ======================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("X-Frame-Options: SAMEORIGIN"); // Security
header("X-Content-Type-Options: nosniff"); // Security

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
 * Cek apakah user adalah Super Admin
 */
function isSuperAdmin() {
    return getUserRole() === 'super_admin';
}

/**
 * Cek apakah user adalah Admin (termasuk Super Admin)
 */
function isAdmin() {
    return in_array(getUserRole(), ['super_admin', 'admin']);
}

/**
 * Cek apakah user adalah Staff/Pelatih
 */
function isStaff() {
    return getUserRole() === 'staff';
}

/**
 * Cek apakah user adalah Member
 */
function isMember() {
    return getUserRole() === 'member';
}

/**
 * Cek permission spesifik user
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $db;
    $user = $db->fetch(
        "SELECT * FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
    
    $permissionMap = [
        'manage_users' => $user['can_manage_users'] ?? 0,
        'manage_stock' => $user['can_manage_stock'] ?? 0,
        'view_reports' => $user['can_view_reports'] ?? 0,
        'manage_finance' => $user['can_manage_finance'] ?? 0,
    ];
    
    return isset($permissionMap[$permission]) && $permissionMap[$permission] == 1;
}

/**
 * Log aktivitas user (untuk audit trail)
 */
function logActivity($action_type, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    if (!isLoggedIn()) {
        return;
    }
    
    global $db;
    
    $db->query(
        "INSERT INTO user_activity_logs 
        (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $_SESSION['user_id'],
            $action_type,
            $table_name,
            $record_id,
            is_array($old_values) ? json_encode($old_values) : $old_values,
            is_array($new_values) ? json_encode($new_values) : $new_values,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );
}

/**
 * Kirim notifikasi ke user
 */
function sendNotification($recipient_id, $title, $message, $type = 'general', $related_table = null, $related_id = null, $action_url = null) {
    global $db;
    
    $db->query(
        "INSERT INTO notifications 
        (recipient_id, title, message, type, related_table, related_id, action_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$recipient_id, $title, $message, $type, $related_table, $related_id, $action_url]
    );
}

/**
 * Get system setting value
 */
function getSetting($key, $default = null) {
    global $db;
    
    $setting = $db->fetch(
        "SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?",
        [$key]
    );
    
    if (!$setting) {
        return $default;
    }
    
    // Convert berdasarkan tipe
    switch ($setting['setting_type']) {
        case 'boolean':
            return (bool)$setting['setting_value'];
        case 'number':
            return (int)$setting['setting_value'];
        case 'json':
            return json_decode($setting['setting_value'], true);
        default:
            return $setting['setting_value'];
    }
}

/**
 * Set system setting value
 */
function setSetting($key, $value) {
    global $db;
    
    $db->query(
        "UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?",
        [$value, $_SESSION['user_id'] ?? null, $key]
    );
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
