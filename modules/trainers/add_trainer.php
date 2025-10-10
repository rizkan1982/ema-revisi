<?php
$page_title = "Tambah Pelatih Baru";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Validate input
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $specialization = trim($_POST['specialization']);
        $experience_years = intval($_POST['experience_years']);
        $certification = trim($_POST['certification']);
        $hourly_rate = floatval($_POST['hourly_rate']);
        $hire_date = $_POST['hire_date'];
        
        // Check if username or email already exists
        $existing = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            throw new Exception('Username atau email sudah digunakan!');
        }
        
        // Create user account
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $db->query("
            INSERT INTO users (username, email, password, full_name, phone, role) 
            VALUES (?, ?, ?, ?, ?, 'trainer')
        ", [$username, $email, $hashed_password, $full_name, $phone]);
        
        $user_id = $db->lastInsertId();
        
        // Generate trainer code
        $trainer_code = generateCode('TRN', 6);
        
        // Create trainer record
        $db->query("
            INSERT INTO trainers (user_id, trainer_code, specialization, experience_years, 
                                certification, hourly_rate, hire_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [$user_id, $trainer_code, $specialization, $experience_years, 
            $certification, $hourly_rate, $hire_date]);
        
        $db->getConnection()->commit();
        
        $success = "Pelatih baru berhasil ditambahkan dengan kode: $trainer_code";
        
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tambah Pelatih Baru</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $success ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div style="padding: 25px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Account Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-user-circle"></i>
                        Informasi Akun
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                </div>
                
                <!-- Trainer Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-user-tie"></i>
                        Informasi Pelatih
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Spesialisasi</label>
                        <input type="text" name="specialization" class="form-control" placeholder="Contoh: Kickboxing, Boxing, MMA">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Pengalaman (Tahun)</label>
                        <input type="number" name="experience_years" class="form-control" min="0" step="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Sertifikasi</label>
                        <textarea name="certification" class="form-control" rows="3" placeholder="Daftar sertifikasi yang dimiliki..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tarif per Jam (Rp)</label>
                        <input type="number" name="hourly_rate" class="form-control" step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Bergabung</label>
                        <input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Simpan Pelatih
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Password Generator -->
<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px;">
        <h6 style="color: #1E459F; margin-bottom: 15px;">
            <i class="fas fa-key"></i>
            Generator Password
        </h6>
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" id="generated-password" class="form-control" placeholder="Password akan muncul di sini..." readonly>
            <button type="button" class="btn btn-outline-primary" onclick="generatePassword()">
                <i class="fas fa-random"></i>
                Generate
            </button>
            <button type="button" class="btn btn-outline-success" onclick="copyPassword()">
                <i class="fas fa-copy"></i>
                Copy
            </button>
            <button type="button" class="btn btn-outline-warning" onclick="usePassword()">
                <i class="fas fa-arrow-up"></i>
                Use
            </button>
        </div>
    </div>
</div>

<script>
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
        document.querySelector('input[name="password"]').value = generatedPassword;
        alert('Password berhasil digunakan!');
    } else {
        alert('Generate password terlebih dahulu!');
    }
}

// Auto generate password on page load
document.addEventListener('DOMContentLoaded', function() {
    generatePassword();
});
</script>

<?php require_once '../../includes/footer.php'; ?>