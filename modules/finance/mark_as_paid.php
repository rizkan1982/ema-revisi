<?php
require_once '../../config/config.php';
requireRole(['admin']);

$payment_id = intval($_GET['id']);

try {
    $db->query("UPDATE payments SET status = 'paid', payment_date = CURRENT_DATE WHERE id = ?", [$payment_id]);
    
    // Get payment info for notification
    $payment = $db->fetch("
        SELECT p.*, u.id as user_id, u.full_name, m.member_code
        FROM payments p
        JOIN members m ON p.member_id = m.id
        JOIN users u ON m.user_id = u.id
        WHERE p.id = ?
    ", [$payment_id]);
    
    // Send notification to member
    if ($payment) {
        $notification_message = "Pembayaran Anda sebesar " . formatRupiah($payment['amount']) . " telah dikonfirmasi sebagai LUNAS. Terima kasih!";
        
        $db->query("
            INSERT INTO notifications (recipient_id, title, message, type) 
            VALUES (?, ?, ?, 'payment_reminder')
        ", [$payment['user_id'], 'Pembayaran Dikonfirmasi', $notification_message]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil ditandai sebagai lunas']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>