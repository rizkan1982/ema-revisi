<?php
$page_title = "Detail Barang";
require_once '../../includes/header.php';

// Get item ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    redirect('modules/inventory/index.php?error=invalid_id');
}

// Fetch item data
$item = $db->fetch("SELECT i.*, u.full_name as created_by_name 
                    FROM inventory_items i 
                    LEFT JOIN users u ON i.created_by = u.id 
                    WHERE i.id = ?", [$id]);

if (!$item) {
    redirect('modules/inventory/index.php?error=not_found');
}

// Fetch history
$history = $db->fetchAll(
    "SELECT h.*, u.full_name as performed_by_name 
     FROM inventory_history h 
     LEFT JOIN users u ON h.performed_by = u.id 
     WHERE h.item_id = ? 
     ORDER BY h.created_at DESC 
     LIMIT 20", 
    [$id]
);

// Calculate stock percentage
$stock_percentage = $item['min_stock'] > 0 ? ($item['current_stock'] / $item['min_stock']) * 100 : 100;
$stock_percentage = min($stock_percentage, 100);

// Determine stock status
if ($item['current_stock'] == 0) {
    $stock_status = 'out_of_stock';
    $stock_badge = 'danger';
    $stock_text = 'Habis';
} elseif ($item['current_stock'] < $item['min_stock']) {
    $stock_status = 'low_stock';
    $stock_badge = 'warning';
    $stock_text = 'Menipis';
} else {
    $stock_status = 'in_stock';
    $stock_badge = 'success';
    $stock_text = 'Tersedia';
}

// Category labels
$categories = [
    'beverage' => 'Minuman',
    'equipment' => 'Perlengkapan',
    'supplement' => 'Suplemen',
    'merchandise' => 'Merchandise',
    'other' => 'Lainnya'
];

$category_label = $categories[$item['category']] ?? $item['category'];
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-box"></i> Detail Barang
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Informasi lengkap barang inventaris</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <?php if (hasPermission('manage_stock')): ?>
            <a href="edit.php?id=<?= $id ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="adjust_stock.php?id=<?= $id ?>" class="btn btn-info">
                <i class="fas fa-exchange-alt"></i> Sesuaikan Stok
            </a>
        <?php else: ?>
            <a href="create_request.php?item_id=<?= $id ?>" class="btn btn-primary">
                <i class="fas fa-hand-paper"></i> Request Barang
            </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="row">
    <!-- Main Info Card -->
    <div class="col-lg-8">
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white;">
                <h5 style="margin: 0;">
                    <i class="fas fa-info-circle"></i> Informasi Barang
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td style="width: 40%; font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-barcode"></i> Kode Barang
                                </td>
                                <td>
                                    <span class="badge badge-dark" style="font-size: 1rem; padding: 8px 15px;">
                                        <?= htmlspecialchars($item['item_code']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-box"></i> Nama Barang
                                </td>
                                <td style="font-size: 1.1rem; font-weight: 600; color: #1E459F;">
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-tag"></i> Kategori
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?= $category_label ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-align-left"></i> Deskripsi
                                </td>
                                <td><?= nl2br(htmlspecialchars($item['description'] ?: '-')) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td style="width: 40%; font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-balance-scale"></i> Satuan
                                </td>
                                <td><?= htmlspecialchars($item['unit']) ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-dollar-sign"></i> Harga Satuan
                                </td>
                                <td style="font-weight: 600; color: #CF2A2A;">
                                    Rp <?= number_format($item['unit_price'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-map-marker-alt"></i> Lokasi
                                </td>
                                <td><?= htmlspecialchars($item['location'] ?: '-') ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-calendar-times"></i> Kadaluarsa
                                </td>
                                <td>
                                    <?php if ($item['expiry_date']): ?>
                                        <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                                        <?php
                                        $days_to_expiry = (strtotime($item['expiry_date']) - time()) / (60 * 60 * 24);
                                        if ($days_to_expiry < 0):
                                        ?>
                                            <span class="badge badge-danger">Kadaluarsa</span>
                                        <?php elseif ($days_to_expiry < 30): ?>
                                            <span class="badge badge-warning">Segera Kadaluarsa</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock History -->
        <div class="card">
            <div class="card-header" style="background: #f8f9fa;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-history"></i> Riwayat Stok (20 Terakhir)
                </h5>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table table-hover" style="margin: 0;">
                        <thead style="background: #f8f9fa;">
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Jumlah</th>
                                <th>Stok Sebelum</th>
                                <th>Stok Sesudah</th>
                                <th>Catatan</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #6c757d;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p style="margin: 10px 0 0 0;">Belum ada riwayat transaksi</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                <?php
                                $type_icons = [
                                    'initial' => ['icon' => 'plus-circle', 'color' => '#28a745', 'label' => 'Stok Awal'],
                                    'in' => ['icon' => 'arrow-down', 'color' => '#28a745', 'label' => 'Masuk'],
                                    'out' => ['icon' => 'arrow-up', 'color' => '#dc3545', 'label' => 'Keluar'],
                                    'adjustment' => ['icon' => 'edit', 'color' => '#ffc107', 'label' => 'Penyesuaian']
                                ];
                                $type_info = $type_icons[$h['transaction_type']] ?? ['icon' => 'question', 'color' => '#6c757d', 'label' => $h['transaction_type']];
                                ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-<?= $type_info['icon'] ?>" style="color: <?= $type_info['color'] ?>;"></i>
                                        <?= $type_info['label'] ?>
                                    </td>
                                    <td style="font-weight: 600; color: <?= $type_info['color'] ?>;">
                                        <?= $h['transaction_type'] === 'out' ? '-' : '+' ?><?= $h['quantity'] ?>
                                    </td>
                                    <td><?= $h['stock_before'] ?></td>
                                    <td style="font-weight: 600;"><?= $h['stock_after'] ?></td>
                                    <td><?= htmlspecialchars($h['notes']) ?></td>
                                    <td><?= htmlspecialchars($h['performed_by_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="col-lg-4">
        <!-- Stock Status Card -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: #<?= $stock_badge === 'success' ? '28a745' : ($stock_badge === 'warning' ? 'ffc107' : 'dc3545') ?>; color: white;">
                <h5 style="margin: 0;">
                    <i class="fas fa-warehouse"></i> Status Stok
                </h5>
            </div>
            <div class="card-body" style="text-align: center; padding: 30px;">
                <div style="font-size: 3rem; font-weight: 700; color: #1E459F; margin-bottom: 10px;">
                    <?= $item['current_stock'] ?>
                </div>
                <div style="font-size: 1.2rem; color: #6c757d; margin-bottom: 20px;">
                    <?= htmlspecialchars($item['unit']) ?>
                </div>
                
                <div class="progress" style="height: 25px; margin-bottom: 15px;">
                    <div class="progress-bar bg-<?= $stock_badge ?>" 
                         role="progressbar" 
                         style="width: <?= $stock_percentage ?>%;"
                         aria-valuenow="<?= $stock_percentage ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?= round($stock_percentage, 1) ?>%
                    </div>
                </div>
                
                <span class="badge badge-<?= $stock_badge ?>" style="font-size: 1rem; padding: 10px 20px;">
                    <i class="fas fa-circle"></i> <?= $stock_text ?>
                </span>
                
                <hr>
                
                <div style="text-align: left;">
                    <div style="margin-bottom: 10px;">
                        <span style="color: #6c757d;">Minimum Stok:</span>
                        <strong style="float: right; color: #CF2A2A;">
                            <?= $item['min_stock'] ?> <?= htmlspecialchars($item['unit']) ?>
                        </strong>
                    </div>
                    <div>
                        <span style="color: #6c757d;">Nilai Total:</span>
                        <strong style="float: right; color: #1E459F;">
                            Rp <?= number_format($item['current_stock'] * $item['unit_price'], 0, ',', '.') ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metadata Card -->
        <div class="card">
            <div class="card-header" style="background: #f8f9fa;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-info-circle"></i> Informasi Sistem
                </h5>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 15px;">
                    <small style="color: #6c757d; display: block; margin-bottom: 5px;">Dibuat Oleh</small>
                    <div style="font-weight: 600;">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($item['created_by_name'] ?? 'System') ?>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <small style="color: #6c757d; display: block; margin-bottom: 5px;">Tanggal Dibuat</small>
                    <div style="font-weight: 600;">
                        <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                    </div>
                </div>
                <div>
                    <small style="color: #6c757d; display: block; margin-bottom: 5px;">Terakhir Diupdate</small>
                    <div style="font-weight: 600;">
                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
