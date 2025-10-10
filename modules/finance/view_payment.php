<?php
$page_title = "Detail Pembayaran";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

$payment_id = intval($_GET['id']);

$payment = $db->fetch("
    SELECT p.*, m.member_code, u.full_name, u.email, u.phone,
           creator.full_name as created_by_name
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    LEFT JOIN users creator ON p.created_by = creator.id
    WHERE p.id = ?
", [$payment_id]);

if (!$payment) {
    redirect('modules/finance/?error=not_found');
}

// Get member's other payments
$other_payments = $db->fetchAll("
    SELECT * FROM payments 
    WHERE member_id = ? AND id != ?
    ORDER BY payment_date DESC
    LIMIT 5
", [$payment['member_id'], $payment_id]);
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Detail Pembayaran</h3>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <a href="edit_payment.php?id=<?= $payment_id ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i>
                Edit
            </a>
            <a href="print_receipt.php?id=<?= $payment_id ?>" class="btn btn-success" target="_blank">
                <i class="fas fa-print"></i>
                Print Receipt
            </a>
        </div>
    </div>
</div>

<!-- Payment Info Header -->
<div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0;"><?= formatRupiah($payment['amount']) ?></h2>
            <div style="margin-top: 10px; opacity: 0.9;">
                <strong>Receipt #:</strong> <?= $payment['receipt_number'] ?: 'N/A' ?><br>
                <strong>Tanggal:</strong> <?= formatDate($payment['payment_date']) ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 2rem; margin-bottom: 10px;">
                <?php
                $status_icon = '';
                switch ($payment['status']) {
                    case 'paid': $status_icon = 'fas fa-check-circle'; break;
                    case 'pending': $status_icon = 'fas fa-clock'; break;
                    case 'overdue': $status_icon = 'fas fa-exclamation-triangle'; break;
                    case 'cancelled': $status_icon = 'fas fa-times-circle'; break;
                }
                ?>
                <i class="<?= $status_icon ?>"></i>
            </div>
            <div style="font-size: 1.2rem; font-weight: bold;">
                <?= strtoupper($payment['status']) ?>
            </div>
        </div>
    </div>
</div>

<!-- Details Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Payment Details -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Informasi Pembayaran</h4>
        </div>
        
        <div style="padding: 25px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">MEMBER</label>
                        <div style="font-size: 1.3rem; font-weight: bold; color: #1E459F;">
                            <?= htmlspecialchars($payment['full_name']) ?>
                        </div>
                        <div style="color: #6c757d; margin-top: 5px;">
                            <?= $payment['member_code'] ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">TIPE PEMBAYARAN</label>
                        <div style="font-size: 1.1rem; color: #495057;">
                            <?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">METODE PEMBAYARAN</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas <?= $payment['payment_method'] === 'cash' ? 'fa-money-bill' : ($payment['payment_method'] === 'transfer' ? 'fa-university' : 'fa-credit-card') ?>" style="color: #CF2A2A;"></i>
                            <span style="font-size: 1.1rem;"><?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?></span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">JUMLAH</label>
                        <div style="font-size: 2rem; font-weight: bold; color: #28a745;">
                            <?= formatRupiah($payment['amount']) ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">TANGGAL PEMBAYARAN</label>
                        <div style="font-size: 1.1rem; color: #495057;">
                            <?= formatDate($payment['payment_date']) ?>
                        </div>
                    </div>
                    
                    <?php if ($payment['due_date']): ?>
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">JATUH TEMPO</label>
                                               <div style="font-size: 1.1rem; color: <?= $payment['due_date'] < date('Y-m-d') && $payment['status'] === 'pending' ? '#dc3545' : '#495057' ?>;">
                            <?= formatDate($payment['due_date']) ?>
                            <?php if ($payment['due_date'] < date('Y-m-d') && $payment['status'] === 'pending'): ?>
                                <span class="badge badge-danger" style="margin-left: 10px;">OVERDUE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($payment['description']): ?>
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 8px;">KETERANGAN</label>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #1E459F;">
                    <?= nl2br(htmlspecialchars($payment['description'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Member Info & Actions -->
    <div>
        <!-- Member Info Card -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h5 class="card-title">Informasi Member</h5>
            </div>
            
            <div style="padding: 20px; text-align: center;">
                <div class="user-avatar" style="width: 80px; height: 80px; margin: 0 auto 15px; font-size: 2rem; background: linear-gradient(135deg, #1E459F, #CF2A2A);">
                    <?= strtoupper(substr($payment['full_name'], 0, 1)) ?>
                </div>
                
                <h5 style="color: #1E459F; margin-bottom: 10px;">
                    <?= htmlspecialchars($payment['full_name']) ?>
                </h5>
                
                <div style="color: #6c757d; margin-bottom: 15px;">
                    <?= $payment['member_code'] ?>
                </div>
                
                <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; font-size: 0.9rem;">
                    <div style="margin-bottom: 5px;">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($payment['email']) ?>
                    </div>
                    <div>
                        <i class="fas fa-phone"></i>
                        <?= htmlspecialchars($payment['phone'] ?: 'Tidak diisi') ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Informasi Sistem</h5>
            </div>
            
            <div style="padding: 15px; font-size: 0.9rem;">
                <div style="margin-bottom: 10px;">
                    <strong>Created by:</strong><br>
                    <?= htmlspecialchars($payment['created_by_name'] ?: 'System') ?>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <strong>Created at:</strong><br>
                    <?= formatDateTime($payment['created_at'] ?? $payment['payment_date']) ?>
                </div>
                
                <?php if ($payment['updated_at'] && $payment['updated_at'] !== $payment['created_at']): ?>
                <div>
                    <strong>Last updated:</strong><br>
                    <?= formatDateTime($payment['updated_at']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Other Payments -->
<?php if (!empty($other_payments)): ?>
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Pembayaran Lain dari Member Ini</h4>
        <a href="../members/view.php?id=<?= $payment['member_id'] ?>" class="btn btn-primary btn-sm">
            Lihat Semua Pembayaran
        </a>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($other_payments as $other): ?>
                <tr>
                    <td><?= formatDate($other['payment_date']) ?></td>
                    <td><?= ucwords(str_replace('_', ' ', $other['payment_type'])) ?></td>
                    <td><strong><?= formatRupiah($other['amount']) ?></strong></td>
                    <td>
                        <?php
                        $badge_class = '';
                        switch ($other['status']) {
                            case 'paid': $badge_class = 'badge-success'; break;
                            case 'pending': $badge_class = 'badge-warning'; break;
                            case 'overdue': $badge_class = 'badge-danger'; break;
                            case 'cancelled': $badge_class = 'badge-secondary'; break;
                        }
                        ?>
                        <span class="badge <?= $badge_class ?>">
                            <?= ucfirst($other['status']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="view_payment.php?id=<?= $other['id'] ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>