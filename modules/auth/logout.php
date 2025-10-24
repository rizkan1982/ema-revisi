<?php
require_once '../../config/config.php';

// Log activity logout sebelum menghapus session
if (isset($_SESSION['user_id'])) {
    logActivity('logout', 'users', $_SESSION['user_id']);
    
    // Update last login time (last logout)
    $db->query(
        "UPDATE users SET last_login = NOW() WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

// STEP 1: Clear all session variables
$_SESSION = array();

// STEP 2: Delete session cookie from browser
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(), 
        '', 
        [
            'expires' => time() - 42000,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Strict'
        ]
    );
}

// STEP 3: Destroy session completely from server
session_destroy();

// STEP 4: Start fresh new session untuk keamanan
session_start();
session_regenerate_id(true);

// STEP 5: Force browser to completely clear cache and storage
header("Clear-Site-Data: \"cache\", \"cookies\", \"storage\", \"executionContexts\"");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// STEP 6: Redirect to login page
header("Location: " . BASE_URL . "modules/auth/login.php?logout=success");
exit();
?>