<?php
require_once '../../includes/header.php';
requireRole(['admin']);

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_id'])) {
    try {
        $schedule_id = intval($_POST['schedule_id']);
        
        // Get schedule info for log
        $schedule = $db->fetch("
            SELECT s.*, c.class_name 
            FROM schedules s 
            JOIN classes c ON s.class_id = c.id 
            WHERE s.id = ?
        ", [$schedule_id]);
        
        if (!$schedule) {
            throw new Exception('Jadwal tidak ditemukan!');
        }
        
        $db->getConnection()->beginTransaction();
        
        // Delete related attendance records
        $db->query("DELETE FROM attendances WHERE class_id = ?", [$schedule['class_id']]);
        
        // Delete the schedule
        $db->query("DELETE FROM schedules WHERE id = ?", [$schedule_id]);
        
        // Add notification log
        $db->query("
            INSERT INTO notifications (recipient_id, title, message, type) 
            VALUES (?, ?, ?, 'system')
        ", [
            $_SESSION['user_id'],
            'Jadwal Dihapus',
            "Jadwal {$schedule['class_name']} berhasil dihapus pada " . date('d/m/Y H:i:s')
        ]);
        
        $db->getConnection()->commit();
        
        $response['success'] = true;
        $response['message'] = 'Jadwal berhasil dihapus!';
        
        // Redirect to schedule list
        redirect('modules/schedule/?success=schedule_deleted');
        
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $response['message'] = $e->getMessage();
        redirect('modules/schedule/?error=' . urlencode($e->getMessage()));
    }
} else {
    redirect('modules/schedule/?error=invalid_request');
}
?>