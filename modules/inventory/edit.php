<?php
$page_title = "Edit Barang";
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
    $item_code = strtoupper(trim($_POST['item_code']));
    $item_name = trim($_POST['item_name']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $unit = trim($_POST['unit']);
    $min_stock = (int)$_POST['min_stock'];
    $unit_price = (float)$_POST['unit_price'];
    $location = trim($_POST['location']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    // Validation
    if (empty($item_code) || empty($item_name) || empty($category)) {
        $error = 'Kode barang, nama barang, dan kategori harus diisi!';
    } else {
        // Check if item_code already exists (except current item)
        $existing = $db->fetch("SELECT id FROM inventory_items WHERE item_code = ? AND id != ?", [$item_code, $id]);
        
        if ($existing) {
            $error = "Kode barang '$item_code' sudah digunakan!";
        } else {
            try {
                // Store old data for logging
                $old_data = [
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'category' => $item['category'],
                    'min_stock' => $item['min_stock'],
                    'unit_price' => $item['unit_price']
                ];
                
                // Update item
                $db->query(
                    "UPDATE inventory_items SET 
                    item_code = ?, item_name = ?, category = ?, description = ?, unit = ?, 
                    min_stock = ?, unit_price = ?, location = ?, expiry_date = ?, updated_at = NOW() 
                    WHERE id = ?",
                    [$item_code, $item_name, $category, $description, $unit, $min_stock, 
                     $unit_price, $location, $expiry_date, $id]
                );
                
                // Log activity
                logActivity('update_inventory_item', 'inventory_items', $id, $old_data, [
                    'item_code' => $item_code,
                    'item_name' => $item_name,
                    'category' => $category,
                    'min_stock' => $min_stock,
                    'unit_price' => $unit_price
                ]);
                
                $success = "Barang '$item_name' berhasil diperbarui!";
                
                // Refresh item data
                $item = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$id]);
                
                // Redirect after 2 seconds
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
            <i class="fas fa-edit"></i> Edit Barang
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Perbarui informasi barang</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-list"></i> List
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
<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <!-- Left Column -->
                <div class="col-md-6">
                    <h5 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i> Informasi Barang
                    </h5>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-barcode"></i> Kode Barang *
                        </label>
                        <input type="text" name="item_code" class="form-control" required 
                               value="<?= htmlspecialchars($item['item_code']) ?>"
                               style="text-transform: uppercase;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-box"></i> Nama Barang *
                        </label>
                        <input type="text" name="item_name" class="form-control" required 
                               value="<?= htmlspecialchars($item['item_name']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i> Kategori *
                        </label>
                        <select name="category" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="beverage" <?= $item['category'] === 'beverage' ? 'selected' : '' ?>>Minuman</option>
                            <option value="equipment" <?= $item['category'] === 'equipment' ? 'selected' : '' ?>>Perlengkapan</option>
                            <option value="supplement" <?= $item['category'] === 'supplement' ? 'selected' : '' ?>>Suplemen</option>
                            <option value="merchandise" <?= $item['category'] === 'merchandise' ? 'selected' : '' ?>>Merchandise</option>
                            <option value="other" <?= $item['category'] === 'other' ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i> Deskripsi
                        </label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($item['description']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Lokasi Penyimpanan
                        </label>
                        <input type="text" name="location" class="form-control" 
                               value="<?= htmlspecialchars($item['location']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar-times"></i> Tanggal Kadaluarsa
                        </label>
                        <input type="date" name="expiry_date" class="form-control" 
                               value="<?= $item['expiry_date'] ?>">
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-md-6">
                    <h5 style="color: #CF2A2A; margin-bottom: 20px;">
                        <i class="fas fa-warehouse"></i> Stok & Harga
                    </h5>
                    
                    <!-- Current Stock (Read-only) -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-cubes"></i> Stok Saat Ini
                        </label>
                        <input type="number" class="form-control" 
                               value="<?= $item['current_stock'] ?>" readonly 
                               style="background-color: #e9ecef;">
                        <small class="form-text text-muted">
                            Untuk mengubah stok, gunakan menu 
                            <a href="adjust_stock.php?id=<?= $id ?>">Sesuaikan Stok</a>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-balance-scale"></i> Satuan
                        </label>
                        <input type="text" name="unit" class="form-control" required 
                               value="<?= htmlspecialchars($item['unit']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-exclamation-triangle"></i> Stok Minimum
                        </label>
                        <input type="number" name="min_stock" class="form-control" min="0" required 
                               value="<?= $item['min_stock'] ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-dollar-sign"></i> Harga Satuan
                        </label>
                        <input type="number" name="unit_price" class="form-control" min="0" step="0.01" required 
                               value="<?= $item['unit_price'] ?>">
                    </div>
                    
                    <!-- Info Card -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #1E459F; margin-top: 30px;">
                        <h6 style="margin: 0 0 10px 0; color: #1E459F;">
                            <i class="fas fa-info-circle"></i> Informasi Tambahan
                        </h6>
                        <div style="font-size: 0.9rem; color: #6c757d;">
                            <div style="margin-bottom: 5px;">
                                <strong>Dibuat:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                            </div>
                            <div>
                                <strong>Terakhir Update:</strong> <?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

.form-control:focus {
    border-color: #1E459F;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25);
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
