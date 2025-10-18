<?php
// Process POST SEBELUM include header untuk avoid "headers already sent"
require_once '../../config/config.php';
requireLogin();
requireRole(['super_admin']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'];
        
        // Permissions
        $can_manage_users = isset($_POST['can_manage_users']) ? 1 : 0;
        $can_manage_stock = isset($_POST['can_manage_stock']) ? 1 : 0;
        $can_view_reports = isset($_POST['can_view_reports']) ? 1 : 0;
        $can_manage_finance = isset($_POST['can_manage_finance']) ? 1 : 0;
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
            throw new Exception('Semua field wajib diisi!');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email tidak valid!');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('Password minimal 6 karakter!');
        }
        
        // Check if username or email already exists
        $existing = $db->fetch(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        
        if ($existing) {
            throw new Exception('Username atau email sudah digunakan!');
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $db->query(
            "INSERT INTO users 
            (username, email, password, full_name, phone, role, is_active, created_by,
             can_manage_users, can_manage_stock, can_view_reports, can_manage_finance) 
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)",
            [$username, $email, $hashed_password, $full_name, $phone, $role, 
             $_SESSION['user_id'], $can_manage_users, $can_manage_stock, 
             $can_view_reports, $can_manage_finance]
        );
        
        $user_id = $db->lastInsertId();
        
        // Log activity
        logActivity('create_user', 'users', $user_id, null, [
            'username' => $username,
            'role' => $role
        ]);
        
        // Redirect dengan success message
        $_SESSION['success_message'] = "User baru '$username' berhasil ditambahkan!";
        header("Location: index.php");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Include header SETELAH POST processing
$page_title = "Tambah User";
require_once '../../includes/header.php';
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-user-plus"></i> Tambah User Baru
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Buat akun user baru dengan role dan permissions</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="">
        <div style="padding: 25px;">
            <div class="row">
                <!-- Account Information -->
                <div class="col-lg-6">
                    <h4 style="color: #1E459F; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                        <i class="fas fa-user-circle"></i> Informasi Akun
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Username *
                        </label>
                        <input type="text" name="username" class="form-control" required 
                               placeholder="username (lowercase, no space)">
                        <small class="form-text text-muted">Akan digunakan untuk login</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email *
                        </label>
                        <input type="email" name="email" class="form-control" required
                               placeholder="email@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                        <input type="password" name="password" id="password" class="form-control" required
                               placeholder="Minimal 6 karakter">
                        <small class="form-text text-muted">Password akan di-hash dengan aman</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i> Nama Lengkap *
                        </label>
                        <input type="text" name="full_name" class="form-control" required
                               placeholder="Nama lengkap user">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i> Nomor Telepon
                        </label>
                        <input type="tel" name="phone" class="form-control"
                               placeholder="08xxxxxxxxxx">
                    </div>
                </div>
                
                <!-- Role & Permissions -->
                <div class="col-lg-6">
                    <h4 style="color: #CF2A2A; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                        <i class="fas fa-shield-alt"></i> Role & Permissions
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-tag"></i> Role *
                        </label>
                        <select name="role" id="role" class="form-control" required onchange="updatePermissionRecommendations()">
                            <option value="">-- Pilih Role --</option>
                            <option value="super_admin">Super Admin (Full Access)</option>
                            <option value="admin">Admin (Operational)</option>
                            <option value="staff">Staff/Trainer</option>
                            <option value="member">Member</option>
                        </select>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <strong style="color: #1E459F;">
                            <i class="fas fa-info-circle"></i> Permissions
                        </strong>
                        <p style="font-size: 0.85rem; margin: 5px 0 10px 0; color: #6c757d;">
                            Centang permission yang sesuai dengan role
                        </p>
                        
                        <div class="form-check" style="margin-bottom: 10px;">
                            <input type="checkbox" name="can_manage_users" id="can_manage_users" class="form-check-input">
                            <label class="form-check-label" for="can_manage_users">
                                <strong>Manage Users</strong>
                                <br>
                                <small>Tambah, edit, hapus user (Super Admin only)</small>
                            </label>
                        </div>
                        
                        <div class="form-check" style="margin-bottom: 10px;">
                            <input type="checkbox" name="can_manage_stock" id="can_manage_stock" class="form-check-input">
                            <label class="form-check-label" for="can_manage_stock">
                                <strong>Manage Stock</strong>
                                <br>
                                <small>Kelola inventaris, approve/reject requests</small>
                            </label>
                        </div>
                        
                        <div class="form-check" style="margin-bottom: 10px;">
                            <input type="checkbox" name="can_view_reports" id="can_view_reports" class="form-check-input">
                            <label class="form-check-label" for="can_view_reports">
                                <strong>View Reports</strong>
                                <br>
                                <small>Akses laporan dan analytics</small>
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" name="can_manage_finance" id="can_manage_finance" class="form-check-input">
                            <label class="form-check-label" for="can_manage_finance">
                                <strong>Manage Finance</strong>
                                <br>
                                <small>Kelola keuangan dan pembayaran</small>
                            </label>
                        </div>
                    </div>
                    
                    <div id="roleRecommendation" class="alert alert-info" style="display: none; font-size: 0.9rem;">
                        <strong><i class="fas fa-lightbulb"></i> Rekomendasi:</strong>
                        <div id="recommendationText"></div>
                    </div>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-times"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Simpan User
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Password Generator -->
<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px;">
        <h6 style="color: #1E459F; margin-bottom: 15px;">
            <i class="fas fa-key"></i> Password Generator
        </h6>
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" id="generated-password" class="form-control" 
                   placeholder="Password akan muncul di sini..." readonly>
            <button type="button" class="btn btn-outline-primary" onclick="generatePassword()">
                <i class="fas fa-random"></i> Generate
            </button>
            <button type="button" class="btn btn-outline-success" onclick="copyPassword()">
                <i class="fas fa-copy"></i> Copy
            </button>
            <button type="button" class="btn btn-outline-warning" onclick="usePassword()">
                <i class="fas fa-arrow-up"></i> Use
            </button>
        </div>
    </div>
</div>

<script>
// Password generator
function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('generated-password').value = password;
}

function copyPassword() {
    const passwordField = document.getElementById('generated-password');
    if (passwordField.value) {
        passwordField.select();
        document.execCommand('copy');
        alert('Password berhasil disalin ke clipboard!');
    } else {
        alert('Generate password terlebih dahulu!');
    }
}

function usePassword() {
    const generatedPassword = document.getElementById('generated-password').value;
    if (generatedPassword) {
        document.getElementById('password').value = generatedPassword;
        alert('Password berhasil digunakan!');
    } else {
        alert('Generate password terlebih dahulu!');
    }
}

// Auto generate on load
document.addEventListener('DOMContentLoaded', function() {
    generatePassword();
});

// Permission recommendations based on role
function updatePermissionRecommendations() {
    const role = document.getElementById('role').value;
    const recommendation = document.getElementById('roleRecommendation');
    const recommendationText = document.getElementById('recommendationText');
    
    const manageUsers = document.getElementById('can_manage_users');
    const manageStock = document.getElementById('can_manage_stock');
    const viewReports = document.getElementById('can_view_reports');
    const manageFinance = document.getElementById('can_manage_finance');
    
    // Reset all
    manageUsers.checked = false;
    manageStock.checked = false;
    viewReports.checked = false;
    manageFinance.checked = false;
    
    switch(role) {
        case 'super_admin':
            manageUsers.checked = true;
            manageStock.checked = true;
            viewReports.checked = true;
            manageFinance.checked = true;
            recommendation.style.display = 'block';
            recommendationText.innerHTML = 'Super Admin memiliki <strong>FULL ACCESS</strong> ke semua fitur sistem.';
            break;
            
        case 'admin':
            manageStock.checked = true;
            viewReports.checked = true;
            manageFinance.checked = true;
            recommendation.style.display = 'block';
            recommendationText.innerHTML = 'Admin operational: Kelola stock, finance, dan reports. <strong>Tidak bisa</strong> manage users.';
            break;
            
        case 'staff':
            recommendation.style.display = 'block';
            recommendationText.innerHTML = 'Staff/Trainer: <strong>View only</strong> inventory dan bisa create requests. Tidak bisa approve.';
            break;
            
        case 'member':
            recommendation.style.display = 'block';
            recommendationText.innerHTML = 'Member: <strong>View only</strong> inventory dan bisa create requests untuk kebutuhan pribadi.';
            break;
            
        default:
            recommendation.style.display = 'none';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
