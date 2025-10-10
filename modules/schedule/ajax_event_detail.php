<?php
require_once '../../config/config.php';
requireLogin();

$event_id = intval($_GET['id']);

$event = $db->fetch("
    SELECT e.*, COUNT(er.member_id) as registered_count
    FROM events e
    LEFT JOIN event_registrations er ON e.id = er.event_id
    WHERE e.id = ?
    GROUP BY e.id
", [$event_id]);

if (!$event) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found']);
    exit;
}

// Check if current user can register (for members)
$can_register = false;
if (getUserRole() === 'member') {
    $existing_registration = $db->fetch("
        SELECT er.id FROM event_registrations er 
        JOIN members m ON er.member_id = m.id 
        WHERE m.user_id = ? AND er.event_id = ?
    ", [$_SESSION['user_id'], $event_id]);
    
    $registration_open = !$event['registration_deadline'] || $event['registration_deadline'] >= date('Y-m-d');
    $can_register = !$existing_registration && $registration_open;
}

$event['can_register'] = $can_register;

header('Content-Type: application/json');
echo json_encode($event);
?>