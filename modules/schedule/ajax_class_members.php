<?php
require_once '../../config/config.php';
requireRole(['admin', 'trainer']);

header('Content-Type: application/json');

$class_id = intval($_GET['class_id']);

try {
    // Get members enrolled in this class or all active members if no specific class
    if ($class_id && $class_id > 0) {
        $members = $db->fetchAll("
            SELECT DISTINCT m.id, m.member_code, u.full_name, u.email
            FROM members m 
            JOIN users u ON m.user_id = u.id 
            LEFT JOIN member_classes mc ON m.id = mc.member_id 
            WHERE u.is_active = 1 AND (mc.class_id = ? OR mc.class_id IS NULL)
            ORDER BY u.full_name ASC
        ", [$class_id]);
    } else {
        // Get all active members
        $members = $db->fetchAll("
            SELECT m.id, m.member_code, u.full_name, u.email
            FROM members m 
            JOIN users u ON m.user_id = u.id 
            WHERE u.is_active = 1
            ORDER BY u.full_name ASC
        ");
    }
    
    echo json_encode([
        'success' => true,
        'members' => $members,
        'count' => count($members)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'members' => []
    ]);
}
?>