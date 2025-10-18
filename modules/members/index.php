<?php
require_once '../../config/config.php';
requireLogin();
requireRole(['super_admin', 'admin']);

$page_title = "Member Management";
require_once '../../includes/header.php';

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where = ["role = 'member'"];
$params = [];

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== '') {
    $where[] = "is_active = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where);

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Get total
$total = $db->fetch(
    "SELECT COUNT(*) as count FROM users WHERE $where_clause",
    $params
);
$total_count = $total['count'];
$total_pages = ceil($total_count / $per_page);

// Get members
$members = $db->fetchAll(
    "SELECT u.*, 
            (SELECT COUNT(*) FROM inventory_requests WHERE requested_by = u.id) as total_requests
     FROM users u
     WHERE $where_clause
     ORDER BY u.created_at DESC
     LIMIT $per_page OFFSET $offset",
    $params
);

// Stats
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_members,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_members,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_members,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today
    FROM users 
    WHERE role = 'member'
");
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-users"></i> Member Management
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Kelola data anggota EMA Camp</p>
    </div>
    <div>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Tambah Member
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row" style="margin-bottom: 25px;">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['total_members']) ?></div>
                <div class="stat-label">Total Members</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['active_members']) ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <div class="stat-icon">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['inactive_members']) ?></div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <div class="stat-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?= number_format($stats['new_today']) ?></div>
                <div class="stat-label">New Today</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <div style="padding: 20px;">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="text" name="search" class="form-control" 
                               placeholder="🔍 Cari nama, username, atau email..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group" style="margin-bottom: 0;">
                        <select name="status" class="form-control">
                            <option value="">-- Semua Status --</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                    <button type="button" class="btn btn-outline-info" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Members Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="20%">Member Info</th>
                    <th width="15%">Contact</th>
                    <th width="10%">Status</th>
                    <th width="10%">Requests</th>
                    <th width="15%">Joined Date</th>
                    <th width="15%">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: #6c757d;">
                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i>
                        <p style="margin: 0;">Tidak ada data member</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($members as $index => $member): ?>
                    <tr>
                        <td><?= $offset + $index + 1 ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1E459F;">
                                        <?= htmlspecialchars($member['full_name']) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #6c757d;">
                                        @<?= htmlspecialchars($member['username']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 0.9rem;">
                                <div style="margin-bottom: 5px;">
                                    <i class="fas fa-envelope" style="color: #6c757d; width: 16px;"></i>
                                    <?= htmlspecialchars($member['email']) ?>
                                </div>
                                <?php if (!empty($member['phone'])): ?>
                                <div>
                                    <i class="fas fa-phone" style="color: #6c757d; width: 16px;"></i>
                                    <?= htmlspecialchars($member['phone']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($member['is_active']): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary">
                                    <i class="fas fa-times-circle"></i> Inactive
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-info">
                                <i class="fas fa-clipboard-list"></i> 
                                <?= number_format($member['total_requests']) ?> requests
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 0.9rem;">
                                <div><?= date('d M Y', strtotime($member['created_at'])) ?></div>
                                <div style="font-size: 0.8rem; color: #6c757d;">
                                    <?= date('H:i', strtotime($member['created_at'])) ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="view.php?id=<?= $member['id'] ?>" 
                                   class="btn btn-sm btn-info" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?= $member['id'] ?>" 
                                   class="btn btn-sm btn-warning" 
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($member['is_active']): ?>
                                <a href="toggle_status.php?id=<?= $member['id'] ?>&action=deactivate" 
                                   class="btn btn-sm btn-secondary" 
                                   title="Deactivate"
                                   onclick="return confirm('Nonaktifkan member ini?')">
                                    <i class="fas fa-ban"></i>
                                </a>
                                <?php else: ?>
                                <a href="toggle_status.php?id=<?= $member['id'] ?>&action=activate" 
                                   class="btn btn-sm btn-success" 
                                   title="Activate"
                                   onclick="return confirm('Aktifkan member ini?')">
                                    <i class="fas fa-check"></i>
                                </a>
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
    <div style="padding: 20px; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center;">
        <div style="color: #6c757d;">
            Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_count) ?> of <?= number_format($total_count) ?> members
        </div>
        <div>
            <div class="btn-group">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter !== '' ? '&status=' . $status_filter : '' ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter !== '' ? '&status=' . $status_filter : '' ?>" 
                       class="btn btn-<?= $i === $page ? 'primary' : 'outline-primary' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status_filter !== '' ? '&status=' . $status_filter : '' ?>" 
                       class="btn btn-outline-primary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.stat-card {
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
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

.btn-group {
    display: flex;
    gap: 5px;
}
</style>

<script>
// Auto refresh every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);
</script>

<?php require_once '../../includes/footer.php'; ?>
