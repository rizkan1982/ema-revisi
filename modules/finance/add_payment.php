<?php
$page_title = "Tambah Pembayaran";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

// Get all active members for selection
$members = $db->fetchAll("
    SELECT m.id, m.member_code, u.full_name, u.email 
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE u.is_active = 1 
    ORDER BY u.full_name ASC
");

// Get member details via AJAX
if ($_GET['ajax'] === 'member_details' && isset($_GET['member_id'])) {
    $member = $db->fetch("
        SELECT m.*, u.full_name, u.email, u.phone,
               (SELECT amount FROM payments WHERE member_id = m.id AND payment_type = 'monthly_fee' ORDER BY created_at DESC LIMIT 1) as last_monthly_fee
        FROM members m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id = ?
    ", [$_GET['member_id']]);
    
    header('Content-Type: application/json');
    echo json_encode($member);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $member_id = $_POST['member_id'];
        $amount = floatval($_POST['amount']);
        $payment_type = $_POST['payment_type'];
        $payment_method = $_POST['payment_method'];
        $payment_date = $_POST['payment_date'];
        $due_date = $_POST['due_date'] ?: null;
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        // Generate receipt number
        $receipt_number = generateCode('RCP', 8);
        
        $db->query("
            INSERT INTO payments (member_id, amount, payment_type, payment_method, payment_date, 
                                due_date, status, description, receipt_number, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [$member_id, $amount, $payment_type, $payment_method, $payment_date, 
            $due_date, $status, $description, $receipt_number, $_SESSION['user_id']]);
        
        $payment_id = $db->lastInsertId();
        
        // Send notification to member
        $member = $db->fetch("SELECT u.id, u.full_name FROM members m JOIN users u ON m.user_id = u.id WHERE m.id = ?", [$member_id]);
        
        $notification_title = "Pembayaran " . ($status === 'paid' ? 'Diterima' : 'Baru');
        $notification_message = "Pembayaran Anda sebesar " . formatRupiah($amount) . " untuk " . ucwords(str_replace('_', ' ', $payment_type)) . " telah " . ($status === 'paid' ? 'diterima' : 'dicatat') . ". No. Receipt: $receipt_number";
        
        $db->query("
            INSERT INTO notifications (recipient_id, title, message, type) 
            VALUES (?, ?, ?, 'payment_reminder')
        ", [$member['id'], $notification_title, $notification_message]);
        
        $success = "Pembayaran berhasil ditambahkan dengan No. Receipt: $receipt_number";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tambah Pembayaran Baru</h3>
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
    
    <form method="POST" action="" id="paymentForm">
        <div style="padding: 25px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Member Selection -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-user"></i>
                        Informasi Member
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Pilih Member *</label>
                        <select name="member_id" id="memberSelect" class="form-control form-select" required>
                            <option value="">-- Pilih Member --</option>
                            <?php foreach ($members as $member): ?>
                            <option value="<?= $member['id'] ?>" data-code="<?= $member['member_code'] ?>">
                                <?= htmlspecialchars($member['full_name']) ?> (<?= $member['member_code'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Member Details Display -->
                    <div id="memberDetails" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <h6 style="color: #1E459F; margin-bottom: 10px;">Detail Member</h6>
                        <div id="memberInfo"></div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-money-bill-wave"></i>
                        Informasi Pembayaran
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Pembayaran *</label>
                        <select name="payment_type" id="paymentType" class="form-control form-select" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="monthly_fee">Iuran Bulanan</option>
                            <option value="registration">Pendaftaran</option>
                            <option value="equipment">Peralatan</option>
                            <option value="tournament">Turnamen</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Jumlah (Rp) *</label>
                        <input type="number" name="amount" id="paymentAmount" class="form-control" step="0.01" min="0" required>
                        <small class="text-muted">Contoh: 150000 untuk Rp 150.000</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Metode Pembayaran *</label>
                        <select name="payment_method" class="form-control form-select" required>
                            <option value="">-- Pilih Metode --</option>
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="e_wallet">E-Wallet</option>
                            <option value="credit_card">Kartu Kredit</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-control form-select" required>
                            <option value="paid">Paid (Lunas)</option>
                            <option value="pending">Pending (Belum Bayar)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
                <div class="form-group">
                    <label class="form-label">Tanggal Pembayaran *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Jatuh Tempo</label>
                    <input type="date" name="due_date" class="form-control">
                    <small class="text-muted">Kosongkan jika pembayaran langsung</small>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">Keterangan</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Simpan Pembayaran
                </button>
                
                <button type="button" id="saveAndPrint" class="btn btn-success btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-print"></i>
                    Simpan & Cetak Receipt
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Quick Amount Buttons -->
<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px;">
        <h6 style="color: #1E459F; margin-bottom: 15px;">Quick Amount (Jumlah Cepat)</h6>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="150000">Rp 150.000</button>
            <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="200000">Rp 200.000</button>
            <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="250000">Rp 250.000</button>
            <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="300000">Rp 300.000</button>
            <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="500000">Rp 500.000</button>
            <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="1000000">Rp 1.000.000</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const memberSelect = document.getElementById('memberSelect');
    const memberDetails = document.getElementById('memberDetails');
    const memberInfo = document.getElementById('memberInfo');
    const paymentAmount = document.getElementById('paymentAmount');
    const paymentType = document.getElementById('paymentType');
    
    // Member selection change
    memberSelect.addEventListener('change', function() {
        if (this.value) {
            fetch(`?ajax=member_details&member_id=${this.value}`)
                .then(response => response.json())
                .then(data => {
                    memberInfo.innerHTML = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <strong>Nama:</strong> ${data.full_name}<br>
                                <strong>Email:</strong> ${data.email}<br>
                                <strong>Phone:</strong> ${data.phone || '-'}
                            </div>
                            <div>
                                <strong>Tipe:</strong> ${data.martial_art_type}<br>
                                <strong>Kelas:</strong> ${data.class_type}<br>
                                <strong>Bergabung:</strong> ${new Date(data.join_date).toLocaleDateString('id-ID')}
                            </div>
                        </div>
                    `;
                    memberDetails.style.display = 'block';
                    
                    // Set default amount for monthly fee
                    if (data.last_monthly_fee && paymentType.value === 'monthly_fee') {
                        paymentAmount.value = data.last_monthly_fee;
                    }
                });
        } else {
            memberDetails.style.display = 'none';
        }
    });
    
    // Payment type change
    paymentType.addEventListener('change', function() {
        if (this.value === 'monthly_fee' && memberSelect.value) {
            const selectedMember = memberSelect.options[memberSelect.selectedIndex];
            // Could set default monthly fee based on member type
            // For now, we'll use a standard amount
            paymentAmount.value = '200000';
        }
    });
    
    // Quick amount buttons
    document.querySelectorAll('.quick-amount').forEach(button => {
        button.addEventListener('click', function() {
            paymentAmount.value = this.dataset.amount;
            
            // Highlight selected button
            document.querySelectorAll('.quick-amount').forEach(btn => btn.classList.remove('btn-primary'));
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');
        });
    });
    
    // Save and print functionality
    document.getElementById('saveAndPrint').addEventListener('click', function() {
        // Add a hidden input to indicate print action
        const printInput = document.createElement('input');
        printInput.type = 'hidden';
        printInput.name = 'print_receipt';
        printInput.value = '1';
        document.getElementById('paymentForm').appendChild(printInput);
        
        document.getElementById('paymentForm').submit();
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>