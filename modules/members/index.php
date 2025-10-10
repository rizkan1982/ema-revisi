<?php
ob_start(); // Fix header error
$page_title = "Manajemen Anggota";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $member_id = intval($_GET['id']);
    
    try {
        $db->getConnection()->beginTransaction();
        
        // Get member info for confirmation
        $member = $db->fetch("
            SELECT m.*, u.full_name, u.id as user_id 
            FROM members m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.id = ?
        ", [$member_id]);
        
        if ($member) {
            // Soft delete - set user as inactive
            $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$member['user_id']]);
            
            // Add notification
            $db->query("
                INSERT INTO notifications (recipient_id, title, message, type) 
                VALUES (?, ?, ?, 'general')
            ", [
                $_SESSION['user_id'], 
                'Member Dihapus', 
                "Member {$member['full_name']} ({$member['member_code']}) telah dihapus dari sistem pada " . date('d/m/Y H:i:s')
            ]);
            
            $db->getConnection()->commit();
            
            // JavaScript redirect to avoid header error
            echo "<script>window.location.href = 'index.php?success=deleted';</script>";
            exit;
        }
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        echo "<script>window.location.href = 'index.php?error=" . urlencode($e->getMessage()) . "';</script>";
        exit;
    }
}

// Handle restore action - NEW FEATURE
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $member_id = intval($_GET['id']);
    
    try {
        $db->getConnection()->beginTransaction();
        
        // Get member info for confirmation
        $member = $db->fetch("
            SELECT m.*, u.full_name, u.id as user_id 
            FROM members m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.id = ?
        ", [$member_id]);
        
        if ($member) {
            // Restore - set user as active
            $db->query("UPDATE users SET is_active = 1 WHERE id = ?", [$member['user_id']]);
            
            // Add notification
            $db->query("
                INSERT INTO notifications (recipient_id, title, message, type) 
                VALUES (?, ?, ?, 'general')
            ", [
                $_SESSION['user_id'], 
                'Member Direstore', 
                "Member {$member['full_name']} ({$member['member_code']}) telah dikembalikan ke sistem pada " . date('d/m/Y H:i:s')
            ]);
            
            $db->getConnection()->commit();
            
            // JavaScript redirect to avoid header error
            echo "<script>window.location.href = 'index.php?success=restored';</script>";
            exit;
        }
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        echo "<script>window.location.href = 'index.php?error=" . urlencode($e->getMessage()) . "';</script>";
        exit;
    }
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';
$filter_class = $_GET['filter_class'] ?? '';
$show_deleted = $_GET['show_deleted'] ?? '0'; // NEW: Filter untuk menampilkan member yang dihapus

// Build query - MODIFIED to support show deleted members
if ($show_deleted === '1') {
    $where_conditions = ["u.is_active = 0"]; // Show only deleted members
} else {
    $where_conditions = ["u.is_active = 1"]; // Show only active members
}
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR m.member_code LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if (!empty($filter_type)) {
    $where_conditions[] = "m.martial_art_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_class)) {
    $where_conditions[] = "m.class_type = ?";
    $params[] = $filter_class;
}

$where_clause = implode(' AND ', $where_conditions);

// Get members with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$members = $db->fetchAll("
    SELECT m.*, u.full_name, u.email, u.phone, u.created_at, u.is_active,
           DATEDIFF(CURRENT_DATE, m.join_date) as days_joined,
           (SELECT COUNT(*) FROM member_classes mc WHERE mc.member_id = m.id AND mc.status = 'active') as active_classes,
           (SELECT COUNT(*) FROM payments p WHERE p.member_id = m.id AND p.status = 'paid') as total_payments
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE $where_clause
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

$total_members = $db->fetch("
    SELECT COUNT(*) as count 
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE $where_clause
", $params)['count'];

$total_pages = ceil($total_members / $limit);

// Display functions
function getMartialArtDisplayName($type) {
    switch($type) {
        case 'savate': return 'Savate';
        case 'kickboxing': return 'Kickboxing';
        case 'boxing': return 'Boxing';
        default: return ucfirst($type);
    }
}

function getClassTypeDisplayName($type) {
    switch($type) {
        case 'regular': return 'Regular';
        case 'private_6x': return 'Private 6x';
        case 'private_8x': return 'Private 8x';
        case 'private_10x': return 'Private 10x';
        default: return ucfirst($type);
    }
}

function getClassTypeBadgeClass($type) {
    switch($type) {
        case 'regular': return 'badge-success';
        case 'private_6x': return 'badge-warning';
        case 'private_8x': return 'badge-info';
        case 'private_10x': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

function getMartialArtBadgeClass($type) {
    switch($type) {
        case 'savate': return 'badge-primary';
        case 'kickboxing': return 'badge-info';
        case 'boxing': return 'badge-warning';
        default: return 'badge-secondary';
    }
}

// Get statistics - MODIFIED to include deleted members count
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE u.is_active = 1")['count'],
    'deleted' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE u.is_active = 0")['count'],
    'savate' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.martial_art_type = 'savate' AND u.is_active = 1")['count'],
    'kickboxing' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.martial_art_type = 'kickboxing' AND u.is_active = 1")['count'],
    'boxing' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.martial_art_type = 'boxing' AND u.is_active = 1")['count'],
    'regular' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.class_type = 'regular' AND u.is_active = 1")['count'],
    'private_6x' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.class_type = 'private_6x' AND u.is_active = 1")['count'],
    'private_8x' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.class_type = 'private_8x' AND u.is_active = 1")['count'],
    'private_10x' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.class_type = 'private_10x' AND u.is_active = 1")['count']
];
?>

<!-- Success/Error Messages -->
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible" style="margin-bottom: 25px;">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <?php if ($_GET['success'] === 'deleted'): ?>
        <i class="fas fa-check-circle"></i> Member berhasil dihapus dari sistem.
    <?php elseif ($_GET['success'] === 'restored'): ?>
        <i class="fas fa-check-circle"></i> Member berhasil dikembalikan ke sistem.
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible" style="margin-bottom: 25px;">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>

<!-- Header Actions -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-users"></i>
            <?= $show_deleted === '1' ? 'Daftar Member Yang Dihapus' : 'Daftar Anggota Aktif' ?>
        </h3>
        <div style="display: flex; gap: 10px;">
            <?php if ($show_deleted === '1'): ?>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-users"></i>
                    Lihat Member Aktif
                </a>
            <?php else: ?>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Tambah Anggota
                </a>
            <?php endif; ?>
            
            <a href="?show_deleted=<?= $show_deleted === '1' ? '0' : '1' ?>" class="btn <?= $show_deleted === '1' ? 'btn-success' : 'btn-warning' ?>">
                <?php if ($show_deleted === '1'): ?>
                    <i class="fas fa-eye"></i>
                    Aktif (<?= $stats['total'] ?>)
                <?php else: ?>
                    <i class="fas fa-trash-restore"></i>
                    Terhapus (<?= $stats['deleted'] ?>)
                <?php endif; ?>
            </a>
            
            <?php if ($show_deleted === '0'): ?>
            <a href="export.php" class="btn btn-success">
                <i class="fas fa-file-excel"></i>
                Export Excel
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div style="padding: 20px; border-bottom: 1px solid #dee2e6; background: #f8f9fa;">
        <form method="GET" action="">
            <input type="hidden" name="show_deleted" value="<?= $show_deleted ?>">
            <div style="display: grid; grid-template-columns: 1fr auto auto auto auto; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Pencarian</label>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, kode member, atau email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Tipe Bela Diri</label>
                    <select name="filter_type" class="form-control form-select">
                        <option value="">Semua Tipe</option>
                        <option value="savate" <?= $filter_type === 'savate' ? 'selected' : '' ?>>Savate</option>
                        <option value="kickboxing" <?= $filter_type === 'kickboxing' ? 'selected' : '' ?>>Kickboxing</option>
                        <option value="boxing" <?= $filter_type === 'boxing' ? 'selected' : '' ?>>Boxing</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Tipe Kelas</label>
                    <select name="filter_class" class="form-control form-select">
                        <option value="">Semua Kelas</option>
                        <option value="regular" <?= $filter_class === 'regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="private_6x" <?= $filter_class === 'private_6x' ? 'selected' : '' ?>>Private 6x</option>
                        <option value="private_8x" <?= $filter_class === 'private_8x' ? 'selected' : '' ?>>Private 8x</option>
                        <option value="private_10x" <?= $filter_class === 'private_10x' ? 'selected' : '' ?>>Private 10x</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Cari
                    </button>
                    
                    <a href="?show_deleted=<?= $show_deleted ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Members Table -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead style="background: linear-gradient(135deg, <?= $show_deleted === '1' ? '#dc3545, #c82333' : '#1E459F, #2056b8' ?>); color: white;">
                <tr>
                    <th style="border: none; padding: 15px;">Kode Member</th>
                    <th style="border: none; padding: 15px;">Nama Lengkap</th>
                    <th style="border: none; padding: 15px;">Kontak</th>
                    <th style="border: none; padding: 15px;">Tipe Bela Diri</th>
                    <th style="border: none; padding: 15px;">Kelas</th>
                    <th style="border: none; padding: 15px;">Bergabung</th>
                    <th style="border: none; padding: 15px;">Status</th>
                    <th style="border: none; padding: 15px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #6c757d;">
                        <i class="fas fa-<?= $show_deleted === '1' ? 'trash' : 'users' ?>" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                        <br>
                        <strong><?= $show_deleted === '1' ? 'Tidak ada member yang dihapus' : 'Belum ada member' ?></strong>
                        <br>
                        <small><?= $show_deleted === '1' ? 'Semua member masih aktif' : 'Silakan tambah member baru untuk memulai' ?></small>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($members as $member): ?>
                    <tr style="border-bottom: 1px solid #f1f3f4; <?= $show_deleted === '1' ? 'opacity: 0.7;' : '' ?>">
                        <td style="padding: 15px; vertical-align: middle;">
                            <strong style="color: <?= $show_deleted === '1' ? '#dc3545' : '#1E459F' ?>; font-size: 1.1rem;"><?= $member['member_code'] ?></strong>
                        </td>
                        <td style="padding: 15px; vertical-align: middle;">
                            <div>
                                <strong style="font-size: 1rem;"><?= htmlspecialchars($member['full_name']) ?></strong>
                                <?php if ($show_deleted === '1'): ?>
                                    <span class="badge badge-danger" style="margin-left: 8px; font-size: 0.7rem;">DELETED</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-envelope" style="margin-right: 5px;"></i>
                                    <?= htmlspecialchars($member['email']) ?>
                                </small>
                            </div>
                        </td>
                        <td style="padding: 15px; vertical-align: middle;">
                            <i class="fas fa-phone" style="margin-right: 5px; color: #28a745;"></i>
                            <?= htmlspecialchars($member['phone'] ?: '-') ?>
                        </td>
                        <td style="padding: 15px; vertical-align: middle;">
                            <?php if (!empty($member['martial_art_type'])): ?>
                                <span class="badge <?= getMartialArtBadgeClass($member['martial_art_type']) ?>" style="padding: 8px 12px; font-size: 0.85rem;">
                                    <?= getMartialArtDisplayName($member['martial_art_type']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="padding: 8px 12px;">Tidak diset</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px; vertical-align: middle;">
                            <?php if (!empty($member['class_type'])): ?>
                                <span class="badge <?= getClassTypeBadgeClass($member['class_type']) ?>" style="padding: 8px 12px; font-size: 0.85rem;">
                                    <?= getClassTypeDisplayName($member['class_type']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="padding: 8px 12px;">Tidak diset</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px; vertical-align: middle;">
                            <div>
                                <strong><?= formatDate($member['join_date']) ?></strong>
                                <br>
                                <small class="text-muted"><?= $member['days_joined'] ?> hari lalu</small>
                            </div>
                        </td>
                        <td style="padding: 15px; vertical-align: middle;">
                            <?php if ($show_deleted === '1'): ?>
                                <span class="badge badge-danger" style="padding: 8px 15px;">
                                    <i class="fas fa-times-circle"></i>
                                    Dihapus
                                </span>
                            <?php else: ?>
                                <div style="font-size: 0.9rem;">
                                    <div style="margin-bottom: 5px;">
                                        <i class="fas fa-chalkboard-teacher" style="margin-right: 5px; color: #007bff;"></i>
                                        Kelas: <strong><?= $member['active_classes'] ?></strong>
                                    </div>
                                    <div>
                                        <i class="fas fa-money-bill" style="margin-right: 5px; color: #28a745;"></i>
                                        Bayar: <strong><?= $member['total_payments'] ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px; vertical-align: middle; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                <?php if ($show_deleted === '1'): ?>
                                    <!-- Restore Actions -->
                                    <button onclick="restoreMember(<?= $member['id'] ?>, '<?= htmlspecialchars($member['full_name']) ?>', '<?= $member['member_code'] ?>')" 
                                            class="btn btn-sm btn-success" title="Restore" style="padding: 8px 12px;">
                                        <i class="fas fa-undo"></i>
                                        Restore
                                    </button>
                                    <a href="view.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-info" title="Lihat Detail" style="padding: 8px 10px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <!-- Normal Actions -->
                                    <a href="view.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-info" title="Lihat Detail" style="padding: 8px 10px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-warning" title="Edit" style="padding: 8px 10px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="attendance.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-success" title="Absensi" style="padding: 8px 10px;">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <button onclick="deleteMember(<?= $member['id'] ?>, '<?= htmlspecialchars($member['full_name']) ?>', '<?= $member['member_code'] ?>')" 
                                            class="btn btn-sm btn-danger" title="Hapus" style="padding: 8px 10px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="padding: 20px; display: flex; justify-content: center; border-top: 1px solid #dee2e6; background: #f8f9fa;">
        <div style="display: flex; gap: 5px; align-items: center;">
            <span style="margin-right: 15px; color: #6c757d;">
                Halaman <?= $page ?> dari <?= $total_pages ?> | Total: <?= $total_members ?> member
            </span>
            
            <?php if ($page > 1): ?>
                <a href="?page=1&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>&filter_class=<?= urlencode($filter_class) ?>&show_deleted=<?= $show_deleted ?>" 
                   class="btn btn-sm btn-secondary">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>&filter_class=<?= urlencode($filter_class) ?>&show_deleted=<?= $show_deleted ?>" 
                   class="btn btn-sm btn-secondary">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>&filter_class=<?= urlencode($filter_class) ?>&show_deleted=<?= $show_deleted ?>" 
                   class="btn btn-sm <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>&filter_class=<?= urlencode($filter_class) ?>&show_deleted=<?= $show_deleted ?>" 
                   class="btn btn-sm btn-secondary">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>&filter_class=<?= urlencode($filter_class) ?>&show_deleted=<?= $show_deleted ?>" 
                   class="btn btn-sm btn-secondary">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Stats -->
<?php if ($show_deleted === '0'): ?>
<div class="stats-grid" style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
    <div class="stat-card blue" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(0,123,255,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= number_format($stats['total']) ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Total Anggota</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red" style="background: linear-gradient(135deg, #dc3545, #a71e2a); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(220,53,69,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['savate'] ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Savate</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-fist-raised"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow" style="background: linear-gradient(135deg, #ffc107, #d39e00); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(255,193,7,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['kickboxing'] ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Kickboxing</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-hand-rock"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream" style="background: linear-gradient(135deg, #fd7e14, #e55100); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(253,126,20,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['boxing'] ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Boxing</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-boxing-glove"></i>
            </div>
        </div>
    </div>
</div>

<div class="stats-grid" style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
    <div class="stat-card green" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(40,167,69,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['regular'] ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Regular</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card orange" style="background: linear-gradient(135deg, #fd7e14, #e55100); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(253,126,20,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['private_6x'] ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Private 6x</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card purple" style="background: linear-gradient(135deg, #6f42c1, #59359a); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(111,66,193,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['private_8x'] ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Private 8x</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-user-plus"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card pink" style="background: linear-gradient(135deg, #e83e8c, #d91a72); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(232,62,140,0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="text-align: left;">
                <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['private_10x'] ?></h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Private 10x</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8;">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <h4 style="color: #dc3545; margin-bottom: 20px; display: flex; align-items: center;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 10px; font-size: 1.5rem;"></i>
            Konfirmasi Hapus Member
        </h4>
        <p style="margin-bottom: 15px; color: #495057;">Apakah Anda yakin ingin menghapus member:</p>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 15px 0; border-left: 4px solid #dc3545;">
            <strong id="deleteMemberName" style="font-size: 1.1rem; color: #1E459F;"></strong><br>
            <small style="color: #6c757d;">
                <i class="fas fa-id-badge" style="margin-right: 5px;"></i>
                Kode: <span id="deleteMemberCode"></span>
            </small>
        </div>
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107;">
            <small style="color: #856404;">
                <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                <strong>Soft Delete:</strong> Member akan disembunyikan dari sistem tapi data tidak hilang permanen. Data masih tersimpan di database untuk keperluan audit dan dapat direstore jika diperlukan.
            </small>
        </div>
        <div style="text-align: right; margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
            <button onclick="closeDeleteModal()" class="btn btn-secondary" style="padding: 10px 20px;">
                <i class="fas fa-times"></i>
                Batal
            </button>
            <a id="deleteConfirmBtn" href="#" class="btn btn-danger" style="padding: 10px 20px;">
                <i class="fas fa-eye-slash"></i>
                Ya, Sembunyikan
            </a>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restoreModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <h4 style="color: #28a745; margin-bottom: 20px; display: flex; align-items: center;">
            <i class="fas fa-undo-alt" style="margin-right: 10px; font-size: 1.5rem;"></i>
            Konfirmasi Restore Member
        </h4>
        <p style="margin-bottom: 15px; color: #495057;">Apakah Anda yakin ingin mengembalikan member:</p>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 15px 0; border-left: 4px solid #28a745;">
            <strong id="restoreMemberName" style="font-size: 1.1rem; color: #1E459F;"></strong><br>
            <small style="color: #6c757d;">
                <i class="fas fa-id-badge" style="margin-right: 5px;"></i>
                Kode: <span id="restoreMemberCode"></span>
            </small>
        </div>
        <div style="background: #d4edda; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #28a745;">
            <small style="color: #155724;">
                <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                <strong>Restore:</strong> Member akan dikembalikan ke sistem dan dapat mengakses semua fitur seperti sebelumnya.
            </small>
        </div>
        <div style="text-align: right; margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
            <button onclick="closeRestoreModal()" class="btn btn-secondary" style="padding: 10px 20px;">
                <i class="fas fa-times"></i>
                Batal
            </button>
            <a id="restoreConfirmBtn" href="#" class="btn btn-success" style="padding: 10px 20px;">
                <i class="fas fa-undo"></i>
                Ya, Kembalikan
            </a>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.badge {
    font-size: 0.8rem;
    padding: 6px 10px;
    border-radius: 6px;
}

.badge-primary { background: linear-gradient(135deg, #007bff, #0056b3); }
.badge-info { background: linear-gradient(135deg, #17a2b8, #138496); }
.badge-warning { background: linear-gradient(135deg, #ffc107, #d39e00); }
.badge-success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.badge-danger { background: linear-gradient(135deg, #dc3545, #c82333); }
.badge-secondary { background: linear-gradient(135deg, #6c757d, #5a6268); }

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 10px 15px;
}

.form-control:focus, .form-select:focus {
    border-color: #1E459F;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25);
}

.table {
    border-radius: 15px;
    overflow: hidden;
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr !important;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn {
        padding: 6px 8px;
        font-size: 0.8rem;
    }
}
</style>

<!-- JavaScript -->
<script>
function deleteMember(id, name, code) {
    document.getElementById('deleteMemberName').textContent = name;
    document.getElementById('deleteMemberCode').textContent = code;
    document.getElementById('deleteConfirmBtn').href = `?action=delete&id=${id}`;
    document.getElementById('deleteModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// NEW: Restore functions
function restoreMember(id, name, code) {
    document.getElementById('restoreMemberName').textContent = name;
    document.getElementById('restoreMemberCode').textContent = code;
    document.getElementById('restoreConfirmBtn').href = `?action=restore&id=${id}`;
    document.getElementById('restoreModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeRestoreModal() {
    document.getElementById('restoreModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

document.getElementById('restoreModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRestoreModal();
    }
});

// Keyboard support
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('deleteModal').style.display === 'block') {
            closeDeleteModal();
        }
        if (document.getElementById('restoreModal').style.display === 'block') {
            closeRestoreModal();
        }
    }
});

// Auto dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Loading states for buttons
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn') && !e.target.onclick) {
        e.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        e.target.disabled = true;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>