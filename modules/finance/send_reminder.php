<?php
require_once '../../config/config.php';
requireRole(['admin']);

$payment_id = intval($_GET['id']);

try {
    $payment = $db->fetch("
        SELECT p.*, u.id as user_id, u.full_name, m.member_code
        FROM payments p
        JOIN members m ON p.member_id = m.id
        JOIN users u ON m.user_id = u.id
        WHERE p.id = ?
    ", [$payment_id]);
    
    if (!$payment) {
        throw new Exception('Pembayaran tidak ditemukan');
    }
    
    $title = "Reminder Pembayaran - " . $payment['member_code'];
    $message = "Pembayaran Anda sebesar " . formatRupiah($payment['amount']) . 
               " untuk " . ucwords(str_replace('_', ' ', $payment['payment_type'])) .
               " dengan jatuh tempo " . formatDate($payment['due_date']) . 
               " belum kami terima. Mohon segera lakukan pembayaran. Terima kasih!";
    
    $db->query("
        INSERT INTO notifications (recipient_id, title, message, type) 
        VALUES (?, ?, ?, 'payment_reminder')
    ", [$payment['user_id'], $title, $message]);
    
    echo json_encode(['success' => true, 'message' => 'Reminder berhasil dikirim']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>