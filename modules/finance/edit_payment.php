<?php
$page_title = "Edit Pembayaran";
require_once '../../includes/header.php';
requireRole(['admin']);

$payment_id = intval($_GET['id']);
$success = '';
$error = '';

$payment = $db->fetch("
    SELECT p.*, m.member_code, u.full_name
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.id = ?
", [$payment_id]);

if (!$payment) {
    redirect('modules/finance/?error=not_found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $amount = floatval($_POST['amount']);
        $payment_type = $_POST['payment_type'];
        $payment_method = $_POST['payment_method'];
        $payment_date = $_POST['payment_date'];
        $due_date = $_POST['due_date'] ?: null;
        $status = $_POST['status'];
        $description = trim($_POST['description']);
        
        $db->query("
            UPDATE payments 
            SET amount = ?, payment_type = ?, payment_method = ?, payment_date = ?, 
                due_date = ?, status = ?, description = ?
            WHERE id = ?
        ", [$amount, $payment_type, $payment_method, $payment_date, 
            $due_date, $status, $description, $payment_id]);
        
        $success = "Data pembayaran berhasil diperbarui!";
        
        // Refresh data
        $payment = $db->fetch("
            SELECT p.*, m.member_code, u.full_name
            FROM payments p
            JOIN members m ON p.member_id = m.id
            JOIN users u ON m.user_id = u.id
            WHERE p.id = ?
        ", [$payment_id]);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Edit Pembayaran</h3>
        <div style="display: flex; gap: 10px;">
            <a href="view_payment.php?id=<?= $payment_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <a href="view_payment.php?id=<?= $payment_id ?>" class="btn btn-info">
                <i class="fas fa-eye"></i>
                Lihat Detail
            </a>
        </div>
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
    
    <form method="POST" action="">
        <div style="padding: 25px;">
            <!-- Current Payment Info -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #1E459F;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="color: #1E459F; margin: 0;">
                            <?= htmlspecialchars($payment['full_name']) ?>
                        </h4>
                        <div style="color: #6c757d; margin-top: 5px;">
                            <strong>Member:</strong> <?= $payment['member_code'] ?> | 
                            <strong>Receipt:</strong> <?= $payment['receipt_number'] ?: 'N/A' ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: #28a745;">
                            <?= formatRupiah($payment['amount']) ?>
                        </div>
                        <div style="color: #6c757d; font-size: 0.9rem;">
                            Current Amount
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Payment Details -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-money-bill-wave"></i>
                        Detail Pembayaran
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Pembayaran *</label>
                        <select name="payment_type" class="form-control form-select" required>
                            <option value="monthly_fee" <?= $payment['payment_type'] === 'monthly_fee' ? 'selected' : '' ?>>Iuran Bulanan</option>
                            <option value="registration" <?= $payment['payment_type'] === 'registration' ? 'selected' : '' ?>>Pendaftaran</option>
                            <option value="equipment" <?= $payment['payment_type'] === 'equipment' ? 'selected' : '' ?>>Peralatan</option>
                            <option value="tournament" <?= $payment['payment_type'] === 'tournament' ? 'selected' : '' ?>>Turnamen</option>
                            <option value="other" <?= $payment['payment_type'] === 'other' ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Jumlah (Rp) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0" value="<?= $payment['amount'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Metode Pembayaran *</label>
                        <select name="payment_method" class="form-control form-select" required>
                            <option value="cash" <?= $payment['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="transfer" <?= $payment['payment_method'] === 'transfer' ? 'selected' : '' ?>>Transfer Bank</option>
                            <option value="e_wallet" <?= $payment['payment_method'] === 'e_wallet' ? 'selected' : '' ?>>E-Wallet</option>
                            <option value="credit_card" <?= $payment['payment_method'] === 'credit_card' ? 'selected' : '' ?>>Kartu Kredit</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-control form-select" required>
                            <option value="paid" <?= $payment['status'] === 'paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                            <option value="pending" <?= $payment['status'] === 'pending' ? 'selected' : '' ?>>Pending (Belum Bayar)</option>
                            <option value="overdue" <?= $payment['status'] === 'overdue' ? 'selected' : '' ?>>Overdue (Terlambat)</option>
                            <option value="cancelled" <?= $payment['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled (Dibatalkan)</option>
                        </select>
                    </div>
                </div>
                
                <!-- Dates & Description -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-calendar-alt"></i>
                        Tanggal & Keterangan
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Pembayaran *</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= $payment['payment_date'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control" value="<?= $payment['due_date'] ?>">
                        <small class="text-muted">Kosongkan jika pembayaran langsung</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deskripsi/Keterangan</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Keterangan tambahan..."><?= htmlspecialchars($payment['description']) ?></textarea>
                    </div>
                    
                    <!-- Read-only info -->
                    <div style="background: #e9ecef; padding: 15px; border-radius: 8px; margin-top: 20px;">
                        <h6 style="color: #495057; margin-bottom: 10px;">Informasi Sistem</h6>
                        <div style="font-size: 0.9rem; color: #6c757d;">
                            <div><strong>Receipt Number:</strong> <?= $payment['receipt_number'] ?: 'N/A' ?></div>
                            <div><strong>Created:</strong> <?= formatDateTime($payment['created_at']) ?></div>
                            <?php if ($payment['updated_at'] !== $payment['created_at']): ?>
                            <div><strong>Updated:</strong> <?= formatDateTime($payment['updated_at']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Update Pembayaran
                </button>
                
                <a href="view_payment.php?id=<?= $payment_id ?>" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                
                <a href="index.php" class="btn btn-info btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-list"></i>
                    Daftar Pembayaran
                </a>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>