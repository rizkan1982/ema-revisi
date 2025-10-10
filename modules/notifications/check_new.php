<?php
require_once '../../config/config.php';
requireLogin();

$last_check = $_SESSION['last_notification_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
$_SESSION['last_notification_check'] = date('Y-m-d H:i:s');

$new_notifications = $db->fetch("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE recipient_id = ? AND sent_at > ? AND is_read = 0
", [$_SESSION['user_id'], $last_check])['count'];

$response = [
    'new_notifications' => $new_notifications,
    'play_sound' => $new_notifications > 0,
    'timestamp' => time()
];

header('Content-Type: application/json');
echo json_encode($response);
?>