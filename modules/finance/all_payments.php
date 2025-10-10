<?php
$page_title = "Semua Pembayaran";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

// Handle search and filters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';
$filter_method = $_GET['filter_method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build where conditions
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR m.member_code LIKE ? OR p.receipt_number LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if (!empty($filter_status)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_type)) {
    $where_conditions[] = "p.payment_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_method)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $filter_method;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.payment_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.payment_date) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Valid sort columns
$valid_sorts = ['payment_date', 'created_at', 'amount', 'status', 'payment_type'];
if (!in_array($sort_by, $valid_sorts)) $sort_by = 'created_at';
if (!in_array($sort_order, ['ASC', 'DESC'])) $sort_order = 'DESC';

// Get payments with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$payments = $db->fetchAll("
    SELECT p.*, 
           m.member_code, 
           u.full_name, 
           u.email,
           COALESCE(creator.full_name, 'System') as created_by_name,
           DATEDIFF(CURRENT_DATE, p.due_date) as days_overdue
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    LEFT JOIN users creator ON p.created_by = creator.id
    WHERE $where_clause
    ORDER BY p.$sort_by $sort_order
    LIMIT $limit OFFSET $offset
", $params);

// Get total count for pagination
$total_payments = $db->fetch("
    SELECT COUNT(*) as count
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE $where_clause
", $params)['count'];

$total_pages = ceil($total_payments / $limit);

// Get statistics
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END), 0) as total_pending,
        SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) as count_paid,
        SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as count_pending,
        SUM(CASE WHEN p.status = 'overdue' OR (p.status = 'pending' AND p.due_date < CURRENT_DATE) THEN 1 ELSE 0 END) as count_overdue
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE $where_clause
", $params);

// Helper functions
function getStatusBadge($status, $due_date = null) {
    if ($status === 'pending' && $due_date && $due_date < date('Y-m-d')) {
        return ['badge-danger', 'fas fa-exclamation-triangle', 'Overdue'];
    }
    
    $badges = [
        'paid' => ['badge-success', 'fas fa-check-circle', 'Lunas'],
        'pending' => ['badge-warning', 'fas fa-clock', 'Pending'],
        'cancelled' => ['badge-secondary', 'fas fa-times', 'Dibatalkan'],
        'refunded' => ['badge-info', 'fas fa-undo', 'Refund']
    ];
    
    return $badges[$status] ?? ['badge-secondary', 'fas fa-question', ucfirst($status)];
}

function getTypeBadge($type) {
    $badges = [
        'monthly_fee' => ['badge-primary', 'Iuran Bulanan'],
        'registration' => ['badge-success', 'Pendaftaran'],
        'equipment' => ['badge-info', 'Peralatan'],
        'tournament' => ['badge-warning', 'Turnamen'],
        'other' => ['badge-secondary', 'Lainnya']
    ];
    
    return $badges[$type] ?? ['badge-secondary', ucfirst($type)];
}

function getMethodIcon($method) {
    $icons = [
        'cash' => 'fas fa-money-bill',
        'transfer' => 'fas fa-university',
        'e_wallet' => 'fas fa-mobile-alt',
        'credit_card' => 'fas fa-credit-card'
    ];
    
    return $icons[$method] ?? 'fas fa-question-circle';
}
?>

<!-- Custom CSS untuk memastikan button styling -->
<style>
/* Reset dan Base Styles */
.btn {
    display: inline-block;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    vertical-align: middle;
    cursor: pointer;
    border: 2px solid transparent;
    padding: 8px 16px;
    font-size: 14px;
    line-height: 1.5;
    border-radius: 8px;
    transition: all 0.3s ease;
    margin: 2px;
    min-width: 80px;
}

/* Button Colors */
.btn-primary {
    color: #fff;
    background: linear-gradient(135deg, #1E459F, #2056b8);
    border-color: #1E459F;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #1a3d8f, #1c4ca5);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(30, 69, 159, 0.3);
}

.btn-success {
    color: #fff;
    background: linear-gradient(135deg, #28a745, #20c997);
    border-color: #28a745;
}
.btn-success:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.btn-info {
    color: #fff;
    background: linear-gradient(135deg, #17a2b8, #138496);
    border-color: #17a2b8;
}
.btn-info:hover {
    background: linear-gradient(135deg, #138496, #117a8b);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
}

.btn-warning {
    color: #fff;
    background: linear-gradient(135deg, #ffc107, #d39e00);
    border-color: #ffc107;
}
.btn-warning:hover {
    background: linear-gradient(135deg, #e0a800, #c69500);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
}

.btn-secondary {
    color: #fff;
    background: linear-gradient(135deg, #6c757d, #5a6268);
    border-color: #6c757d;
}
.btn-secondary:hover {
    background: linear-gradient(135deg, #5a6268, #4e555b);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
}

/* Button Sizes */
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
    min-width: 60px;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 16px;
    border-radius: 10px;
    min-width: 120px;
}

/* Button Groups */
.btn-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.btn-group {
    display: inline-flex;
    vertical-align: middle;
}

.btn-group .btn {
    margin: 0;
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-radius: 8px 0 0 8px;
}

.btn-group .btn:last-child {
    border-radius: 0 8px 8px 0;
}

.btn-group .btn:only-child {
    border-radius: 8px;
}

/* Dropdown */
.dropdown-toggle::after {
    margin-left: 8px;
    vertical-align: middle;
}

.dropdown-menu {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    padding: 8px 0;
    margin-top: 4px;
}

.dropdown-item {
    display: block;
    width: 100%;
    padding: 10px 20px;
    clear: both;
    font-weight: 400;
    color: #212529;
    text-align: inherit;
    text-decoration: none;
    white-space: nowrap;
    background: transparent;
    border: 0;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: rgba(30, 69, 159, 0.1);
    color: #1E459F;
}

/* Form Controls */
.form-control, .form-select {
    display: block;
    width: 100%;
    padding: 10px 12px;
    font-size: 14px;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus, .form-select:focus {
    color: #495057;
    background-color: #fff;
    border-color: #1E459F;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25);
}

/* Cards */
.card {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.125);
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid rgba(0,0,0,0.125);
    border-radius: 15px 15px 0 0;
}

.card-body {
    padding: 20px;
}

/* Table */
.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
    background-color: #f8f9fa;
    font-weight: 600;
}

.table-hover tbody tr:hover {
    color: #212529;
    background-color: rgba(30, 69, 159, 0.05);
}

/* Badge */
.badge {
    display: inline-block;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 15px;
}

.badge-primary { background-color: #1E459F; }
.badge-success { background-color: #28a745; }
.badge-info { background-color: #17a2b8; }
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-danger { background-color: #dc3545; }
.badge-secondary { background-color: #6c757d; }

/* Responsive */
@media (max-width: 768px) {
    .btn-toolbar {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group {
        width: 100%;
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        margin: 2px 0;
        border-radius: 8px !important;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .table-responsive {
        border: none;
        overflow-x: auto;
    }
}

/* Loading State */
.btn.loading {
    pointer-events: none;
    opacity: 0.65;
    position: relative;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- Header -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0; font-size: 1.8rem; font-weight: 700;">
                    <i class="fas fa-list-alt" style="margin-right: 10px;"></i>
                    Semua Pembayaran
                </h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 1rem;">
                    Kelola dan pantau semua transaksi pembayaran
                </p>
            </div>
            <div class="btn-toolbar">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
                <a href="add_payment.php" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    Tambah
                </a>
                <div class="dropdown">
                    <button class="btn btn-info dropdown-toggle" type="button" onclick="toggleDropdown(this)">
                        <i class="fas fa-download"></i>
                        Export
                    </button>
                    <div class="dropdown-menu" style="display: none;">
                        <a class="dropdown-item" href="export_payments.php?format=excel&<?= http_build_query($_GET) ?>">
                            <i class="fas fa-file-excel" style="color: #28a745; margin-right: 8px;"></i>Excel
                        </a>
                        <a class="dropdown-item" href="export_payments.php?format=pdf&<?= http_build_query($_GET) ?>">
                            <i class="fas fa-file-pdf" style="color: #dc3545; margin-right: 8px;"></i>PDF
                        </a>
                        <a class="dropdown-item" href="export_payments.php?format=csv&<?= http_build_query($_GET) ?>">
                            <i class="fas fa-file-csv" style="color: #1E459F; margin-right: 8px;"></i>CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; border: none;">
        <div class="card-body" style="text-align: center;">
            <i class="fas fa-list" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: 700;">
                <?= number_format($stats['total_payments']) ?>
            </h3>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1rem;">Total Transaksi</p>
        </div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border: none;">
        <div class="card-body" style="text-align: center;">
            <i class="fas fa-check-circle" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
            <h4 style="margin: 0; font-size: 1.5rem; font-weight: 700;">
                <?= formatRupiah($stats['total_paid']) ?>
            </h4>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1rem;">Lunas (<?= $stats['count_paid'] ?>)</p>
        </div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #ffc107, #d39e00); color: white; border: none;">
        <div class="card-body" style="text-align: center;">
            <i class="fas fa-clock" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
            <h4 style="margin: 0; font-size: 1.5rem; font-weight: 700;">
                <?= formatRupiah($stats['total_pending']) ?>
            </h4>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1rem;">Pending (<?= $stats['count_pending'] ?>)</p>
        </div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #dc3545, #a71e2a); color: white; border: none;">
        <div class="card-body" style="text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: 700;">
                <?= $stats['count_overdue'] ?>
            </h3>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1rem;">Overdue</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="background: #f8f9fa;">
        <h5 style="margin: 0; color: #1E459F; font-weight: 600;">
            <i class="fas fa-filter" style="margin-right: 8px;"></i>
            Filter & Pencarian
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" id="filterForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Pencarian Global</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Nama member, kode, atau receipt..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Status</label>
                    <select name="filter_status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>✅ Lunas</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>❌ Dibatalkan</option>
                        <option value="refunded" <?= $filter_status === 'refunded' ? 'selected' : '' ?>>🔄 Refund</option>
                    </select>
                </div>
                
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Tipe</label>
                    <select name="filter_type" class="form-select">
                        <option value="">Semua Tipe</option>
                        <option value="monthly_fee" <?= $filter_type === 'monthly_fee' ? 'selected' : '' ?>>💰 Iuran Bulanan</option>
                        <option value="registration" <?= $filter_type === 'registration' ? 'selected' : '' ?>>📝 Pendaftaran</option>
                        <option value="equipment" <?= $filter_type === 'equipment' ? 'selected' : '' ?>>🥊 Peralatan</option>
                        <option value="tournament" <?= $filter_type === 'tournament' ? 'selected' : '' ?>>🏆 Turnamen</option>
                        <option value="other" <?= $filter_type === 'other' ? 'selected' : '' ?>>📌 Lainnya</option>
                    </select>
                </div>
                
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Metode</label>
                    <select name="filter_method" class="form-select">
                        <option value="">Semua Metode</option>
                        <option value="cash" <?= $filter_method === 'cash' ? 'selected' : '' ?>>💵 Cash</option>
                        <option value="transfer" <?= $filter_method === 'transfer' ? 'selected' : '' ?>>🏦 Transfer</option>
                        <option value="e_wallet" <?= $filter_method === 'e_wallet' ? 'selected' : '' ?>>📱 E-Wallet</option>
                        <option value="credit_card" <?= $filter_method === 'credit_card' ? 'selected' : '' ?>>💳 Kartu Kredit</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 15px; align-items: end;">
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                
                <div>
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Cari
                </button>
                
                <a href="all_payments.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-header" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h4 style="margin: 0; font-weight: 700;">
                    <i class="fas fa-table" style="margin-right: 8px;"></i>
                    Daftar Pembayaran
                </h4>
                <small style="opacity: 0.9;">
                    Menampilkan <?= count($payments) ?> dari <?= number_format($total_payments) ?> total
                </small>
            </div>
            <?php if ($total_pages > 1): ?>
                <small style="opacity: 0.9;">
                    Halaman <?= $page ?> dari <?= $total_pages ?>
                </small>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($payments)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-search" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <h5>Tidak Ada Data Ditemukan</h5>
            <p>Tidak ada pembayaran yang sesuai dengan kriteria pencarian</p>
            <div class="btn-toolbar" style="justify-content: center;">
                <a href="all_payments.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i>
                    Reset Filter
                </a>
                <a href="add_payment.php" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    Tambah Pembayaran
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="font-weight: 600;">
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'payment_date', 'sort_order' => $sort_by === 'payment_date' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" 
                               style="text-decoration: none; color: inherit;">
                                📅 Tanggal
                                <?php if ($sort_by === 'payment_date'): ?>
                                    <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>" style="color: #1E459F;"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="font-weight: 600;">👤 Member</th>
                        <th style="font-weight: 600;">🏷️ Tipe</th>
                        <th style="font-weight: 600;">💳 Metode</th>
                        <th style="font-weight: 600;">
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'amount', 'sort_order' => $sort_by === 'amount' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" 
                               style="text-decoration: none; color: inherit;">
                                💰 Jumlah
                                <?php if ($sort_by === 'amount'): ?>
                                    <i class="fas fa-sort-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>" style="color: #1E459F;"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="font-weight: 600;">📊 Status</th>
                        <th style="font-weight: 600;">🧾 Receipt</th>
                        <th style="font-weight: 600; text-align: center;">⚙️ Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $status_badge = getStatusBadge($payment['status'], $payment['due_date']);
                        $type_badge = getTypeBadge($payment['payment_type']);
                        $method_icon = getMethodIcon($payment['payment_method']);
                        $is_overdue = $payment['status'] === 'pending' && $payment['due_date'] < date('Y-m-d');
                        ?>
                        <tr style="<?= $is_overdue ? 'background-color: rgba(220, 53, 69, 0.05);' : '' ?>">
                            <td>
                                <div>
                                    <div style="font-weight: 600;">
                                        <?= formatDate($payment['payment_date']) ?>
                                    </div>
                                    <?php if ($payment['due_date']): ?>
                                        <small style="color: #6c757d;">
                                            Due: <?= formatDate($payment['due_date']) ?>
                                            <?php if ($payment['days_overdue'] > 0): ?>
                                                <span style="color: #dc3545; font-weight: 600;">
                                                    (+<?= $payment['days_overdue'] ?> hari)
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div style="font-weight: 600; color: #1E459F;">
                                        <?= htmlspecialchars($payment['full_name']) ?>
                                    </div>
                                    <small style="color: #6c757d;">
                                        <i class="fas fa-id-badge"></i>
                                        <?= $payment['member_code'] ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $type_badge[0] ?>">
                                    <?= $type_badge[1] ?>
                                </span>
                            </td>
                            <td>
                                <i class="<?= $method_icon ?>" style="margin-right: 8px; color: #6c757d;"></i>
                                <?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #28a745; font-size: 1.1rem;">
                                    <?= formatRupiah($payment['amount']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $status_badge[0] ?>">
                                    <i class="<?= $status_badge[1] ?>"></i>
                                    <?= $status_badge[2] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($payment['receipt_number']): ?>
                                    <code style="background: #f8f9fa; color: #495057; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                                        <?= $payment['receipt_number'] ?>
                                    </code>
                                <?php else: ?>
                                    <small style="color: #6c757d;">-</small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div class="btn-toolbar" style="justify-content: center;">
                                    <a href="view_payment.php?id=<?= $payment['id'] ?>" class="btn btn-info btn-sm" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (getUserRole() === 'admin'): ?>
                                        <a href="edit_payment.php?id=<?= $payment['id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="print_receipt.php?id=<?= $payment['id'] ?>" class="btn btn-success btn-sm" title="Print" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php if ($payment['status'] === 'pending' && getUserRole() === 'admin'): ?>
                                        <button onclick="markAsPaid(<?= $payment['id'] ?>)" class="btn btn-primary btn-sm" title="Tandai Lunas">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div style="color: #6c757d;">
                    Menampilkan <?= ($page - 1) * $limit + 1 ?> - <?= min($page * $limit, $total_payments) ?> dari <?= number_format($total_payments) ?> total
                </div>
                
                <div class="btn-toolbar">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
function toggleDropdown(button) {
    const dropdown = button.nextElementSibling;
    const isVisible = dropdown.style.display === 'block';
    
    // Hide all dropdowns first
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
    });
    
    // Toggle current dropdown
    dropdown.style.display = isVisible ? 'none' : 'block';
    
    // Position dropdown
    const rect = button.getBoundingClientRect();
    dropdown.style.position = 'absolute';
    dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.zIndex = '1000';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});

function markAsPaid(paymentId) {
    if (confirm('✅ Konfirmasi: Tandai pembayaran ini sebagai LUNAS?')) {
        const btn = event.target.closest('button');
        btn.classList.add('loading');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner"></i>';
        
        fetch('mark_as_paid.php?id=' + paymentId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Pembayaran berhasil ditandai sebagai lunas!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                }
            })
            .catch(error => {
                showNotification('Error: ' + error.message, 'error');
                btn.classList.remove('loading');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i>';
            });
    }
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        border-radius: 8px;
        z-index: 9999;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        font-weight: 600;
        max-width: 400px;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Auto-submit filters with debounce
document.addEventListener('DOMContentLoaded', function() {
    const filterElements = document.querySelectorAll('select[name^="filter_"]');
    
    filterElements.forEach(element => {
        element.addEventListener('change', function() {
            const form = document.getElementById('filterForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            setTimeout(() => {
                form.submit();
            }, 500);
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>