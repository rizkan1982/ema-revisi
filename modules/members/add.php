<?php
$page_title = "Tambah Anggota Baru";
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
        $birth_date = $_POST['birth_date'];
        $address = trim($_POST['address']);
        $emergency_contact = trim($_POST['emergency_contact']);
        $martial_art_type = $_POST['martial_art_type'];
        $class_type = $_POST['class_type'];
        $belt_level = trim($_POST['belt_level']);
        $medical_notes = trim($_POST['medical_notes']);
        
        // Check if username or email already exists
        $existing = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            throw new Exception('Username atau email sudah digunakan!');
        }
        
        // Create user account
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $db->query("
            INSERT INTO users (username, email, password, full_name, phone, role) 
            VALUES (?, ?, ?, ?, ?, 'member')
        ", [$username, $email, $hashed_password, $full_name, $phone]);
        
        $user_id = $db->lastInsertId();
        
        // Generate member code
        $member_code = generateCode('MBR', 6);
        
        // Create member record
        $db->query("
            INSERT INTO members (user_id, member_code, birth_date, address, emergency_contact, 
                                join_date, martial_art_type, class_type, belt_level, medical_notes) 
            VALUES (?, ?, ?, ?, ?, CURRENT_DATE, ?, ?, ?, ?)
        ", [$user_id, $member_code, $birth_date, $address, $emergency_contact, 
            $martial_art_type, $class_type, $belt_level, $medical_notes]);
        
        $db->getConnection()->commit();
        
        $success = "Anggota baru berhasil ditambahkan dengan kode: $member_code";
        
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tambah Anggota Baru</h3>
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
                        <input type="text" name="username" class="form-control" required 
                               placeholder="Masukkan username unik">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required
                               placeholder="contoh@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required
                               placeholder="Minimal 6 karakter">
                        <small class="text-muted">Password harus minimal 6 karakter</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" name="full_name" class="form-control" required
                               placeholder="Nama lengkap member">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="tel" name="phone" class="form-control"
                               placeholder="08123456789">
                    </div>
                </div>
                
                <!-- Member Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-fist-raised"></i>
                        Informasi Member
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="birth_date" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" class="form-control" rows="3" 
                                  placeholder="Alamat lengkap member"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kontak Darurat</label>
                        <input type="tel" name="emergency_contact" class="form-control"
                               placeholder="Nomor telepon keluarga/kerabat">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Bela Diri *</label>
                        <select name="martial_art_type" class="form-control form-select" required>
                            <option value="">-- Pilih Tipe Bela Diri --</option>
                            <option value="savate">Savate (French Kickboxing)</option>
                            <option value="kickboxing">Kickboxing</option>
                            <option value="boxing">Boxing</option>
                        </select>
                        <small class="text-muted">Pilih jenis bela diri yang akan dipelajari</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Kelas *</label>
                        <select name="class_type" class="form-control form-select" required>
                            <option value="">-- Pilih Tipe Kelas --</option>
                            <option value="regular">Regular (Kelas Grup)</option>
                            <option value="private_6x">Private - 6x Sebulan</option>
                            <option value="private_8x">Private - 8x Sebulan</option>
                            <option value="private_10x">Private - 10x Sebulan</option>
                        </select>
                        <small class="text-muted">Regular untuk kelas grup, Private untuk kelas personal</small>
                    </div>
                </div>
            </div>
            
            <!-- Additional Information -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f8f9fa;">
                <h4 style="color: #1E459F; margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i>
                    Informasi Tambahan
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="form-group">
                        <label class="form-label">Level Sabuk</label>
                        <input type="text" name="belt_level" class="form-control" 
                               placeholder="Contoh: Putih, Kuning, Orange, Hijau, Biru, Coklat, Hitam">
                        <small class="text-muted">Level sabuk saat ini (kosongkan jika pemula)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Catatan Medis</label>
                        <textarea name="medical_notes" class="form-control" rows="4" 
                                  placeholder="Riwayat cedera, alergi, kondisi medis khusus, dll..."></textarea>
                        <small class="text-muted">Informasi penting untuk keselamatan saat latihan</small>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="margin-top: 40px; padding-top: 25px; border-top: 1px solid #dee2e6; text-align: center;">
                <button type="submit" class="btn btn-primary btn-lg" style="min-width: 200px;">
                    <i class="fas fa-user-plus"></i>
                    Simpan Anggota Baru
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 15px; min-width: 150px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Additional CSS for better styling -->
<style>
.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 12px 15px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #1E459F;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25);
}

.alert {
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    border: none;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.card-header {
    background: linear-gradient(135deg, #1E459F, #2056b8);
    color: white;
    border-radius: 15px 15px 0 0 !important;
    padding: 20px 25px;
}

.btn-primary {
    background: linear-gradient(135deg, #1E459F, #CF2A2A);
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(30, 69, 159, 0.3);
}

.btn-secondary {
    background: #6c757d;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
}

.text-muted {
    font-size: 12px;
    color: #6c757d !important;
}

h4 i {
    margin-right: 10px;
}

select.form-select option {
    padding: 10px;
}
</style>

<script>
// Add some client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const password = document.querySelector('input[name="password"]');
    const phone = document.querySelector('input[name="phone"]');
    
    // Password validation
    password.addEventListener('input', function() {
        if (this.value.length < 6 && this.value.length > 0) {
            this.style.borderColor = '#dc3545';
        } else {
            this.style.borderColor = '#e9ecef';
        }
    });
    
    // Phone number formatting
    phone.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value.startsWith('0')) {
            this.value = value;
        } else if (value.startsWith('62')) {
            this.value = '0' + value.substring(2);
        }
    });
    
    // Form validation before submit
    form.addEventListener('submit', function(e) {
        const requiredFields = ['username', 'email', 'password', 'full_name', 'martial_art_type', 'class_type'];
        let hasError = false;
        
        requiredFields.forEach(field => {
            const input = document.querySelector(`[name="${field}"]`);
            if (!input.value.trim()) {
                input.style.borderColor = '#dc3545';
                hasError = true;
            } else {
                input.style.borderColor = '#e9ecef';
            }
        });
        
        if (password.value.length < 6) {
            password.style.borderColor = '#dc3545';
            hasError = true;
            alert('Password harus minimal 6 karakter!');
        }
        
        if (hasError) {
            e.preventDefault();
            alert('Mohon lengkapi semua field yang wajib diisi!');
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>