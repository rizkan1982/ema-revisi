<?php
require_once '../../config/config.php';
requireRole(['admin', 'trainer']);

header('Content-Type: application/json');

$trainer_id = intval($_GET['trainer_id'] ?? 0);

try {
    if (!$trainer_id) {
        throw new Exception('Trainer ID tidak valid');
    }
    
    // Get trainer info
    $trainer = $db->fetch("
        SELECT t.*, u.full_name
        FROM trainers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ", [$trainer_id]);
    
    if (!$trainer) {
        throw new Exception('Pelatih tidak ditemukan');
    }
    
    // Get trainer schedules
    $schedules = $db->fetchAll("
        SELECT s.*, c.class_name, c.martial_art_type, c.max_participants,
               COUNT(mc.member_id) as enrolled_count
        FROM schedules s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
        WHERE c.trainer_id = ? AND s.is_active = 1
        GROUP BY s.id
        ORDER BY 
            CASE s.day_of_week
                WHEN 'monday' THEN 1
                WHEN 'tuesday' THEN 2
                WHEN 'wednesday' THEN 3
                WHEN 'thursday' THEN 4
                WHEN 'friday' THEN 5
                WHEN 'saturday' THEN 6
                WHEN 'sunday' THEN 7
            END,
            s.start_time
    ", [$trainer_id]);
    
    echo json_encode([
        'success' => true,
        'trainer_name' => $trainer['full_name'],
        'trainer_code' => $trainer['trainer_code'],
        'schedules' => $schedules,
        'total_schedules' => count($schedules)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trainer_name' => 'Unknown',
        'trainer_code' => 'Unknown',
        'schedules' => []
    ]);
}
?>