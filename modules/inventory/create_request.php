<?php
require_once '../../config/config.php';
requireLogin();

// Staff, Trainers and Members can request
if (!in_array(getUserRole(), ['staff', 'member', 'trainer'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$error = '';
$success = '';

// Get item ID if provided (for pre-selection)
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// PROCESS POST REQUEST BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $reason = trim($_POST['reason']);
    $urgency = $_POST['urgency'];
    
    if (!$item_id || $quantity <= 0) {
        $error = 'Pilih barang dan masukkan jumlah yang valid!';
    } elseif (empty($reason)) {
        $error = 'Alasan permintaan wajib diisi!';
    } else {
        // Check if item exists
        $item = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$item_id]);
        
        if (!$item) {
            $error = 'Barang tidak ditemukan!';
        } elseif ($quantity > $item['current_stock']) {
            $error = "Jumlah melebihi stok tersedia ({$item['current_stock']} {$item['unit']})!";
        } else {
            try {
                // Generate request code
                $request_code = 'REQ-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Create request
                $db->query(
                    "INSERT INTO inventory_requests 
                    (request_code, item_id, requested_by, requested_quantity, reason, priority, request_type, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'usage', 'pending')",
                    [$request_code, $item_id, $_SESSION['user_id'], $quantity, $reason, $urgency]
                );
                
                $request_id = $db->lastInsertId();
                
                // Log activity
                logActivity('create_request', 'inventory_requests', $request_id, null, [
                    'item_id' => $item_id,
                    'item_name' => $item['item_name'],
                    'quantity' => $quantity
                ]);
                
                // Notify admins
                $requester = $db->fetch("SELECT full_name FROM users WHERE id = ?", [$_SESSION['user_id']]);
                $admins = $db->fetchAll("SELECT id FROM users WHERE role IN ('super_admin', 'admin') AND is_active = 1");
                foreach ($admins as $admin) {
                    sendNotification(
                        $admin['id'],
                        'Request Barang Baru',
                        "{$requester['full_name']} request {$quantity} {$item['unit']} '{$item['item_name']}'",
                        'request',
                        'inventory_requests',
                        $request_id,
                        BASE_URL . 'modules/inventory/requests.php?id=' . $request_id
                    );
                }
                
                $success = "Request berhasil dikirim! Admin akan segera memproses.";
                header("Location: requests.php?success=" . urlencode($success));
                exit;
                
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Get selected item if provided
$selected_item = null;
if ($item_id) {
    $selected_item = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$item_id]);
}

// Get all available items
$items = $db->fetchAll("SELECT * FROM inventory_items WHERE current_stock > 0 ORDER BY item_name");

// Now include header after all processing
$page_title = "Request Barang";
require_once '../../includes/header.php';
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-hand-paper"></i> Request Barang
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Ajukan permintaan pengambilan barang</p>
    </div>
    <div>
        <a href="requests.php" class="btn btn-outline-secondary">
            <i class="fas fa-list"></i> Lihat Request Saya
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?= $success ?>
</div>
<?php endif; ?>

<?php if (!$success): ?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="" id="requestForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-box"></i> Pilih Barang *
                        </label>
                        <select name="item_id" id="item_id" class="form-control" required onchange="updateItemInfo()">
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>" 
                                    data-stock="<?= $item['current_stock'] ?>"
                                    data-unit="<?= htmlspecialchars($item['unit']) ?>"
                                    data-code="<?= htmlspecialchars($item['item_code']) ?>"
                                    <?= $selected_item && $selected_item['id'] == $item['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['item_name']) ?> 
                                (<?= htmlspecialchars($item['item_code']) ?>) 
                                - Stok: <?= $item['current_stock'] ?> <?= htmlspecialchars($item['unit']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="itemInfo" style="display: none; background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1E459F;">
                        <div class="row">
                            <div class="col-md-6">
                                <small style="color: #6c757d;">Kode Barang</small>
                                <div style="font-weight: 600;" id="itemCode">-</div>
                            </div>
                            <div class="col-md-6">
                                <small style="color: #6c757d;">Stok Tersedia</small>
                                <div style="font-weight: 600; color: #1E459F; font-size: 1.2rem;" id="itemStock">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-hashtag"></i> Jumlah yang Diminta *
                        </label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" required 
                               placeholder="Masukkan jumlah">
                        <small class="form-text text-muted" id="quantityHelp">
                            Masukkan jumlah barang yang dibutuhkan
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-exclamation-circle"></i> Tingkat Urgensi *
                        </label>
                        <select name="urgency" class="form-control" required>
                            <option value="">-- Pilih Urgensi --</option>
                            <option value="low">Rendah - Bisa ditunda</option>
                            <option value="medium" selected>Sedang - Dalam beberapa hari</option>
                            <option value="high">Tinggi - Segera dibutuhkan</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment-alt"></i> Alasan/Keperluan *
                        </label>
                        <textarea name="reason" class="form-control" rows="4" required 
                                  placeholder="Contoh: Untuk latihan rutin, event championship, dll. Jelaskan secara detail keperluan barang ini."></textarea>
                        <small class="form-text text-muted">
                            Jelaskan secara detail untuk mempercepat persetujuan
                        </small>
                    </div>
                    
                    <hr style="margin: 30px 0;">
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Kirim Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white;">
                <h5 style="margin: 0;">
                    <i class="fas fa-info-circle"></i> Panduan Request
                </h5>
            </div>
            <div class="card-body">
                <ol style="margin: 0; padding-left: 20px; font-size: 0.9rem;">
                    <li style="margin-bottom: 10px;">
                        Pilih barang yang dibutuhkan dari daftar
                    </li>
                    <li style="margin-bottom: 10px;">
                        Pastikan jumlah tidak melebihi stok tersedia
                    </li>
                    <li style="margin-bottom: 10px;">
                        Tentukan tingkat urgensi dengan tepat
                    </li>
                    <li style="margin-bottom: 10px;">
                        Jelaskan alasan secara detail dan jelas
                    </li>
                    <li>
                        Tunggu persetujuan dari Admin
                    </li>
                </ol>
                
                <hr>
                
                <div style="background: #fff3cd; padding: 10px; border-radius: 5px; font-size: 0.85rem;">
                    <strong><i class="fas fa-lightbulb"></i> Tips:</strong>
                    <p style="margin: 5px 0 0 0;">
                        Request yang disertai alasan jelas dan lengkap akan diproses lebih cepat!
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <div class="card-header" style="background: #f8f9fa;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-clock"></i> Status Request
                </h5>
            </div>
            <div class="card-body" style="font-size: 0.9rem;">
                <div style="margin-bottom: 10px;">
                    <span class="badge badge-warning">Pending</span>
                    <span style="margin-left: 10px;">Menunggu review</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span class="badge badge-success">Approved</span>
                    <span style="margin-left: 10px;">Disetujui</span>
                </div>
                <div>
                    <span class="badge badge-danger">Rejected</span>
                    <span style="margin-left: 10px;">Ditolak</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function updateItemInfo() {
    const select = document.getElementById('item_id');
    const selected = select.options[select.selectedIndex];
    const itemInfo = document.getElementById('itemInfo');
    const itemCode = document.getElementById('itemCode');
    const itemStock = document.getElementById('itemStock');
    const quantityInput = document.getElementById('quantity');
    const quantityHelp = document.getElementById('quantityHelp');
    
    if (selected.value) {
        const stock = selected.getAttribute('data-stock');
        const unit = selected.getAttribute('data-unit');
        const code = selected.getAttribute('data-code');
        
        itemInfo.style.display = 'block';
        itemCode.textContent = code;
        itemStock.textContent = stock + ' ' + unit;
        
        quantityInput.max = stock;
        quantityHelp.textContent = `Maksimal: ${stock} ${unit}`;
    } else {
        itemInfo.style.display = 'none';
    }
}

// Initialize if item is pre-selected
if (document.getElementById('item_id').value) {
    updateItemInfo();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
