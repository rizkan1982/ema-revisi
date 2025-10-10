<?php
require_once '../../config/config.php';
requireRole(['admin']);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $payment_id = intval($_GET['id'] ?? 0);
    
    if (!$payment_id) {
        throw new Exception('Payment ID tidak valid');
    }
    
    // Update payment status
    $updated = $db->query("
        UPDATE payments 
        SET status = 'paid', payment_date = CURRENT_DATE, updated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ", [$payment_id]);
    
    if ($updated > 0) {
        $response['success'] = true;
        $response['message'] = 'Pembayaran berhasil ditandai sebagai lunas';
    } else {
        throw new Exception('Gagal update status pembayaran atau pembayaran sudah lunas');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>