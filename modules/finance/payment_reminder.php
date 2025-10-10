<?php
$page_title = "Kirim Reminder Pembayaran";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

// Get overdue and upcoming payments
$overdue_payments = $db->fetchAll("
    SELECT p.*, m.member_code, u.full_name, u.email, u.phone,
           DATEDIFF(CURRENT_DATE, p.due_date) as days_overdue
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.status = 'pending' AND p.due_date < CURRENT_DATE
    ORDER BY p.due_date ASC
");

$upcoming_payments = $db->fetchAll("
    SELECT p.*, m.member_code, u.full_name, u.email, u.phone,
           DATEDIFF(p.due_date, CURRENT_DATE) as days_until_due
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.status = 'pending' 
    AND p.due_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
    ORDER BY p.due_date ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $selected_payments = $_POST['payments'] ?? [];
        $reminder_type = $_POST['reminder_type'];
        $custom_message = trim($_POST['custom_message']);
        
        if (empty($selected_payments)) {
            throw new Exception('Pilih minimal satu pembayaran!');
        }
        
        $sent_count = 0;
        
        foreach ($selected_payments as $payment_id) {
            $payment = $db->fetch("
                SELECT p.*, m.member_code, u.full_name, u.email
                FROM payments p
                JOIN members m ON p.member_id = m.id
                JOIN users u ON m.user_id = u.id
                WHERE p.id = ?
            ", [$payment_id]);
            
            if ($payment) {
                // Create notification
                $title = $reminder_type === 'overdue' ? 
                    'Pembayaran Terlambat - ' . $payment['member_code'] :
                    'Reminder Pembayaran - ' . $payment['member_code'];
                
                $message = $custom_message ?: 
                    "Pembayaran Anda sebesar " . formatRupiah($payment['amount']) . 
                    " untuk " . ucwords(str_replace('_', ' ', $payment['payment_type'])) .
                    " dengan jatuh tempo " . formatDate($payment['due_date']) . 
                    " belum kami terima. Mohon segera lakukan pembayaran.";
                
                $db->query("
                    INSERT INTO notifications (recipient_id, title, message, type) 
                    VALUES (?, ?, ?, 'payment_reminder')
                ", [$payment['user_id'] ?? 1, $title, $message]);
                
                $sent_count++;
            }
        }
        
        $success = "Berhasil mengirim $sent_count reminder pembayaran!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Kirim Reminder Pembayaran</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $success ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px; margin-bottom: 30px;">
        <div class="stat-card red">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= count($overdue_payments) ?></h3>
                    <p>Pembayaran Overdue</p>
                </div>
                <div class="stat-icon red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card yellow">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= count($upcoming_payments) ?></h3>
                    <p>Jatuh Tempo 7 Hari</p>
                </div>
                <div class="stat-icon yellow">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <form method="POST" action="">
        <div style="padding: 25px; padding-top: 0;">
            <!-- Reminder Settings -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <h4 style="color: #1E459F; margin-bottom: 15px;">
                    <i class="fas fa-cog"></i>
                    Pengaturan Reminder
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Tipe Reminder</label>
                        <select name="reminder_type" class="form-control form-select" required>
                            <option value="upcoming">Upcoming (Akan Jatuh Tempo)</option>
                            <option value="overdue">Overdue (Sudah Terlambat)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Metode Pengiriman</label>
                        <select name="delivery_method" class="form-control form-select">
                            <option value="notification">Notifikasi Sistem</option>
                            <option value="email" disabled>Email (Coming Soon)</option>
                            <option value="whatsapp" disabled>WhatsApp (Coming Soon)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Pesan Kustom (Opsional)</label>
                    <textarea name="custom_message" class="form-control" rows="3" placeholder="Kosongkan untuk menggunakan template default..."></textarea>
                </div>
            </div>
            
            <!-- Overdue Payments -->
            <?php if (!empty($overdue_payments)): ?>
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header" style="background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
                    <h4 class="card-title" style="color: #dc3545;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Pembayaran Overdue (<?= count($overdue_payments) ?>)
                    </h4>
                    <div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="selectAllOverdue()">
                            <i class="fas fa-check-double"></i>
                            Pilih Semua
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="50px">
                                    <input type="checkbox" id="check-all-overdue" onchange="toggleAllOverdue()">
                                </th>
                                <th>Member</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Jatuh Tempo</th>
                                <th>Terlambat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdue_payments as $payment): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="payments[]" value="<?= $payment['id'] ?>" class="overdue-checkbox">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($payment['full_name']) ?></strong><br>
                                    <small class="text-muted"><?= $payment['member_code'] ?></small>
                                </td>
                                <td><?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?></td>
                                <td><strong><?= formatRupiah($payment['amount']) ?></strong></td>
                                <td><?= formatDate($payment['due_date']) ?></td>
                                <td>
                                    <span class="badge badge-danger">
                                        <?= $payment['days_overdue'] ?> hari
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Payments -->
            <?php if (!empty($upcoming_payments)): ?>
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header" style="background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107;">
                    <h4 class="card-title" style="color: #ffc107;">
                        <i class="fas fa-clock"></i>
                        Jatuh Tempo 7 Hari Kedepan (<?= count($upcoming_payments) ?>)
                    </h4>
                    <div>
                        <button type="button" class="btn btn-sm btn-warning" onclick="selectAllUpcoming()">
                            <i class="fas fa-check-double"></i>
                            Pilih Semua
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="50px">
                                    <input type="checkbox" id="check-all-upcoming" onchange="toggleAllUpcoming()">
                                </th>
                                <th>Member</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Jatuh Tempo</th>
                                <th>Sisa Hari</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_payments as $payment): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="payments[]" value="<?= $payment['id'] ?>" class="upcoming-checkbox">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($payment['full_name']) ?></strong><br>
                                    <small class="text-muted"><?= $payment['member_code'] ?></small>
                                </td>
                                <td><?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?></td>
                                <td><strong><?= formatRupiah($payment['amount']) ?></strong></td>
                                <td><?= formatDate($payment['due_date']) ?></td>
                                <td>
                                    <span class="badge badge-warning">
                                        <?= $payment['days_until_due'] ?> hari
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($overdue_payments) && empty($upcoming_payments)): ?>
            <div style="padding: 60px; text-align: center; color: #28a745;">
                <i class="fas fa-check-circle" style="font-size: 4rem; margin-bottom: 20px;"></i>
                <h4>Semua Pembayaran Lancar!</h4>
                <p>Tidak ada pembayaran yang overdue atau akan jatuh tempo dalam 7 hari kedepan.</p>
            </div>
            <?php else: ?>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center;">
                <button type="submit" class="btn btn-primary btn-lg" id="send-button" disabled>
                    <i class="fas fa-paper-plane"></i>
                    Kirim Reminder
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
function selectAllOverdue() {
    document.querySelectorAll('.overdue-checkbox').forEach(cb => cb.checked = true);
    updateSendButton();
}

function selectAllUpcoming() {
    document.querySelectorAll('.upcoming-checkbox').forEach(cb => cb.checked = true);
    updateSendButton();
}

function toggleAllOverdue() {
    const mainCheck = document.getElementById('check-all-overdue');
    document.querySelectorAll('.overdue-checkbox').forEach(cb => cb.checked = mainCheck.checked);
    updateSendButton();
}

function toggleAllUpcoming() {
    const mainCheck = document.getElementById('check-all-upcoming');
    document.querySelectorAll('.upcoming-checkbox').forEach(cb => cb.checked = mainCheck.checked);
    updateSendButton();
}

function updateSendButton() {
    const selectedCount = document.querySelectorAll('input[name="payments[]"]:checked').length;
    document.getElementById('send-button').disabled = selectedCount === 0;
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="payments[]"]').forEach(cb => {
        cb.addEventListener('change', updateSendButton);
    });
    
    updateSendButton();
});
</script>

<?php require_once '../../includes/footer.php'; ?>