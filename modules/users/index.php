<?php
$page_title = "User Management";
require_once '../../includes/header.php';

// Check permission - Super Admin only!
if (getUserRole() !== 'super_admin') {
    redirect('modules/dashboard/index.php?error=unauthorized');
}

// Get filter parameters
$role = isset($_GET['role']) ? $_GET['role'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = [];
$params = [];

if ($role !== 'all') {
    $where[] = "role = ?";
    $params[] = $role;
}

if ($status === 'active') {
    $where[] = "is_active = 1";
} elseif ($status === 'inactive') {
    $where[] = "is_active = 0";
}

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch users
$users = $db->fetchAll("SELECT * FROM users {$where_clause} ORDER BY created_at DESC", $params);

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'super_admin' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin'")['count'],
    'admin' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'],
    'staff' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'staff'")['count'],
    'member' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'member'")['count'],
    'active' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'inactive' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 0")['count'],
];
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-users-cog"></i> User Management
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Kelola semua user sistem</p>
    </div>
    <div>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah User
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-2">
        <div class="card" style="border-left: 4px solid #1E459F;">
            <div class="card-body" style="padding: 15px;">
                <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 5px;">Total Users</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #1E459F;"><?= $stats['total'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card" style="border-left: 4px solid #6f42c1;">
            <div class="card-body" style="padding: 15px;">
                <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 5px;">Super Admin</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #6f42c1;"><?= $stats['super_admin'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card" style="border-left: 4px solid #007bff;">
            <div class="card-body" style="padding: 15px;">
                <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 5px;">Admin</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #007bff;"><?= $stats['admin'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card" style="border-left: 4px solid #ffc107;">
            <div class="card-body" style="padding: 15px;">
                <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 5px;">Staff</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #ffc107;"><?= $stats['staff'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card" style="border-left: 4px solid #17a2b8;">
            <div class="card-body" style="padding: 15px;">
                <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 5px;">Member</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #17a2b8;"><?= $stats['member'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card" style="border-left: 4px solid #28a745;">
            <div class="card-body" style="padding: 15px;">
                <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 5px;">Active</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #28a745;"><?= $stats['active'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-user-tag"></i> Role</label>
                <select name="role" class="form-control">
                    <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>Semua Role</option>
                    <option value="super_admin" <?= $role === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Member</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-toggle-on"></i> Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><i class="fas fa-search"></i> Cari</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Cari nama, email, atau username..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-hover" style="margin: 0;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 50px;">
                            <i class="fas fa-users" style="font-size: 4rem; color: #dee2e6; margin-bottom: 15px;"></i>
                            <p style="color: #6c757d; margin: 0;">Tidak ada user yang sesuai filter</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <?php
                        $role_colors = [
                            'super_admin' => 'purple',
                            'admin' => 'primary',
                            'staff' => 'warning',
                            'member' => 'info'
                        ];
                        $role_labels = [
                            'super_admin' => 'Super Admin',
                            'admin' => 'Admin',
                            'staff' => 'Staff',
                            'member' => 'Member'
                        ];
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-right: 10px;">
                                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge badge-success" style="font-size: 0.7rem; margin-left: 5px;">YOU</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><code><?= htmlspecialchars($user['username'] ?? '') ?></code></td>
                            <td>
                                <span class="badge badge-<?= $role_colors[$user['role'] ?? 'member'] ?? 'info' ?>">
                                    <?= $role_labels[$user['role'] ?? 'member'] ?? 'Unknown' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <?php if ($user['is_active']): ?>
                                        <a href="toggle_status.php?id=<?= $user['id'] ?>&action=deactivate" 
                                           class="btn btn-sm btn-danger" 
                                           title="Nonaktifkan"
                                           onclick="return confirm('Nonaktifkan user ini?')">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="toggle_status.php?id=<?= $user['id'] ?>&action=activate" 
                                           class="btn btn-sm btn-success" 
                                           title="Aktifkan"
                                           onclick="return confirm('Aktifkan user ini?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="reset_password.php?id=<?= $user['id'] ?>" 
                                       class="btn btn-sm btn-info" 
                                       title="Reset Password"
                                       onclick="return confirm('Reset password user ini?')">
                                        <i class="fas fa-key"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 10px; border-left: 4px solid #ffc107;">
    <h6 style="margin: 0 0 10px 0; color: #856404;">
        <i class="fas fa-exclamation-triangle"></i> Peringatan Keamanan
    </h6>
    <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: #856404;">
        <li>Hanya Super Admin yang dapat mengakses halaman ini</li>
        <li>Berhati-hatilah saat mengubah role user</li>
        <li>Jangan nonaktifkan akun Super Admin sendiri</li>
        <li>Reset password akan mengirim email ke user (jika dikonfigurasi)</li>
    </ul>
</div>

<style>
.badge-purple {
    background-color: #6f42c1;
    color: white;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
