<?php
$page_title = "Tambah Barang Baru";
require_once '../../includes/header.php';

// Check permission
if (!hasPermission('manage_stock')) {
    redirect('modules/inventory/index.php?error=unauthorized');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_code = strtoupper(trim($_POST['item_code']));
    $item_name = trim($_POST['item_name']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $unit = trim($_POST['unit']);
    $current_stock = (int)$_POST['current_stock'];
    $min_stock = (int)$_POST['min_stock'];
    $unit_price = (float)$_POST['unit_price'];
    $location = trim($_POST['location']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    // Validation
    if (empty($item_code) || empty($item_name) || empty($category)) {
        $error = 'Kode barang, nama barang, dan kategori harus diisi!';
    } else {
        // Check if item_code already exists
        $existing = $db->fetch("SELECT id FROM inventory_items WHERE item_code = ?", [$item_code]);
        
        if ($existing) {
            $error = "Kode barang '$item_code' sudah digunakan!";
        } else {
            try {
                // Insert item
                $db->query(
                    "INSERT INTO inventory_items 
                    (item_code, item_name, category, description, unit, current_stock, min_stock, 
                     unit_price, location, expiry_date, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$item_code, $item_name, $category, $description, $unit, $current_stock, $min_stock,
                     $unit_price, $location, $expiry_date, $_SESSION['user_id']]
                );
                
                $item_id = $db->lastInsertId();
                
                // Create initial stock history
                if ($current_stock > 0) {
                    $db->query(
                        "INSERT INTO inventory_history 
                        (item_id, transaction_type, quantity, stock_before, stock_after, notes, performed_by) 
                        VALUES (?, 'initial', ?, 0, ?, 'Stok awal barang', ?)",
                        [$item_id, $current_stock, $current_stock, $_SESSION['user_id']]
                    );
                }
                
                // Log activity
                logActivity('create_inventory_item', 'inventory_items', $item_id, null, [
                    'item_code' => $item_code,
                    'item_name' => $item_name,
                    'current_stock' => $current_stock
                ]);
                
                // Send notification to admins if low stock
                if ($current_stock < $min_stock) {
                    $admins = $db->fetchAll("SELECT id FROM users WHERE role IN ('super_admin', 'admin') AND is_active = 1");
                    foreach ($admins as $admin) {
                        sendNotification(
                            $admin['id'],
                            'Barang Baru Stok Menipis',
                            "Barang baru '$item_name' ditambahkan dengan stok $current_stock (di bawah minimum $min_stock)",
                            'stock_alert',
                            'inventory_items',
                            $item_id,
                            BASE_URL . 'modules/inventory/view.php?id=' . $item_id
                        );
                    }
                }
                
                $success = "Barang '$item_name' berhasil ditambahkan!";
                
                // Redirect after 2 seconds
                header("refresh:2;url=index.php");
                
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
            <i class="fas fa-plus-circle"></i> Tambah Barang Baru
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Tambahkan barang baru ke inventaris</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary">
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
                               placeholder="Contoh: BEV001, EQP001" style="text-transform: uppercase;">
                        <small class="form-text text-muted">Kode unik untuk identifikasi barang</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-box"></i> Nama Barang *
                        </label>
                        <input type="text" name="item_name" class="form-control" required 
                               placeholder="Contoh: Aqua Botol 600ml">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i> Kategori *
                        </label>
                        <select name="category" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="beverage">Minuman</option>
                            <option value="equipment">Perlengkapan</option>
                            <option value="supplement">Suplemen</option>
                            <option value="merchandise">Merchandise</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i> Deskripsi
                        </label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Deskripsi lengkap barang..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Lokasi Penyimpanan
                        </label>
                        <input type="text" name="location" class="form-control" 
                               placeholder="Contoh: Gudang A - Rak 1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar-times"></i> Tanggal Kadaluarsa
                        </label>
                        <input type="date" name="expiry_date" class="form-control">
                        <small class="form-text text-muted">Opsional, untuk minuman/suplemen</small>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-md-6">
                    <h5 style="color: #CF2A2A; margin-bottom: 20px;">
                        <i class="fas fa-warehouse"></i> Stok & Harga
                    </h5>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-balance-scale"></i> Satuan
                        </label>
                        <input type="text" name="unit" class="form-control" value="pcs" required 
                               placeholder="Contoh: pcs, box, liter, kg">
                        <small class="form-text text-muted">Satuan penghitungan (pcs, box, liter, kg, dll)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-cubes"></i> Stok Awal
                        </label>
                        <input type="number" name="current_stock" class="form-control" value="0" min="0" required>
                        <small class="form-text text-muted">Jumlah stok saat ini</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-exclamation-triangle"></i> Stok Minimum
                        </label>
                        <input type="number" name="min_stock" class="form-control" value="10" min="0" required>
                        <small class="form-text text-muted">Batas minimum untuk alert stok menipis</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-dollar-sign"></i> Harga Satuan
                        </label>
                        <input type="number" name="unit_price" class="form-control" value="0" min="0" step="0.01" required>
                        <small class="form-text text-muted">Harga per satuan (dalam Rupiah)</small>
                    </div>
                    
                    <!-- Preview Card -->
                    <div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); padding: 20px; border-radius: 15px; color: white; margin-top: 30px;">
                        <h6 style="margin: 0 0 10px 0;">
                            <i class="fas fa-info-circle"></i> Tips:
                        </h6>
                        <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem;">
                            <li>Gunakan kode barang yang mudah diingat</li>
                            <li>Set stok minimum sesuai kebutuhan</li>
                            <li>Lokasi penyimpanan memudahkan pencarian</li>
                            <li>Tanggal kadaluarsa penting untuk minuman/suplemen</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Barang
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
