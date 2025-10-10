<?php
require_once '../../config/config.php';
requireLogin();

$schedule_id = intval($_GET['id']);

$schedule = $db->fetch("
    SELECT s.*, c.class_name, c.martial_art_type, c.class_type, c.max_participants, c.id as class_id,
           u.full_name as trainer_name,
           COUNT(mc.member_id) as enrolled_count
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    WHERE s.id = ?
    GROUP BY s.id
", [$schedule_id]);

if (!$schedule) {
    http_response_code(404);
    echo json_encode(['error' => 'Schedule not found']);
    exit;
}

// Check if current user can enroll (for members)
$can_enroll = false;
if (getUserRole() === 'member') {
    $existing_enrollment = $db->fetch("
        SELECT mc.id FROM member_classes mc 
        JOIN members m ON mc.member_id = m.id 
        WHERE m.user_id = ? AND mc.class_id = ? AND mc.status = 'active'
    ", [$_SESSION['user_id'], $schedule['class_id']]);
    
    $can_enroll = !$existing_enrollment && $schedule['enrolled_count'] < $schedule['max_participants'];
}

$schedule['can_enroll'] = $can_enroll;

header('Content-Type: application/json');
echo json_encode($schedule);
?>