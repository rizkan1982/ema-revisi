<?php
require_once '../../config/config.php';
requireLogin();

// Check permission - Super Admin only!
if (getUserRole() !== 'super_admin') {
    header("Location: ../dashboard/index.php?error=unauthorized");
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$user_id || !in_array($action, ['activate', 'deactivate'])) {
    header("Location: index.php?error=invalid_params");
    exit;
}

// Prevent deactivating own account
if ($user_id == $_SESSION['user_id']) {
    header("Location: index.php?error=cannot_deactivate_self");
    exit;
}

// Get user
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);

if (!$user) {
    header("Location: index.php?error=user_not_found");
    exit;
}

try {
    if ($action === 'activate') {
        $db->query("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ?", [$user_id]);
        
        // Log activity
        logActivity('activate_user', 'users', $user_id, ['was_active' => 0], ['is_active' => 1]);
        
        // Notify user
        sendNotification(
            $user_id,
            'Akun Diaktifkan',
            'Akun Anda telah diaktifkan kembali oleh administrator. Anda sekarang dapat login.',
            'system'
        );
        
        header("Location: index.php?success=user_activated");
        
    } else {
        $db->query("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?", [$user_id]);
        
        // Log activity
        logActivity('deactivate_user', 'users', $user_id, ['was_active' => 1], ['is_active' => 0]);
        
        // Notify user
        sendNotification(
            $user_id,
            'Akun Dinonaktifkan',
            'Akun Anda telah dinonaktifkan oleh administrator. Hubungi admin untuk informasi lebih lanjut.',
            'system'
        );
        
        header("Location: index.php?success=user_deactivated");
    }
    
} catch (Exception $e) {
    header("Location: index.php?error=" . urlencode($e->getMessage()));
}

exit;
?>
