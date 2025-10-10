<?php
$page_title = "Buat Invoice Massal";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

// Get all active members
$members = $db->fetchAll("
    SELECT m.id, m.member_code, u.full_name, u.email,
           (SELECT amount FROM payments WHERE member_id = m.id AND payment_type = 'monthly_fee' ORDER BY created_at DESC LIMIT 1) as last_fee
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE u.is_active = 1
    ORDER BY u.full_name ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $selected_members = $_POST['members'] ?? [];
        $payment_type = $_POST['payment_type'];
        $amount = floatval($_POST['amount']);
        $due_date = $_POST['due_date'];
        $description = trim($_POST['description']);
        
        if (empty($selected_members)) {
            throw new Exception('Pilih minimal satu member!');
        }
        
        $created_count = 0;
        
        foreach ($selected_members as $member_id) {
            $receipt_number = generateCode('INV', 8);
            
            $db->query("
                INSERT INTO payments (member_id, amount, payment_type, payment_method, payment_date, 
                                    due_date, status, description, receipt_number, created_by) 
                VALUES (?, ?, ?, 'transfer', CURRENT_DATE, ?, 'pending', ?, ?, ?)
            ", [$member_id, $amount, $payment_type, $due_date, $description, $receipt_number, $_SESSION['user_id']]);
            
            $created_count++;
        }
        
        $success = "Berhasil membuat $created_count invoice untuk member yang dipilih!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Buat Invoice Massal</h3>
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
    
    <form method="POST" action="">
        <div style="padding: 25px;">
            <!-- Invoice Settings -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-cog"></i>
                        Pengaturan Invoice
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Pembayaran *</label>
                        <select name="payment_type" class="form-control form-select" required>
                            <option value="monthly_fee">Iuran Bulanan</option>
                            <option value="equipment">Peralatan</option>
                            <option value="tournament">Turnamen</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Jumlah (Rp) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Jatuh Tempo *</label>
                        <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-t') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Keterangan tambahan..."></textarea>
                    </div>
                </div>
                
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-calculator"></i>
                        Quick Amount
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                        <button type="button" class="btn btn-outline-primary quick-amount" data-amount="150000">Rp 150.000</button>
                        <button type="button" class="btn btn-outline-primary quick-amount" data-amount="200000">Rp 200.000</button>
                        <button type="button" class="btn btn-outline-primary quick-amount" data-amount="250000">Rp 250.000</button>
                        <button type="button" class="btn btn-outline-primary quick-amount" data-amount="300000">Rp 300.000</button>
                        <button type="button" class="btn btn-outline-primary quick-amount" data-amount="400000">Rp 400.000</button>
                        <button type="button" class="btn btn-outline-primary quick-amount" data-amount="500000">Rp 500.000</button>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h6 style="color: #495057; margin-bottom: 10px;">
                            <i class="fas fa-info-circle"></i>
                            Informasi
                        </h6>
                        <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: #6c757d;">
                            <li>Invoice akan dibuat dengan status "Pending"</li>
                            <li>Nomor invoice akan di-generate otomatis</li>
                            <li>Member akan menerima notifikasi (jika diaktifkan)</li>
                            <li>Laporan pembayaran akan terupdate otomatis</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Member Selection -->
            <h4 style="color: #1E459F; margin-bottom: 20px;">
                <i class="fas fa-users"></i>
                Pilih Member
            </h4>
            
            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                    <i class="fas fa-check-double"></i>
                    Pilih Semua
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectNone()">
                    <i class="fas fa-times"></i>
                    Batal Semua
                </button>
                <button type="button" class="btn btn-sm btn-outline-info" onclick="selectByType('kickboxing')">
                    <i class="fas fa-fist-raised"></i>
                    Kickboxing
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="selectByType('boxing')">
                    <i class="fas fa-hand-rock"></i>
                    Boxing
                </button>
            </div>
            
            <div style="max-height: 400px; overflow-y: auto; border: 2px solid #dee2e6; border-radius: 8px; padding: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                    <?php foreach ($members as $member): ?>
                    <div class="member-card" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; transition: all 0.2s ease;">
                        <div class="form-check">
                            <input class="form-check-input member-checkbox" type="checkbox" name="members[]" value="<?= $member['id'] ?>" id="member_<?= $member['id'] ?>">
                            <label class="form-check-label" for="member_<?= $member['id'] ?>" style="width: 100%; cursor: pointer;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: #1E459F;"><?= htmlspecialchars($member['full_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= $member['member_code'] ?></small>
                                        <br>
                                        <small style="color: #6c757d;"><?= htmlspecialchars($member['email']) ?></small>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php if ($member['last_fee']): ?>
                                            <small style="color: #28a745;">
                                                <i class="fas fa-money-bill"></i>
                                                <?= formatRupiah($member['last_fee']) ?>
                                            </small>
                                        <?php else: ?>
                                            <small style="color: #6c757d;">Belum ada pembayaran</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="margin-top: 15px; font-size: 0.9rem; color: #6c757d;">
                <i class="fas fa-info-circle"></i>
                <span id="selected-count">0</span> member dipilih
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center;">
                <button type="submit" class="btn btn-primary btn-lg" id="create-button" disabled>
                    <i class="fas fa-file-invoice-dollar"></i>
                    Buat Invoice Massal
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<script>
// Quick amount buttons
document.querySelectorAll('.quick-amount').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelector('input[name="amount"]').value = this.dataset.amount;
        
        // Highlight selected button
        document.querySelectorAll('.quick-amount').forEach(btn => btn.classList.remove('btn-primary'));
        this.classList.remove('btn-outline-primary');
        this.classList.add('btn-primary');
    });
});

function selectAll() {
    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = true);
    updateSelectedCount();
}

function selectNone() {
    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
    updateSelectedCount();
}

function selectByType(type) {
    // This would require additional data attributes on checkboxes
    // For now, just select all
    selectAll();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.member-checkbox:checked').length;
    document.getElementById('selected-count').textContent = count;
    document.getElementById('create-button').disabled = count === 0;
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.member-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            updateSelectedCount();
            
            // Highlight selected card
            const card = this.closest('.member-card');
            if (this.checked) {
                card.style.background = 'rgba(30, 69, 159, 0.05)';
                card.style.borderColor = '#1E459F';
            } else {
                card.style.background = '';
                card.style.borderColor = '#dee2e6';
            }
        });
    });
    
    updateSelectedCount();
});
</script>

<?php require_once '../../includes/footer.php'; ?>