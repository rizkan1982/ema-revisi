<?php
require_once '../../config/config.php';

// Update last login sebelum logout
if (isset($_SESSION['user_id'])) {
    $db->query(
        "UPDATE users SET last_login = NOW() WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

// Unset semua session variables
$_SESSION = array();

// Hapus session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroy session
session_destroy();

// Start new clean session
session_start();
session_regenerate_id(true);

// Redirect dengan cache clear headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: " . BASE_URL . "modules/auth/login.php?logout=success");
exit();
?>