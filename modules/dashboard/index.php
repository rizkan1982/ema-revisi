<?php
require_once '../../config/config.php';
requireLogin();

$page_title = "Dashboard";
require_once '../../includes/header.php';

$user_role = getUserRole();

// Get comprehensive statistics based on role
$stats = [];

// Super Admin & Admin - Full Stats
if (in_array($user_role, ['super_admin', 'admin'])) {
    // Users Stats
    $stats['total_users'] = $db->fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'] ?? 0;
    $stats['super_admins'] = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin' AND is_active = 1")['count'] ?? 0;
    $stats['admins'] = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1")['count'] ?? 0;
    $stats['staff_users'] = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND is_active = 1")['count'] ?? 0;
    $stats['members'] = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'member' AND is_active = 1")['count'] ?? 0;
    
    // Inventory Stats
    $stats['total_items'] = $db->fetch("SELECT COUNT(*) as count FROM inventory_items WHERE is_active = 1")['count'] ?? 0;
    $stats['low_stock_items'] = $db->fetch("
        SELECT COUNT(*) as count FROM inventory_items 
        WHERE current_stock <= min_stock AND is_active = 1
    ")['count'] ?? 0;
    $stats['out_of_stock'] = $db->fetch("
        SELECT COUNT(*) as count FROM inventory_items 
        WHERE current_stock = 0 AND is_active = 1
    ")['count'] ?? 0;
    $total_value = $db->fetch("
        SELECT SUM(current_stock * (unit_price)) as total 
        FROM inventory_items WHERE is_active = 1
    ")['total'] ?? 0;
    $stats['total_inventory_value'] = $total_value;
    
    // Requests Stats
    $stats['pending_requests'] = $db->fetch("SELECT COUNT(*) as count FROM inventory_requests WHERE status = 'pending'")['count'] ?? 0;
    $stats['approved_requests'] = $db->fetch("SELECT COUNT(*) as count FROM inventory_requests WHERE status = 'approved'")['count'] ?? 0;
    $stats['rejected_requests'] = $db->fetch("SELECT COUNT(*) as count FROM inventory_requests WHERE status = 'rejected'")['count'] ?? 0;
    $stats['total_requests'] = $db->fetch("SELECT COUNT(*) as count FROM inventory_requests")['count'] ?? 0;
    
    // Recent Activities
    $recent_activities = $db->fetchAll("
        SELECT a.*, u.full_name, u.role
        FROM user_activity_logs a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    
    // Pending Requests (detail)
    $pending_requests_detail = $db->fetchAll("
        SELECT sr.*, i.item_name, u.full_name, u.role
        FROM inventory_requests sr
        JOIN inventory_items i ON sr.item_id = i.id
        JOIN users u ON sr.requested_by = u.id
        WHERE sr.status = 'pending'
        ORDER BY 
            CASE sr.priority 
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            sr.requested_at DESC
        LIMIT 5
    ");
    
    // Low Stock Items
    $low_stock_items = $db->fetchAll("
        SELECT * FROM inventory_items
        WHERE current_stock <= min_stock AND is_active = 1
        ORDER BY (current_stock / NULLIF(min_stock, 0)) ASC
        LIMIT 5
    ");
    
    // Additional stats for admin dashboard
    $stats['total_members'] = $stats['members']; // Alias
    $stats['active_trainers'] = $db->fetch("SELECT COUNT(*) as count FROM trainers t JOIN users u ON t.user_id = u.id WHERE u.is_active = 1")['count'] ?? 0;
    
    // Financial stats (if payments table exists)
    $stats['monthly_income'] = $db->fetch("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM payments 
        WHERE MONTH(payment_date) = MONTH(CURDATE()) 
        AND YEAR(payment_date) = YEAR(CURDATE())
        AND status = 'paid'
    ")['total'] ?? 0;
    
    $stats['pending_payments'] = $db->fetch("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE status = 'pending'
    ")['count'] ?? 0;
    
// Staff & Member - Limited Stats
} elseif (in_array($user_role, ['staff', 'member'])) {
    // Own Requests
    $stats['my_pending'] = $db->fetch("
        SELECT COUNT(*) as count FROM inventory_requests 
        WHERE requested_by = ? AND status = 'pending'
    ", [$_SESSION['user_id']])['count'] ?? 0;
    
    $stats['my_approved'] = $db->fetch("
        SELECT COUNT(*) as count FROM inventory_requests 
        WHERE requested_by = ? AND status = 'approved'
    ", [$_SESSION['user_id']])['count'] ?? 0;
    
    $stats['my_rejected'] = $db->fetch("
        SELECT COUNT(*) as count FROM inventory_requests 
        WHERE requested_by = ? AND status = 'rejected'
    ", [$_SESSION['user_id']])['count'] ?? 0;
    
    $stats['total_requests'] = $db->fetch("
        SELECT COUNT(*) as count FROM inventory_requests 
        WHERE requested_by = ?
    ", [$_SESSION['user_id']])['count'] ?? 0;
    
    // My Recent Requests
    $my_requests = $db->fetchAll("
        SELECT sr.*, i.item_name, i.unit
        FROM inventory_requests sr
        JOIN inventory_items i ON sr.item_id = i.id
        WHERE sr.requested_by = ?
        ORDER BY sr.requested_at DESC
        LIMIT 10
    ", [$_SESSION['user_id']]);
    
    // Available Items
    $stats['available_items'] = $db->fetch("
        SELECT COUNT(*) as count FROM inventory_items 
        WHERE current_stock > 0 AND is_active = 1
    ")['count'] ?? 0;
}
?>

<!-- Welcome Header -->
<div style="margin-bottom: 30px;">
    <h1 style="color: #1E459F; margin: 0;">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </h1>
    <p style="color: #6c757d; margin: 5px 0 0 0;">
        Selamat datang, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>! 
        <?php
        $hour = date('H');
        if ($hour < 12) echo 'Selamat pagi';
        elseif ($hour < 17) echo 'Selamat siang';
        else echo 'Selamat malam';
        ?> dan selamat beraktivitas.
    </p>
</div>

<!-- Super Admin & Admin Dashboard -->
<?php if (in_array($user_role, ['super_admin', 'admin'])): ?>

<!-- User Statistics -->
<h4 style="color: #1E459F; margin: 30px 0 15px 0;">
    <i class="fas fa-users"></i> Statistik Pengguna
</h4>
<div class="row" style="margin-bottom: 30px;">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['super_admins'] + $stats['admins']) ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['staff_users']) ?></div>
                <div class="stat-label">Staff/Trainer</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['members']) ?></div>
                <div class="stat-label">Members</div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Statistics -->
<h4 style="color: #CF2A2A; margin: 30px 0 15px 0;">
    <i class="fas fa-boxes"></i> Statistik Inventaris
</h4>
<div class="row" style="margin-bottom: 30px;">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['total_items']) ?></div>
                <div class="stat-label">Total Item</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-details">
                <div class="stat-value" style="font-size: 20px;">Rp <?= number_format($stats['total_inventory_value'], 0, ',', '.') ?></div>
                <div class="stat-label">Total Nilai</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['low_stock_items']) ?></div>
                <div class="stat-label">Stock Rendah</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['out_of_stock']) ?></div>
                <div class="stat-label">Habis</div>
            </div>
        </div>
    </div>
</div>

<!-- Request Statistics -->
<h4 style="color: #FABD32; margin: 30px 0 15px 0;">
    <i class="fas fa-clipboard-list"></i> Statistik Request
</h4>
<div class="row" style="margin-bottom: 30px;">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['total_requests']) ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['pending_requests']) ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['approved_requests']) ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['rejected_requests']) ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Requests Table -->
<?php if (!empty($pending_requests_detail)): ?>
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px; border-bottom: 1px solid #e9ecef;">
        <h5 style="margin: 0; color: #1E459F;">
            <i class="fas fa-hourglass-half"></i> Request Pending yang Perlu Diproses
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Pemohon</th>
                    <th>Jumlah</th>
                    <th>Urgency</th>
                    <th>Tanggal</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_requests_detail as $req): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($req['item_name']) ?></strong></td>
                    <td>
                        <?= htmlspecialchars($req['full_name']) ?>
                        <br><small class="text-muted"><?= ucfirst($req['role']) ?></small>
                    </td>
                    <td><span class="badge badge-info"><?= number_format($req['requested_quantity'] ?? 0) ?></span></td>
                    <td>
                        <?php
                        $urgency_colors = [
                            'urgent' => 'danger',
                            'high' => 'warning',
                            'medium' => 'info',
                            'low' => 'secondary'
                        ];
                        $color = $urgency_colors[$req['priority'] ?? 'low'] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?= $color ?>">
                            <?= ucfirst($req['priority'] ?? 'N/A') ?>
                        </span>
                    </td>
                    <td><?= date('d M Y H:i', strtotime($req['requested_at'] ?? $req['created_at'] ?? 'now')) ?></td>
                    <td>
                        <a href="../inventory/process_request.php?id=<?= $req['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-tasks"></i> Proses
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding: 15px; border-top: 1px solid #e9ecef; text-align: center;">
        <a href="../inventory/requests.php" class="btn btn-outline-primary">
            <i class="fas fa-list"></i> Lihat Semua Request
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Low Stock Alert -->
<?php if (!empty($low_stock_items)): ?>
<div class="card" style="margin-bottom: 30px; border-left: 4px solid #CF2A2A;">
    <div style="padding: 20px; border-bottom: 1px solid #e9ecef;">
        <h5 style="margin: 0; color: #CF2A2A;">
            <i class="fas fa-exclamation-triangle"></i> Peringatan Stock Rendah
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Stock Saat Ini</th>
                    <th>Min Stock</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_items as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                    <td>
                        <span class="badge badge-danger">
                            <?= number_format($item['current_stock']) ?> <?= htmlspecialchars($item['unit']) ?>
                        </span>
                    </td>
                    <td><?= number_format($item['min_stock']) ?> <?= htmlspecialchars($item['unit']) ?></td>
                    <td>
                        <?php if ($item['current_stock'] == 0): ?>
                            <span class="badge badge-danger">HABIS</span>
                        <?php else: ?>
                            <span class="badge badge-warning">RENDAH</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="../inventory/adjust_stock.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-plus"></i> Tambah Stock
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding: 15px; border-top: 1px solid #e9ecef; text-align: center;">
        <a href="../inventory/index.php?filter=low_stock" class="btn btn-outline-danger">
            <i class="fas fa-box"></i> Lihat Semua Item Stock Rendah
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activities -->
<?php if (!empty($recent_activities)): ?>
<div class="card">
    <div style="padding: 20px; border-bottom: 1px solid #e9ecef;">
        <h5 style="margin: 0; color: #1E459F;">
            <i class="fas fa-history"></i> Aktivitas Terbaru
        </h5>
    </div>
    <div style="padding: 20px;">
        <div class="timeline">
            <?php foreach ($recent_activities as $activity): ?>
            <div class="timeline-item">
                <div class="timeline-icon bg-primary">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="timeline-content">
                    <div style="font-weight: 600; color: #1E459F;">
                        <?= htmlspecialchars($activity['full_name']) ?>
                        <span class="badge badge-secondary"><?= ucfirst($activity['role']) ?></span>
                    </div>
                    <div style="color: #6c757d; font-size: 0.9rem;">
                        <?= htmlspecialchars($activity['action_type'] ?? 'Activity') ?> - <?= htmlspecialchars($activity['table_name'] ?? '') ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #adb5bd; margin-top: 5px;">
                        <i class="fas fa-clock"></i> <?= date('d M Y H:i', strtotime($activity['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Staff & Member Dashboard -->
<?php elseif (in_array($user_role, ['staff', 'member'])): ?>

<h4 style="color: #1E459F; margin: 30px 0 15px 0;">
    <i class="fas fa-clipboard-list"></i> Request Saya
</h4>
<div class="row" style="margin-bottom: 30px;">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['total_requests']) ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['my_pending']) ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['my_approved']) ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['my_rejected']) ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-md-6">
        <a href="../inventory/index.php" class="card" style="text-decoration: none; display: block; padding: 30px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px;">
            <div style="font-size: 48px; margin-bottom: 15px;">
                <i class="fas fa-box"></i>
            </div>
            <h4 style="margin: 0;">Lihat Inventaris</h4>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">
                <?= number_format($stats['available_items']) ?> item tersedia
            </p>
        </a>
    </div>
    <div class="col-md-6">
        <a href="../inventory/create_request.php" class="card" style="text-decoration: none; display: block; padding: 30px; text-align: center; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-radius: 12px;">
            <div style="font-size: 48px; margin-bottom: 15px;">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h4 style="margin: 0;">Buat Request Baru</h4>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Request item yang Anda butuhkan</p>
        </a>
    </div>
</div>

<!-- My Recent Requests -->
<?php if (!empty($my_requests)): ?>
<div class="card">
    <div style="padding: 20px; border-bottom: 1px solid #e9ecef;">
        <h5 style="margin: 0; color: #1E459F;">
            <i class="fas fa-history"></i> Request Terbaru Saya
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Urgency</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_requests as $req): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($req['item_name']) ?></strong></td>
                    <td><?= number_format($req['requested_quantity'] ?? 0) ?> <?= htmlspecialchars($req['unit']) ?></td>
                    <td>
                        <?php
                        $status_colors = [
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger'
                        ];
                        $color = $status_colors[$req['status']] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?= $color ?>">
                            <?= ucfirst($req['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $urgency_colors = [
                            'urgent' => 'danger',
                            'high' => 'warning',
                            'medium' => 'info',
                            'low' => 'secondary'
                        ];
                        $color = $urgency_colors[$req['priority'] ?? 'low'] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?= $color ?>">
                            <?= ucfirst($req['priority'] ?? 'N/A') ?>
                        </span>
                    </td>
                    <td><?= date('d M Y H:i', strtotime($req['requested_at'] ?? $req['created_at'] ?? 'now')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
.stat-card {
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 36px;
    opacity: 0.9;
}

.stat-details {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-icon {
    position: absolute;
    left: -30px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: white;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -21px;
    top: 20px;
    width: 2px;
    height: calc(100% - 20px);
    background: #e9ecef;
}

.timeline-item:last-child:before {
    display: none;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.bg-primary {
    background-color: #1E459F;
}
</style>

<!-- PWA Install Notification dengan Deteksi Platform -->
<div id="pwa-install-banner" style="display: none; background: linear-gradient(45deg, #1E459F, #CF2A2A); color: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.2);">
    <div class="install-banner-content">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="font-size: 3rem;">
                <i class="fas fa-mobile-alt" id="install-icon"></i>
            </div>
            <div>
                <h4 style="margin: 0; color: #FABD32;">
                    <i class="fas fa-star"></i>
                    <span id="install-title">Install EMA Camp App</span>
                </h4>
                <p style="margin: 5px 0 0 0; opacity: 0.9;" id="install-instruction">
                    Akses lebih cepat dengan shortcut di home screen! Bisa digunakan offline dan mendapat notifikasi real-time.
                </p>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button id="pwa-install-button" class="btn btn-lg install-btn">
                <i class="fas fa-download"></i>
                <span id="install-text">Install Now</span>
            </button>
            <button id="pwa-dismiss" class="btn btn-lg dismiss-btn">
                <i class="fas fa-times"></i>
                Nanti
            </button>
        </div>
        
        <!-- Platform Info -->
        <div id="platform-info" style="margin-top: 15px; font-size: 0.85rem; opacity: 0.8; display: none;">
            <i class="fas fa-info-circle"></i>
            <span id="platform-message"></span>
        </div>
    </div>
</div>

<!-- Welcome Message -->
<div class="alert alert-info welcome-message">
    <h4 style="margin: 0; color: #1E459F;">
        <i class="fas fa-hand-paper"></i>
        Selamat datang, <?= $_SESSION['user_name'] ?>!
    </h4>
    <p style="margin: 5px 0 0 0;">
        <?php
        $hour = date('H');
        if ($hour < 12) echo 'Selamat pagi';
        elseif ($hour < 17) echo 'Selamat siang';
        else echo 'Selamat malam';
        ?> dan selamat beraktivitas di EMA Camp Management System.
        
        <!-- Online Status -->
        <span id="online-status" class="badge badge-success status-badge">
            <i class="fas fa-wifi"></i>
            Online
        </span>
        
        <!-- HTTPS Status for PWA -->
        <span id="https-status" class="badge status-badge" style="margin-left: 10px;">
            <i class="fas fa-lock"></i>
            <span id="https-text">Checking SSL...</span>
        </span>
    </p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <?php if (getUserRole() === 'admin'): ?>
        <div class="stat-card blue">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['total_members'] ?></h3>
                    <p>Total Anggota</p>
                </div>
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['active_trainers'] ?></h3>
                    <p>Pelatih Aktif</p>
                </div>
                <div class="stat-icon red">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card yellow">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= formatRupiah($stats['monthly_income']) ?></h3>
                    <p>Pendapatan Bulan Ini</p>
                </div>
                <div class="stat-icon yellow">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card cream">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['pending_payments'] ?></h3>
                    <p>Pembayaran Tertunda</p>
                </div>
                <div class="stat-icon cream">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="card quick-actions-card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h3>
        
        <div class="app-controls">
            <button id="fullscreen-btn" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-expand"></i>
                <span class="btn-text">Fullscreen</span>
            </button>
            <button id="offline-mode-btn" class="btn btn-outline-info btn-sm">
                <i class="fas fa-wifi"></i>
                <span id="offline-text">Online Mode</span>
            </button>
        </div>
    </div>
    
    <div class="quick-actions-content">
        <div class="actions-grid">
            <?php if (getUserRole() === 'admin'): ?>
                <a href="<?= BASE_URL ?>modules/members/add.php" class="btn btn-primary quick-action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Tambah Member Baru</span>
                </a>
                <a href="<?= BASE_URL ?>modules/finance/add_payment.php" class="btn btn-success quick-action-btn">
                    <i class="fas fa-money-bill"></i>
                    <span>Catat Pembayaran</span>
                </a>
                <a href="<?= BASE_URL ?>modules/schedule/add_event.php" class="btn btn-warning quick-action-btn">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Tambah Event</span>
                </a>
                <a href="<?= BASE_URL ?>modules/reports/" class="btn btn-info quick-action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <span>Lihat Laporan</span>
                </a>
            <?php endif; ?>
            
            <a href="<?= BASE_URL ?>modules/schedule/" class="btn btn-primary quick-action-btn">
                <i class="fas fa-calendar"></i>
                <span>Lihat Jadwal</span>
            </a>
            <a href="<?= BASE_URL ?>modules/notifications/" class="btn btn-secondary quick-action-btn">
                <i class="fas fa-bell"></i>
                <span>Notifikasi</span>
            </a>
        </div>
    </div>
</div>

<!-- Desktop PWA Info Modal -->
<div id="desktop-pwa-modal" class="install-modal" style="display: none;">
    <div class="install-modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-desktop"></i>
                Install Desktop App
            </h3>
            <button class="close-modal" onclick="closeDesktopModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="desktop-requirements">
                <h5><i class="fas fa-check-circle text-success"></i> Requirements for Desktop PWA:</h5>
                
                <div class="requirement-list">
                    <div class="requirement-item">
                        <i class="fas fa-lock"></i>
                        <span><strong>HTTPS Required:</strong> Website harus menggunakan SSL certificate</span>
                    </div>
                    
                    <div class="requirement-item">
                        <i class="fab fa-chrome"></i>
                        <span><strong>Browser Support:</strong> Chrome, Edge, atau Opera (Firefox tidak support)</span>
                    </div>
                    
                    <div class="requirement-item">
                        <i class="fas fa-server"></i>
                        <span><strong>Service Worker:</strong> Harus aktif dan registered</span>
                    </div>
                </div>
                
                <div class="current-status">
                    <h6>Status Saat Ini:</h6>
                    <div id="current-protocol" class="status-item">
                        <i class="fas fa-globe"></i>
                        Protocol: <span id="protocol-status"></span>
                    </div>
                    <div id="current-browser" class="status-item">
                        <i class="fas fa-browser"></i>
                        Browser: <span id="browser-status"></span>
                    </div>
                    <div id="current-sw" class="status-item">
                        <i class="fas fa-cog"></i>
                        Service Worker: <span id="sw-status"></span>
                    </div>
                </div>
                
                <div class="install-tip">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Tip:</strong> Jika requirements terpenuhi, tombol install akan muncul otomatis di address bar browser atau di menu "Install App".
                </div>
            </div>
            
            <button onclick="closeDesktopModal()" class="btn btn-primary modal-btn">
                <i class="fas fa-check"></i>
                Mengerti
            </button>
        </div>
    </div>
</div>

<!-- iOS Install Instructions Modal -->
<div id="ios-install-modal" class="install-modal" style="display: none;">
    <div class="install-modal-content">
        <div class="modal-header">
            <h3>
                <i class="fab fa-apple"></i>
                Install di iPhone/iPad
            </h3>
            <button class="close-modal" onclick="closeIOSModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="install-steps">
                <div class="install-step">
                    <span class="step-number">1</span>
                    <i class="fas fa-share step-icon"></i>
                    <span class="step-text">Tap tombol <strong>Share</strong> di Safari</span>
                </div>
                
                <div class="install-step">
                    <span class="step-number">2</span>
                    <i class="fas fa-plus-square step-icon"></i>
                    <span class="step-text">Pilih <strong>"Add to Home Screen"</strong></span>
                </div>
                
                <div class="install-step">
                    <span class="step-number">3</span>
                    <i class="fas fa-check step-icon"></i>
                    <span class="step-text">Tap <strong>"Add"</strong> untuk install</span>
                </div>
            </div>
            
            <div class="install-info">
                <i class="fas fa-info-circle"></i>
                Setelah diinstall, aplikasi akan muncul di home screen dan bisa digunakan seperti aplikasi native!
            </div>
            
            <button onclick="closeIOSModal()" class="btn btn-primary modal-btn">
                <i class="fas fa-check"></i>
                Mengerti
            </button>
        </div>
    </div>
</div>

<!-- Enhanced PWA JavaScript with Desktop Detection -->
<script>
console.log('🚀 Enhanced PWA Script Loading...');

// Comprehensive Device and Browser Detection
const userAgent = navigator.userAgent;
const isIOS = /iPad|iPhone|iPod/.test(userAgent);
const isSafari = /^((?!chrome|android).)*safari/i.test(userAgent);
const isInStandaloneMode = () => ('standalone' in window.navigator) && (window.navigator.standalone);
const isAndroid = /Android/.test(userAgent);
const isChrome = /Chrome/.test(userAgent) && !/Edge/.test(userAgent);
const isEdge = /Edge/.test(userAgent) || /Edg/.test(userAgent);
const isFirefox = /Firefox/.test(userAgent);
const isOpera = /Opera|OPR/.test(userAgent);
const isDesktop = !(/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent));
const isHTTPS = location.protocol === 'https:' || location.hostname === 'localhost';

console.log('📱 Device Detection:', { 
    isIOS, isSafari, isAndroid, isChrome, isEdge, isFirefox, isOpera, 
    isDesktop, isHTTPS, isStandalone: isInStandaloneMode() 
});

// PWA Support Detection
const supportsPWA = isChrome || isEdge || isOpera || (isSafari && isIOS);
const supportsDesktopPWA = isDesktop && (isChrome || isEdge || isOpera) && isHTTPS;

console.log('🔧 PWA Support:', { supportsPWA, supportsDesktopPWA });

// Get dynamic paths
const getCurrentBasePath = () => {
    const path = window.location.pathname;
    const modulesIndex = path.indexOf('/modules');
    return modulesIndex !== -1 ? path.substring(0, modulesIndex) : path.substring(0, path.lastIndexOf('/'));
};

const basePath = getCurrentBasePath();
const swPath = basePath + '/sw.js';

console.log('🛠️ Paths:', { basePath, swPath });

// PWA Elements
let deferredPrompt;
const installBtn = document.getElementById('pwa-install-button');
const installBanner = document.getElementById('pwa-install-banner');
const dismissBtn = document.getElementById('pwa-dismiss');
const installText = document.getElementById('install-text');
const installInstruction = document.getElementById('install-instruction');
const installTitle = document.getElementById('install-title');
const installIcon = document.getElementById('install-icon');
const platformInfo = document.getElementById('platform-info');
const platformMessage = document.getElementById('platform-message');

// HTTPS Status Update
function updateHTTPSStatus() {
    const httpsStatus = document.getElementById('https-status');
    const httpsText = document.getElementById('https-text');
    
    if (isHTTPS) {
        httpsStatus.className = 'badge badge-success status-badge';
        httpsText.textContent = 'Secure (HTTPS)';
    } else {
        httpsStatus.className = 'badge badge-warning status-badge';
        httpsText.textContent = 'Not Secure (HTTP)';
    }
}

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        console.log('📋 Registering Service Worker:', swPath);
        
        navigator.serviceWorker.register(swPath, {
            scope: basePath + '/'
        })
            .then(function(registration) {
                console.log('✅ SW registered successfully', registration.scope);
                updateSWStatus('Active');
                
                registration.addEventListener('updatefound', () => {
                    console.log('🔄 SW Update found');
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateNotification();
                        }
                    });
                });
            })
            .catch(function(error) {
                console.error('❌ SW registration failed:', error);
                updateSWStatus('Failed');
            });
    });
} else {
    console.warn('⚠️ Service Worker not supported');
    updateSWStatus('Not Supported');
}

function updateSWStatus(status) {
    const swStatus = document.getElementById('sw-status');
    if (swStatus) swStatus.textContent = status;
}

// Enhanced PWA Install Banner Logic
function showInstallBanner() {
    console.log('🎯 Checking install banner conditions...');
    
    if (localStorage.getItem('pwa-dismissed-v4')) {
        console.log('📝 Banner dismissed before');
        return;
    }
    
    if (isInStandaloneMode()) {
        console.log('📱 Already in standalone mode');
        return;
    }
    
    if (!supportsPWA) {
        console.log('❌ PWA not supported on this platform');
        return;
    }
    
    setTimeout(() => {
        console.log('🎪 Showing install banner');
        
        if (isDesktop) {
            // Desktop PWA
            installIcon.className = 'fas fa-desktop';
            installTitle.textContent = 'Install Desktop App';
            installInstruction.textContent = 'Install aplikasi di komputer untuk akses yang lebih cepat dan mudah.';
            
            if (!supportsDesktopPWA) {
                platformInfo.style.display = 'block';
                if (!isHTTPS) {
                    platformMessage.textContent = 'Perlu HTTPS untuk install di desktop. Coba di server yang sudah SSL.';
                } else if (!isChrome && !isEdge && !isOpera) {
                    platformMessage.textContent = 'Gunakan Chrome, Edge, atau Opera untuk install di desktop.';
                }
            }
            
        } else if (isIOS && isSafari) {
            // iOS Safari
            installIcon.className = 'fab fa-apple';
            installTitle.textContent = 'Install di iPhone/iPad';
            installText.innerHTML = '<i class="fas fa-info-circle"></i> How to Install';
            installInstruction.innerHTML = 'Tap <i class="fas fa-share"></i> Share button, then "Add to Home Screen".';
            
        } else if (isAndroid) {
            // Android
            installIcon.className = 'fab fa-android';
            installTitle.textContent = 'Install Android App';
            installInstruction.textContent = 'Install aplikasi untuk pengalaman yang lebih baik dan notifikasi real-time.';
        }
        
        installBanner.style.display = 'block';
        
    }, 2000);
}

// Desktop PWA Modal Functions
function showDesktopPWAInfo() {
    console.log('🖥️ Showing desktop PWA info');
    const modal = document.getElementById('desktop-pwa-modal');
    
    // Update status
    document.getElementById('protocol-status').textContent = isHTTPS ? 'HTTPS ✅' : 'HTTP ❌';
    document.getElementById('browser-status').textContent = getBrowserName();
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDesktopModal() {
    document.getElementById('desktop-pwa-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function getBrowserName() {
    if (isChrome) return 'Chrome ✅';
    if (isEdge) return 'Edge ✅';  
    if (isOpera) return 'Opera ✅';
    if (isFirefox) return 'Firefox ❌';
    if (isSafari) return 'Safari ❌';
    return 'Unknown';
}

// Enhanced beforeinstallprompt handler
window.addEventListener('beforeinstallprompt', (e) => {
    console.log('🎊 beforeinstallprompt fired');
    e.preventDefault();
    deferredPrompt = e;
    
    installBanner.style.display = 'block';
    
    const handleInstall = async () => {
        console.log('⬇️ Install clicked');
        if (deferredPrompt) {
            try {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                
                console.log('👤 User choice:', outcome);
                
                if (outcome === 'accepted') {
                    console.log('✅ PWA install accepted');
                    installBanner.style.display = 'none';
                    showNotification('🎉 Aplikasi berhasil diinstall!', 'success');
                    localStorage.setItem('pwa-installed', 'true');
                } else {
                    console.log('❌ PWA install dismissed');
                }
                
                deferredPrompt = null;
            } catch (error) {
                console.error('💥 Install error:', error);
                showNotification('Gagal menginstall aplikasi', 'error');
            }
        }
    };
    
    // Event listener for install button
    if (installBtn) {
        const newInstallBtn = installBtn.cloneNode(true);
        installBtn.parentNode.replaceChild(newInstallBtn, installBtn);
        
        newInstallBtn.addEventListener('click', (e) => {
            e.preventDefault();
            
            if (isDesktop && !supportsDesktopPWA) {
                showDesktopPWAInfo();
            } else if (isIOS && isSafari) {
                showIOSInstructions();
            } else {
                handleInstall();
            }
        });
    }
});

// App Installation Success
window.addEventListener('appinstalled', (evt) => {
    console.log('🎉 PWA installed successfully');
    installBanner.style.display = 'none';
    showNotification('🚀 EMA Camp siap digunakan!', 'success');
    localStorage.setItem('pwa-installed', 'true');
});

// iOS Installation Instructions
function showIOSInstructions() {
    console.log('🍎 Showing iOS instructions');
    document.getElementById('ios-install-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeIOSModal() {
    document.getElementById('ios-install-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Dismiss Banner
if (dismissBtn) {
    dismissBtn.addEventListener('click', () => {
        console.log('❌ Banner dismissed');
        installBanner.style.display = 'none';
        localStorage.setItem('pwa-dismissed-v4', 'true');
        showNotification('💡 Anda bisa install aplikasi kapan saja dari menu browser', 'info');
    });
}

// Fullscreen Toggle
const fullscreenBtn = document.getElementById('fullscreen-btn');
if (fullscreenBtn) {
    fullscreenBtn.addEventListener('click', function() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
            this.innerHTML = '<i class="fas fa-expand"></i><span class="btn-text">Fullscreen</span>';
        } else {
            document.documentElement.requestFullscreen().catch(err => {
                console.warn('Fullscreen failed:', err);
            });
            this.innerHTML = '<i class="fas fa-compress"></i><span class="btn-text">Exit Fullscreen</span>';
        }
    });
}

// Online/Offline Status
function updateOnlineStatus() {
    const status = document.getElementById('online-status');
    const offlineBtn = document.getElementById('offline-mode-btn');
    const offlineText = document.getElementById('offline-text');
    
    if (navigator.onLine) {
        if (status) {
            status.className = 'badge badge-success status-badge';
            status.innerHTML = '<i class="fas fa-wifi"></i> Online';
        }
        if (offlineText) offlineText.textContent = 'Online Mode';
        if (offlineBtn) offlineBtn.className = 'btn btn-outline-success btn-sm';
    } else {
        if (status) {
            status.className = 'badge badge-warning status-badge';
            status.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline';
        }
        if (offlineText) offlineText.textContent = 'Offline Mode';
        if (offlineBtn) offlineBtn.className = 'btn btn-outline-warning btn-sm';
    }
}

// Network Events
window.addEventListener('online', () => {
    console.log('📶 Back online');
    updateOnlineStatus();
    showNotification('Koneksi internet kembali normal', 'success', 3000);
});

window.addEventListener('offline', () => {
    console.log('📵 Gone offline');
    updateOnlineStatus();
    showNotification('Anda sedang offline. Beberapa fitur mungkin terbatas.', 'warning', 5000);
});

// Enhanced Notification System
function showNotification(message, type = 'info', duration = 4000) {
    console.log('🔔 Showing notification:', message, type);
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle', 
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="notification-close">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    requestAnimationFrame(() => {
        notification.classList.add('show');
    });
    
    if (duration > 0) {
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, duration);
    }
}

// Update Notification
function showUpdateNotification() {
    console.log('🔄 Showing update notification');
    const updateBanner = document.createElement('div');
    updateBanner.className = 'update-banner';
    updateBanner.innerHTML = `
        <div class="update-content">
            <div class="update-info">
                <h6><i class="fas fa-download"></i> Update Tersedia</h6>
                <p>Versi baru EMA Camp tersedia dengan fitur dan perbaikan terbaru.</p>
            </div>
            <div class="update-actions">
                <button onclick="updateApp()" class="btn btn-sm btn-warning">
                    <i class="fas fa-sync"></i> Update
                </button>
                <button onclick="this.parentElement.parentElement.remove()" class="btn btn-sm btn-secondary">
                    Nanti
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(updateBanner);
}

function updateApp() {
    console.log('🔄 Updating app...');
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for(let registration of registrations) {
                registration.unregister();
            }
            showNotification('Aplikasi sedang diupdate...', 'info');
            setTimeout(() => {
                window.location.reload(true);
            }, 1000);
        });
    }
}

// Enhanced Quick Actions
function initQuickActions() {
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
}

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 DOM Loaded - Initializing...');
    
    updateOnlineStatus();
    updateHTTPSStatus();
    initQuickActions();
    showInstallBanner();
    
    if (isInStandaloneMode()) {
        document.body.classList.add('pwa-standalone');
        console.log('📱 Running in standalone mode');
    }
    
    if (localStorage.getItem('pwa-installed')) {
        console.log('✅ PWA already installed');
        if (installBanner) installBanner.style.display = 'none';
    }
    
    console.log('🚀 App initialization complete');
});

// iOS Specific Optimizations
if (isIOS) {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🍎 Applying iOS optimizations...');
        
        const inputs = document.querySelectorAll('input, select, textarea');
        const viewport = document.querySelector('meta[name="viewport"]');
        
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (viewport) {
                    viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
                }
            });
            input.addEventListener('blur', function() {
                if (viewport) {
                    viewport.setAttribute('content', 'width=device-width, initial-scale=1.0');
                }
            });
        });
    });
}

// Debug info
console.log('🔍 PWA Debug Info:', {
    basePath, swPath, isIOS, isSafari, isAndroid, isChrome, isEdge,
    isDesktop, isHTTPS, supportsPWA, supportsDesktopPWA, 
    isStandalone: isInStandaloneMode()
});
</script>

<!-- Enhanced PWA Styles -->
<style>
/* Hide PWA Install Button in Header - sesuai permintaan */
#pwa-install-btn {
    display: none !important;
}

/* Install Banner dengan Background Tombol Putih */
.install-banner-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.install-btn {
    background: white !important;
    color: #1E459F !important;
    border: 2px solid #1E459F !important;
    font-weight: bold;
    padding: 12px 25px;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.install-btn:hover {
    background: #f8f9fa !important;
    color: #1E459F !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(30, 69, 159, 0.2);
}

.install-btn:active {
    transform: translateY(0);
    background: #e9ecef !important;
}

.dismiss-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 12px 20px;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.dismiss-btn:hover {
    background: rgba(255,255,255,0.3);
}

/* Desktop PWA Modal */
.desktop-requirements {
    text-align: left;
}

.requirement-list {
    margin: 20px 0;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.requirement-item i {
    color: #1E459F;
    font-size: 1.2rem;
    min-width: 24px;
}

.current-status {
    margin: 20px 0;
    padding: 15px;
    background: #e3f2fd;
    border-radius: 10px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 8px 0;
}

.status-item i {
    color: #1976d2;
    min-width: 20px;
}

.install-tip {
    padding: 15px;
    background: #fff3cd;
    border-radius: 8px;
    color: #856404;
    margin: 15px 0;
}

/* Welcome Message */
.welcome-message {
    background: linear-gradient(45deg, rgba(30, 69, 159, 0.1), rgba(250, 189, 50, 0.1));
    border-left: 5px solid #1E459F;
    margin-bottom: 30px;
    border-radius: 15px;
    padding: 20px;
}

.status-badge {
    margin-left: 10px;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Quick Actions Card */
.quick-actions-card {
    margin-top: 30px;
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.quick-actions-card .card-header {
    background: linear-gradient(135deg, #1E459F, #2056b8);
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.app-controls {
    display: flex;
    gap: 10px;
}

.quick-actions-content {
    padding: 25px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    cursor: pointer;
}

.quick-action-btn i {
    font-size: 2rem;
}

.quick-action-btn span {
    font-weight: 600;
    text-align: center;
}

.quick-action-btn:hover {
    text-decoration: none;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

/* Enhanced Mobile Responsive */
@media (max-width: 768px) {
    .install-banner-content {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .install-banner-content > div:first-child {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .requirement-item {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }
    
    .install-btn, .dismiss-btn {
        width: 100%;
        margin: 5px 0;
    }
}

/* Rest of the existing styles remain the same... */
/* ... (semua style notification, modal, stats, dll tetap sama) ... */
</style>

<?php require_once '../../includes/footer.php'; ?>