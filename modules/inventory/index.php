<?php
$page_title = "Manajemen Stok Barang";
require_once '../../includes/header.php';

// Check access - Semua role bisa akses, tapi fitur berbeda
requireLogin();

// Get filter parameters
$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? 'all'; // all, low_stock, out_of_stock

// Build query
$where = ["i.is_active = 1"];
$params = [];

if ($category !== 'all') {
    $where[] = "i.category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $where[] = "(i.item_name LIKE ? OR i.item_code LIKE ? OR i.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($stock_filter === 'low_stock') {
    $where[] = "i.current_stock < i.min_stock AND i.current_stock > 0";
} elseif ($stock_filter === 'out_of_stock') {
    $where[] = "i.current_stock = 0";
}

$whereClause = implode(' AND ', $where);

// Get inventory items
$items = $db->fetchAll("
    SELECT i.*, 
           u.full_name as created_by_name,
           CASE 
               WHEN i.current_stock = 0 THEN 'out_of_stock'
               WHEN i.current_stock < i.min_stock THEN 'low_stock'
               ELSE 'normal'
           END as stock_status
    FROM inventory_items i
    LEFT JOIN users u ON i.created_by = u.id
    WHERE $whereClause
    ORDER BY i.created_at DESC
", $params);

// Get low stock count untuk alert
$lowStockCount = $db->fetch("
    SELECT COUNT(*) as count 
    FROM inventory_items 
    WHERE is_active = 1 AND current_stock < min_stock AND current_stock > 0
")['count'];

$outOfStockCount = $db->fetch("
    SELECT COUNT(*) as count 
    FROM inventory_items 
    WHERE is_active = 1 AND current_stock = 0
")['count'];

// Categories untuk filter
$categories = [
    'all' => 'Semua Kategori',
    'beverage' => 'Minuman',
    'equipment' => 'Perlengkapan',
    'supplement' => 'Suplemen',
    'merchandise' => 'Merchandise',
    'other' => 'Lainnya'
];
?>

<!-- Alert Stok Menipis -->
<?php if ($lowStockCount > 0 || $outOfStockCount > 0): ?>
<div class="alert alert-warning" style="border-left: 5px solid #FABD32; animation: pulse 2s infinite;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i>
        <div>
            <h5 style="margin: 0; color: #856404;">
                <i class="fas fa-bell"></i> Peringatan Stok!
            </h5>
            <p style="margin: 5px 0 0 0;">
                <?php if ($lowStockCount > 0): ?>
                    <strong><?= $lowStockCount ?></strong> barang stok menipis
                <?php endif; ?>
                <?php if ($lowStockCount > 0 && $outOfStockCount > 0): ?> | <?php endif; ?>
                <?php if ($outOfStockCount > 0): ?>
                    <strong><?= $outOfStockCount ?></strong> barang habis
                <?php endif; ?>
            </p>
        </div>
        <div style="margin-left: auto;">
            <a href="?stock_filter=low_stock" class="btn btn-sm btn-warning">
                <i class="fas fa-eye"></i> Lihat Detail
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-boxes"></i> Manajemen Stok Barang
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Kelola inventaris dan monitoring stok barang</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <?php if (hasPermission('manage_stock')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i>
            Tambah Barang Baru
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="fas fa-print"></i>
            <span class="mobile-hide">Print</span>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-body">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label"><i class="fas fa-search"></i> Cari Barang</label>
                <input type="text" name="search" class="form-control" placeholder="Nama, kode, atau deskripsi..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label"><i class="fas fa-tag"></i> Kategori</label>
                <select name="category" class="form-control">
                    <?php foreach ($categories as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $category === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label"><i class="fas fa-filter"></i> Status Stok</label>
                <select name="stock_filter" class="form-control">
                    <option value="all" <?= $stock_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="low_stock" <?= $stock_filter === 'low_stock' ? 'selected' : '' ?>>Stok Menipis</option>
                    <option value="out_of_stock" <?= $stock_filter === 'out_of_stock' ? 'selected' : '' ?>>Habis</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Grid -->
<?php if (empty($items)): ?>
<div class="card">
    <div class="card-body" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-box-open" style="font-size: 4rem; color: #dee2e6; margin-bottom: 20px;"></i>
        <h4 style="color: #6c757d;">Tidak ada barang ditemukan</h4>
        <p style="color: #adb5bd;">Coba ubah filter atau tambah barang baru</p>
        <?php if (hasPermission('manage_stock')): ?>
        <a href="add.php" class="btn btn-primary" style="margin-top: 20px;">
            <i class="fas fa-plus-circle"></i> Tambah Barang
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="inventory-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    <?php foreach ($items as $item): ?>
    <div class="card inventory-card" style="transition: all 0.3s ease; border-left: 5px solid <?= 
        $item['stock_status'] === 'out_of_stock' ? '#CF2A2A' : 
        ($item['stock_status'] === 'low_stock' ? '#FABD32' : '#1E459F') 
    ?>;">
        <div class="card-body">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <h5 style="margin: 0; color: #1E459F;">
                        <i class="fas fa-box"></i> <?= htmlspecialchars($item['item_name']) ?>
                    </h5>
                    <small style="color: #6c757d;"><?= $item['item_code'] ?></small>
                </div>
                <span class="badge badge-<?= 
                    $item['category'] === 'beverage' ? 'info' : 
                    ($item['category'] === 'equipment' ? 'primary' : 
                    ($item['category'] === 'supplement' ? 'success' : 
                    ($item['category'] === 'merchandise' ? 'warning' : 'secondary'))) 
                ?>" style="text-transform: capitalize;">
                    <?= $item['category'] ?>
                </span>
            </div>
            
            <!-- Stock Info -->
            <div style="background: rgba(0,0,0,0.03); padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.85rem; color: #6c757d; margin-bottom: 5px;">Stok Saat Ini</div>
                        <div style="font-size: 2rem; font-weight: bold; color: <?= 
                            $item['stock_status'] === 'out_of_stock' ? '#CF2A2A' : 
                            ($item['stock_status'] === 'low_stock' ? '#FABD32' : '#28a745') 
                        ?>;">
                            <?= $item['current_stock'] ?>
                            <span style="font-size: 1rem; font-weight: normal; color: #6c757d;"><?= $item['unit'] ?></span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.85rem; color: #6c757d; margin-bottom: 5px;">Min. Stok</div>
                        <div style="font-size: 1.2rem; color: #6c757d;">
                            <?= $item['min_stock'] ?> <?= $item['unit'] ?>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div style="margin-top: 10px; background: rgba(0,0,0,0.1); border-radius: 10px; height: 8px; overflow: hidden;">
                    <?php 
                    $percentage = $item['min_stock'] > 0 ? min(($item['current_stock'] / $item['min_stock']) * 100, 100) : 100;
                    ?>
                    <div style="width: <?= $percentage ?>%; height: 100%; background: <?= 
                        $item['stock_status'] === 'out_of_stock' ? '#CF2A2A' : 
                        ($item['stock_status'] === 'low_stock' ? '#FABD32' : '#28a745') 
                    ?>; transition: width 0.3s ease;"></div>
                </div>
            </div>
            
            <!-- Additional Info -->
            <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span><i class="fas fa-dollar-sign"></i> Harga:</span>
                    <strong><?= formatRupiah($item['unit_price']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span><i class="fas fa-map-marker-alt"></i> Lokasi:</span>
                    <strong><?= htmlspecialchars($item['location'] ?? '-') ?></strong>
                </div>
                <?php if ($item['expiry_date']): ?>
                <div style="display: flex; justify-content: space-between;">
                    <span><i class="fas fa-calendar-times"></i> Kadaluarsa:</span>
                    <strong><?= formatDate($item['expiry_date']) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Actions -->
            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                <a href="view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" style="flex: 1;">
                    <i class="fas fa-eye"></i> Detail
                </a>
                <?php if (hasPermission('manage_stock')): ?>
                <a href="adjust_stock.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-success" style="flex: 1;">
                    <i class="fas fa-exchange-alt"></i> Adjust
                </a>
                <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                    <i class="fas fa-edit"></i>
                </a>
                <?php else: ?>
                <a href="create_request.php?item_id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-info" style="flex: 1;">
                    <i class="fas fa-paper-plane"></i> Request
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Realtime Refresh -->
<div id="realtime-indicator" style="position: fixed; bottom: 20px; right: 20px; background: white; padding: 10px 15px; border-radius: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; z-index: 1000;">
    <div class="spinner-border spinner-border-sm text-primary" role="status" id="realtime-spinner" style="display: none;">
        <span class="sr-only">Loading...</span>
    </div>
    <span id="realtime-text" style="font-size: 0.85rem; color: #6c757d;">
        <i class="fas fa-clock"></i> Auto-refresh aktif
    </span>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.inventory-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

@media print {
    .btn, .page-header > div:last-child, #realtime-indicator, .sidebar, .header {
        display: none !important;
    }
    .inventory-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 768px) {
    .inventory-grid {
        grid-template-columns: 1fr !important;
    }
    .mobile-hide {
        display: none;
    }
}
</style>

<script>
// Realtime data refresh
const REFRESH_INTERVAL = <?= getSetting('realtime_refresh_interval', 30000) ?>;
let refreshTimer;

function refreshInventoryData() {
    const spinner = document.getElementById('realtime-spinner');
    const text = document.getElementById('realtime-text');
    
    spinner.style.display = 'inline-block';
    text.innerHTML = '<i class="fas fa-sync fa-spin"></i> Memperbarui...';
    
    // Reload page untuk refresh data
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

function startRealtimeRefresh() {
    refreshTimer = setInterval(refreshInventoryData, REFRESH_INTERVAL);
    
    // Update countdown
    let secondsLeft = REFRESH_INTERVAL / 1000;
    const countdownTimer = setInterval(() => {
        secondsLeft--;
        if (secondsLeft > 0) {
            document.getElementById('realtime-text').innerHTML = 
                `<i class="fas fa-clock"></i> Refresh dalam ${secondsLeft}s`;
        } else {
            clearInterval(countdownTimer);
        }
    }, 1000);
}

// Start realtime refresh on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startRealtimeRefresh);
} else {
    startRealtimeRefresh();
}

// Stop refresh when user is editing/interacting
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(refreshTimer);
    } else {
        startRealtimeRefresh();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
