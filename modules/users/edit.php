<?php
require_once '../../config/config.php';
requireLogin();

// Check permission - Super Admin only!
if (getUserRole() !== 'super_admin') {
    header("Location: ../dashboard/index.php?error=unauthorized");
    exit;
}

$page_title = "Edit User";
require_once '../../includes/header.php';

$error = '';
$success = '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get user data
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);

if (!$user) {
    header("Location: index.php?error=user_not_found");
    exit;
}

// Prevent editing own super admin account's role
$is_self = ($user_id == $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Permissions
    $can_manage_users = isset($_POST['can_manage_users']) ? 1 : 0;
    $can_manage_stock = isset($_POST['can_manage_stock']) ? 1 : 0;
    $can_view_reports = isset($_POST['can_view_reports']) ? 1 : 0;
    $can_manage_finance = isset($_POST['can_manage_finance']) ? 1 : 0;
    
    // Password change (optional)
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    if (empty($full_name) || empty($email) || empty($username)) {
        $error = 'Nama, email, dan username wajib diisi!';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'Password baru dan konfirmasi password tidak cocok!';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Check duplicate username/email (excluding current user)
        $duplicate = $db->fetch(
            "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?",
            [$username, $email, $user_id]
        );
        
        if ($duplicate) {
            $error = 'Username atau email sudah digunakan user lain!';
        } else {
            try {
                // Prevent changing own super admin role
                if ($is_self && $user['role'] === 'super_admin') {
                    $role = 'super_admin';
                    $can_manage_users = 1;
                }
                
                // Update user
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->query(
                        "UPDATE users SET 
                        full_name = ?, email = ?, username = ?, phone = ?, 
                        role = ?, is_active = ?, password = ?,
                        can_manage_users = ?, can_manage_stock = ?, 
                        can_view_reports = ?, can_manage_finance = ?,
                        updated_at = NOW()
                        WHERE id = ?",
                        [$full_name, $email, $username, $phone, $role, $is_active, $hashed_password,
                         $can_manage_users, $can_manage_stock, $can_view_reports, $can_manage_finance, $user_id]
                    );
                } else {
                    $db->query(
                        "UPDATE users SET 
                        full_name = ?, email = ?, username = ?, phone = ?, 
                        role = ?, is_active = ?,
                        can_manage_users = ?, can_manage_stock = ?, 
                        can_view_reports = ?, can_manage_finance = ?,
                        updated_at = NOW()
                        WHERE id = ?",
                        [$full_name, $email, $username, $phone, $role, $is_active,
                         $can_manage_users, $can_manage_stock, $can_view_reports, $can_manage_finance, $user_id]
                    );
                }
                
                // Log activity
                logActivity('update_user', 'users', $user_id, [
                    'old_name' => $user['full_name'],
                    'old_role' => $user['role']
                ], [
                    'new_name' => $full_name,
                    'new_role' => $role
                ]);
                
                $success = 'User berhasil diupdate!';
                
                // Refresh user data
                $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
                
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

$role_colors = [
    'super_admin' => 'purple',
    'admin' => 'primary',
    'staff' => 'warning',
    'member' => 'success'
];

$role_labels = [
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Staff',
    'member' => 'Member'
];
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-user-edit"></i> Edit User
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Update informasi user</p>
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

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header" style="background: #f8f9fa; border-bottom: 2px solid #1E459F;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-info-circle"></i> Informasi User
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i> Nama Lengkap *
                                </label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i> Email *
                                </label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-id-badge"></i> Username *
                                </label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone"></i> Telepon
                                </label>
                                <input type="text" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <hr style="margin: 25px 0;">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tag"></i> Role *
                                </label>
                                <select name="role" class="form-control" <?= $is_self && $user['role'] === 'super_admin' ? 'disabled' : '' ?> required>
                                    <option value="member" <?= $user['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                    <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="super_admin" <?= $user['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                </select>
                                <?php if ($is_self && $user['role'] === 'super_admin'): ?>
                                <small class="text-muted">Tidak dapat mengubah role akun sendiri</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-toggle-on"></i> Status
                                </label>
                                <div class="custom-control custom-switch" style="padding-top: 8px;">
                                    <input type="checkbox" name="is_active" class="custom-control-input" 
                                           id="is_active" <?= $user['is_active'] ? 'checked' : '' ?>
                                           <?= $is_self ? 'disabled' : '' ?>>
                                    <label class="custom-control-label" for="is_active">
                                        <strong>Aktif</strong>
                                    </label>
                                </div>
                                <?php if ($is_self): ?>
                                <small class="text-muted">Tidak dapat menonaktifkan akun sendiri</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <hr style="margin: 25px 0;">
                    
                    <h6 style="color: #1E459F; margin-bottom: 15px;">
                        <i class="fas fa-key"></i> Ubah Password (Opsional)
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <input type="password" name="new_password" class="form-control" 
                                       placeholder="Kosongkan jika tidak ingin ubah password">
                                <small class="text-muted">Minimal 6 karakter</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Ulangi password baru">
                            </div>
                        </div>
                    </div>
                    
                    <hr style="margin: 25px 0;">
                    
                    <h6 style="color: #1E459F; margin-bottom: 15px;">
                        <i class="fas fa-shield-alt"></i> Hak Akses (Permissions)
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="custom-control custom-checkbox" style="margin-bottom: 10px;">
                                <input type="checkbox" name="can_manage_users" class="custom-control-input" 
                                       id="can_manage_users" <?= $user['can_manage_users'] ? 'checked' : '' ?>
                                       <?= $is_self && $user['role'] === 'super_admin' ? 'disabled' : '' ?>>
                                <label class="custom-control-label" for="can_manage_users">
                                    <strong>Kelola User</strong>
                                    <br><small class="text-muted">Dapat menambah, edit, dan hapus user</small>
                                </label>
                            </div>
                            
                            <div class="custom-control custom-checkbox" style="margin-bottom: 10px;">
                                <input type="checkbox" name="can_manage_stock" class="custom-control-input" 
                                       id="can_manage_stock" <?= $user['can_manage_stock'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="can_manage_stock">
                                    <strong>Kelola Inventaris</strong>
                                    <br><small class="text-muted">Dapat mengelola stok barang</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="custom-control custom-checkbox" style="margin-bottom: 10px;">
                                <input type="checkbox" name="can_view_reports" class="custom-control-input" 
                                       id="can_view_reports" <?= $user['can_view_reports'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="can_view_reports">
                                    <strong>Lihat Laporan</strong>
                                    <br><small class="text-muted">Dapat melihat semua laporan</small>
                                </label>
                            </div>
                            
                            <div class="custom-control custom-checkbox" style="margin-bottom: 10px;">
                                <input type="checkbox" name="can_manage_finance" class="custom-control-input" 
                                       id="can_manage_finance" <?= $user['can_manage_finance'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="can_manage_finance">
                                    <strong>Kelola Keuangan</strong>
                                    <br><small class="text-muted">Dapat mengelola transaksi keuangan</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <hr style="margin: 25px 0;">
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 style="margin: 0;">
                    <i class="fas fa-info-circle"></i> Informasi User
                </h5>
            </div>
            <div class="card-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 80px; height: 80px; margin: 0 auto; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <h5 style="margin: 15px 0 5px 0;"><?= htmlspecialchars($user['full_name']) ?></h5>
                    <span class="badge badge-<?= $role_colors[$user['role']] ?>">
                        <?= $role_labels[$user['role']] ?>
                    </span>
                </div>
                
                <table class="table table-sm">
                    <tr>
                        <td><i class="fas fa-calendar-plus text-muted"></i> <strong>Bergabung:</strong></td>
                        <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td><i class="fas fa-clock text-muted"></i> <strong>Update Terakhir:</strong></td>
                        <td><?= date('d M Y H:i', strtotime($user['updated_at'])) ?></td>
                    </tr>
                    <?php if ($user['last_login']): ?>
                    <tr>
                        <td><i class="fas fa-sign-in-alt text-muted"></i> <strong>Login Terakhir:</strong></td>
                        <td><?= date('d M Y H:i', strtotime($user['last_login'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px; border-left: 4px solid #ffc107;">
            <div class="card-body">
                <h6 style="color: #ffc107; margin-bottom: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> Peringatan
                </h6>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem;">
                    <li>Pastikan data user benar sebelum menyimpan</li>
                    <li>Perubahan role akan mempengaruhi akses user</li>
                    <li>Password hanya diubah jika diisi</li>
                    <li>Menonaktifkan user akan memblokir akses mereka</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
