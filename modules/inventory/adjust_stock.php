<?php
$page_title = "Sesuaikan Stok";
require_once '../../includes/header.php';

// Check permission
if (!hasPermission('manage_stock')) {
    redirect('modules/inventory/index.php?error=unauthorized');
}

$error = '';
$success = '';

// Get item ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    redirect('modules/inventory/index.php?error=invalid_id');
}

// Fetch item data
$item = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$id]);

if (!$item) {
    redirect('modules/inventory/index.php?error=not_found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_type = $_POST['transaction_type'];
    $quantity = (int)$_POST['quantity'];
    $notes = trim($_POST['notes']);
    
    if ($quantity <= 0) {
        $error = 'Jumlah harus lebih dari 0!';
    } elseif (empty($notes)) {
        $error = 'Catatan/alasan wajib diisi!';
    } else {
        $stock_before = $item['current_stock'];
        
        // Calculate new stock
        if ($transaction_type === 'in') {
            $stock_after = $stock_before + $quantity;
        } elseif ($transaction_type === 'out') {
            $stock_after = $stock_before - $quantity;
            if ($stock_after < 0) {
                $error = 'Stok tidak mencukupi! Stok tersedia: ' . $stock_before;
            }
        } elseif ($transaction_type === 'adjustment') {
            // For adjustment, quantity is the new stock value
            $stock_after = $quantity;
            $quantity = abs($stock_after - $stock_before);
        } else {
            $error = 'Tipe transaksi tidak valid!';
        }
        
        if (!$error) {
            try {
                // Update stock
                $db->query(
                    "UPDATE inventory_items SET current_stock = ?, updated_at = NOW() WHERE id = ?",
                    [$stock_after, $id]
                );
                
                // Create history record
                $db->query(
                    "INSERT INTO inventory_history 
                    (item_id, transaction_type, quantity, stock_before, stock_after, notes, performed_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$id, $transaction_type, $quantity, $stock_before, $stock_after, $notes, $_SESSION['user_id']]
                );
                
                // Log activity
                logActivity('adjust_stock', 'inventory_items', $id, 
                    ['stock' => $stock_before], 
                    ['stock' => $stock_after, 'type' => $transaction_type]
                );
                
                // Check if stock is low and send notification
                if ($stock_after < $item['min_stock'] && $stock_before >= $item['min_stock']) {
                    $admins = $db->fetchAll("SELECT id FROM users WHERE role IN ('super_admin', 'admin') AND is_active = 1");
                    foreach ($admins as $admin) {
                        sendNotification(
                            $admin['id'],
                            'Stok Barang Menipis',
                            "Stok '{$item['item_name']}' sekarang $stock_after (di bawah minimum {$item['min_stock']})",
                            'stock_alert',
                            'inventory_items',
                            $id,
                            BASE_URL . 'modules/inventory/view.php?id=' . $id
                        );
                    }
                }
                
                $success = "Stok berhasil disesuaikan! Stok sekarang: $stock_after {$item['unit']}";
                header("refresh:2;url=view.php?id=$id");
                
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-exchange-alt"></i> Sesuaikan Stok
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">
            <?= htmlspecialchars($item['item_name']) ?> (<?= htmlspecialchars($item['item_code']) ?>)
        </p>
    </div>
    <div>
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
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
                <form method="POST" action="" id="adjustStockForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-exchange-alt"></i> Jenis Transaksi *
                        </label>
                        <select name="transaction_type" id="transaction_type" class="form-control" required onchange="updateFormLabels()">
                            <option value="">-- Pilih Jenis Transaksi --</option>
                            <option value="in">Stok Masuk (+)</option>
                            <option value="out">Stok Keluar (-)</option>
                            <option value="adjustment">Penyesuaian Manual</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" id="quantityLabel">
                            <i class="fas fa-hashtag"></i> Jumlah *
                        </label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" required 
                               placeholder="Masukkan jumlah" onkeyup="calculateNewStock()">
                        <small class="form-text text-muted" id="quantityHelp">
                            Masukkan jumlah barang
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-sticky-note"></i> Catatan/Alasan *
                        </label>
                        <textarea name="notes" class="form-control" rows="3" required 
                                  placeholder="Contoh: Pembelian baru dari supplier, Digunakan untuk event, Koreksi stok fisik, dll"></textarea>
                        <small class="form-text text-muted">
                            Wajib diisi untuk audit trail
                        </small>
                    </div>
                    
                    <!-- Stock Preview -->
                    <div id="stockPreview" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #1E459F; margin-top: 20px;">
                        <h6 style="color: #1E459F; margin-bottom: 15px;">
                            <i class="fas fa-calculator"></i> Preview Perubahan Stok
                        </h6>
                        <div class="row">
                            <div class="col-md-4 text-center" style="padding: 15px;">
                                <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Stok Sekarang</div>
                                <div style="font-size: 2rem; font-weight: 700; color: #6c757d;">
                                    <?= $item['current_stock'] ?>
                                </div>
                                <div style="color: #6c757d;"><?= htmlspecialchars($item['unit']) ?></div>
                            </div>
                            <div class="col-md-4 text-center" style="padding: 15px;">
                                <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Perubahan</div>
                                <div id="changeAmount" style="font-size: 2rem; font-weight: 700;">
                                    +0
                                </div>
                                <div style="color: #6c757d;"><?= htmlspecialchars($item['unit']) ?></div>
                            </div>
                            <div class="col-md-4 text-center" style="padding: 15px; background: white; border-radius: 8px;">
                                <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Stok Baru</div>
                                <div id="newStock" style="font-size: 2rem; font-weight: 700; color: #1E459F;">
                                    <?= $item['current_stock'] ?>
                                </div>
                                <div style="color: #6c757d;"><?= htmlspecialchars($item['unit']) ?></div>
                            </div>
                        </div>
                        <div id="lowStockWarning" style="display: none; margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; color: #856404;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Peringatan:</strong> Stok baru akan di bawah minimum (<?= $item['min_stock'] ?> <?= htmlspecialchars($item['unit']) ?>)
                        </div>
                        <div id="outOfStockWarning" style="display: none; margin-top: 15px; padding: 10px; background: #f8d7da; border-radius: 5px; color: #721c24;">
                            <i class="fas fa-times-circle"></i> 
                            <strong>Error:</strong> Stok tidak mencukupi! Stok tersedia: <?= $item['current_stock'] ?> <?= htmlspecialchars($item['unit']) ?>
                        </div>
                    </div>
                    
                    <hr style="margin: 30px 0;">
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Simpan Penyesuaian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header" style="background: #1E459F; color: white;">
                <h5 style="margin: 0;">
                    <i class="fas fa-info-circle"></i> Informasi Barang
                </h5>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 15px;">
                    <small style="color: #6c757d; display: block;">Nama Barang</small>
                    <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                </div>
                <div style="margin-bottom: 15px;">
                    <small style="color: #6c757d; display: block;">Kode Barang</small>
                    <strong><?= htmlspecialchars($item['item_code']) ?></strong>
                </div>
                <div style="margin-bottom: 15px;">
                    <small style="color: #6c757d; display: block;">Stok Saat Ini</small>
                    <strong style="font-size: 1.5rem; color: #1E459F;">
                        <?= $item['current_stock'] ?> <?= htmlspecialchars($item['unit']) ?>
                    </strong>
                </div>
                <div style="margin-bottom: 15px;">
                    <small style="color: #6c757d; display: block;">Minimum Stok</small>
                    <strong style="color: #CF2A2A;">
                        <?= $item['min_stock'] ?> <?= htmlspecialchars($item['unit']) ?>
                    </strong>
                </div>
                <div>
                    <small style="color: #6c757d; display: block;">Lokasi</small>
                    <strong><?= htmlspecialchars($item['location'] ?: '-') ?></strong>
                </div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <div class="card-header" style="background: #f8f9fa;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-lightbulb"></i> Panduan
                </h5>
            </div>
            <div class="card-body">
                <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem;">
                    <li style="margin-bottom: 10px;">
                        <strong>Stok Masuk:</strong> Untuk pembelian baru, pengembalian barang
                    </li>
                    <li style="margin-bottom: 10px;">
                        <strong>Stok Keluar:</strong> Untuk pemakaian, penjualan, atau barang keluar
                    </li>
                    <li>
                        <strong>Penyesuaian Manual:</strong> Untuk koreksi stok fisik atau perbaikan data
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const currentStock = <?= $item['current_stock'] ?>;
const minStock = <?= $item['min_stock'] ?>;

function updateFormLabels() {
    const type = document.getElementById('transaction_type').value;
    const quantityLabel = document.getElementById('quantityLabel');
    const quantityHelp = document.getElementById('quantityHelp');
    const quantityInput = document.getElementById('quantity');
    
    if (type === 'adjustment') {
        quantityLabel.innerHTML = '<i class="fas fa-hashtag"></i> Stok Baru *';
        quantityHelp.textContent = 'Masukkan nilai stok baru yang benar';
        quantityInput.placeholder = 'Masukkan stok baru';
    } else {
        quantityLabel.innerHTML = '<i class="fas fa-hashtag"></i> Jumlah *';
        quantityHelp.textContent = 'Masukkan jumlah barang';
        quantityInput.placeholder = 'Masukkan jumlah';
    }
    
    calculateNewStock();
}

function calculateNewStock() {
    const type = document.getElementById('transaction_type').value;
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const preview = document.getElementById('stockPreview');
    const changeAmount = document.getElementById('changeAmount');
    const newStock = document.getElementById('newStock');
    const lowStockWarning = document.getElementById('lowStockWarning');
    const outOfStockWarning = document.getElementById('outOfStockWarning');
    const submitBtn = document.getElementById('submitBtn');
    
    if (!type || quantity === 0) {
        preview.style.display = 'none';
        return;
    }
    
    preview.style.display = 'block';
    
    let newStockValue;
    let change;
    
    if (type === 'in') {
        newStockValue = currentStock + quantity;
        change = '+' + quantity;
        changeAmount.style.color = '#28a745';
    } else if (type === 'out') {
        newStockValue = currentStock - quantity;
        change = '-' + quantity;
        changeAmount.style.color = '#dc3545';
    } else if (type === 'adjustment') {
        newStockValue = quantity;
        change = (quantity > currentStock ? '+' : '') + (quantity - currentStock);
        changeAmount.style.color = quantity > currentStock ? '#28a745' : '#dc3545';
    }
    
    changeAmount.textContent = change;
    newStock.textContent = newStockValue;
    
    // Show warnings
    lowStockWarning.style.display = 'none';
    outOfStockWarning.style.display = 'none';
    submitBtn.disabled = false;
    
    if (newStockValue < 0) {
        outOfStockWarning.style.display = 'block';
        submitBtn.disabled = true;
        newStock.style.color = '#dc3545';
    } else if (newStockValue < minStock) {
        lowStockWarning.style.display = 'block';
        newStock.style.color = '#ffc107';
    } else {
        newStock.style.color = '#28a745';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
