<?php
$page_title = "Edit Member";
require_once '../../includes/header.php';
requireRole(['admin']);

$member_id = intval($_GET['id']);
$success = '';
$error = '';

$member = $db->fetch("
    SELECT m.*, u.full_name, u.email, u.phone, u.username
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.id = ?
", [$member_id]);

if (!$member) {
    redirect('modules/members/?error=member_not_found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->beginTransaction();
        
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $birth_date = $_POST['birth_date'] ?: null;
        $address = trim($_POST['address']);
        $emergency_contact = trim($_POST['emergency_contact']);
        $martial_art_type = $_POST['martial_art_type'];
        $class_type = $_POST['class_type'];
        $belt_level = trim($_POST['belt_level']);
        $medical_notes = trim($_POST['medical_notes']);
        
        // Validation
        if (empty($email) || empty($full_name) || empty($martial_art_type) || empty($class_type)) {
            throw new Exception('Field yang wajib diisi tidak boleh kosong!');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email tidak valid!');
        }
        
        // Check email uniqueness (exclude current user)
        $existing = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $member['user_id']]);
        if ($existing) {
            throw new Exception('Email sudah digunakan oleh member lain!');
        }
        
        // Update user data
        $db->query("
            UPDATE users 
            SET email = ?, full_name = ?, phone = ? 
            WHERE id = ?
        ", [$email, $full_name, $phone, $member['user_id']]);
        
        // Update member data
        $db->query("
            UPDATE members 
            SET birth_date = ?, address = ?, emergency_contact = ?, 
                martial_art_type = ?, class_type = ?, belt_level = ?, medical_notes = ?
            WHERE id = ?
        ", [$birth_date, $address, $emergency_contact, $martial_art_type, 
            $class_type, $belt_level, $medical_notes, $member_id]);
        
        $db->getConnection()->commit();
        
        $success = "Data member berhasil diperbarui!";
        
        // Refresh data
        $member = $db->fetch("
            SELECT m.*, u.full_name, u.email, u.phone, u.username
            FROM members m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.id = ?
        ", [$member_id]);
        
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = $e->getMessage();
    }
}

// Display functions
function getMartialArtDisplayName($type) {
    switch($type) {
        case 'savate': return 'Savate (French Kickboxing)';
        case 'kickboxing': return 'Kickboxing';
        case 'boxing': return 'Boxing';
        default: return ucfirst($type);
    }
}

function getClassTypeDisplayName($type) {
    switch($type) {
        case 'regular': return 'Regular (Kelas Grup)';
        case 'private_6x': return 'Private - 6x Sebulan';
        case 'private_8x': return 'Private - 8x Sebulan';
        case 'private_10x': return 'Private - 10x Sebulan';
        default: return ucfirst($type);
    }
}
?>

<div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
    <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white; border-radius: 15px 15px 0 0; padding: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0; font-size: 1.5rem;">
                    <i class="fas fa-user-edit"></i>
                    Edit Member
                </h3>
                <small style="opacity: 0.9; margin-top: 5px; display: block;">
                    Perbarui informasi data member
                </small>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <a href="index.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
                <a href="view.php?id=<?= $member_id ?>" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                    <i class="fas fa-eye"></i>
                    Lihat Detail
                </a>
            </div>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success" style="margin: 20px 25px 0; border-radius: 10px; border: none; background: linear-gradient(135deg, #d4edda, #c3e6cb);">
            <div style="display: flex; align-items: center;">
                <i class="fas fa-check-circle" style="font-size: 1.2rem; margin-right: 10px;"></i>
                <span><?= $success ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin: 20px 25px 0; border-radius: 10px; border: none; background: linear-gradient(135deg, #f8d7da, #f5c6cb);">
            <div style="display: flex; align-items: center;">
                <i class="fas fa-exclamation-circle" style="font-size: 1.2rem; margin-right: 10px;"></i>
                <span><?= $error ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="editForm">
        <div style="padding: 30px;">
            <!-- Current Member Info Display -->
            <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 20px; border-radius: 12px; margin-bottom: 30px; border-left: 5px solid #1E459F;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="user-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #1E459F, #CF2A2A); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: white;">
                        <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h4 style="color: #1E459F; margin: 0; font-size: 1.3rem;"><?= htmlspecialchars($member['full_name']) ?></h4>
                        <div style="color: #6c757d; margin-top: 5px;">
                            <span style="display: inline-flex; align-items: center; margin-right: 20px;">
                                <i class="fas fa-id-badge" style="margin-right: 5px;"></i>
                                <strong>Kode:</strong> <?= $member['member_code'] ?>
                            </span>
                            <span style="display: inline-flex; align-items: center;">
                                <i class="fas fa-user" style="margin-right: 5px;"></i>
                                <strong>Username:</strong> <?= $member['username'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                <!-- Personal Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 2px solid #f1f3f4;">
                        <i class="fas fa-user-circle"></i>
                        Informasi Personal
                    </h4>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Email <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($member['email']) ?>" required
                               style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease;"
                               placeholder="contoh@email.com">
                        <small style="color: #6c757d; font-size: 0.85rem;">Email untuk login dan komunikasi</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Nama Lengkap <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($member['full_name']) ?>" required
                               style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease;"
                               placeholder="Nama lengkap member">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Nomor Telepon
                        </label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone']) ?>"
                               style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease;"
                               placeholder="08123456789">
                        <small style="color: #6c757d; font-size: 0.85rem;">Nomor telepon yang bisa dihubungi</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Tanggal Lahir
                        </label>
                        <input type="date" name="birth_date" class="form-control" value="<?= $member['birth_date'] ?>"
                               style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Alamat Lengkap
                        </label>
                        <textarea name="address" class="form-control" rows="4" 
                                  style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease; resize: vertical;"
                                  placeholder="Alamat lengkap tempat tinggal"><?= htmlspecialchars($member['address']) ?></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Kontak Darurat
                        </label>
                        <input type="tel" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($member['emergency_contact']) ?>"
                               style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease;"
                               placeholder="Nomor telepon keluarga/kerabat">
                        <small style="color: #6c757d; font-size: 0.85rem;">Nomor yang dapat dihubungi saat darurat</small>
                    </div>
                </div>
                
                <!-- Member Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 2px solid #f1f3f4;">
                        <i class="fas fa-fist-raised"></i>
                        Informasi Member
                    </h4>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Tipe Bela Diri <span style="color: #dc3545;">*</span>
                        </label>
                        <select name="martial_art_type" class="form-control form-select" required
                                style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease; background: white;">
                            <option value="">-- Pilih Tipe Bela Diri --</option>
                            <option value="savate" <?= $member['martial_art_type'] === 'savate' ? 'selected' : '' ?>>
                                Savate (French Kickboxing)
                            </option>
                            <option value="kickboxing" <?= $member['martial_art_type'] === 'kickboxing' ? 'selected' : '' ?>>
                                Kickboxing
                            </option>
                            <option value="boxing" <?= $member['martial_art_type'] === 'boxing' ? 'selected' : '' ?>>
                                Boxing
                            </option>
                        </select>
                        <small style="color: #6c757d; font-size: 0.85rem;">Jenis bela diri yang dipelajari member</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Tipe Kelas <span style="color: #dc3545;">*</span>
                        </label>
                        <select name="class_type" class="form-control form-select" required
                                style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease; background: white;">
                            <option value="">-- Pilih Tipe Kelas --</option>
                            <option value="regular" <?= $member['class_type'] === 'regular' ? 'selected' : '' ?>>
                                Regular (Kelas Grup)
                            </option>
                            <option value="private_6x" <?= $member['class_type'] === 'private_6x' ? 'selected' : '' ?>>
                                Private - 6x Sebulan
                            </option>
                            <option value="private_8x" <?= $member['class_type'] === 'private_8x' ? 'selected' : '' ?>>
                                Private - 8x Sebulan
                            </option>
                            <option value="private_10x" <?= $member['class_type'] === 'private_10x' ? 'selected' : '' ?>>
                                Private - 10x Sebulan
                            </option>
                        </select>
                        <small style="color: #6c757d; font-size: 0.85rem;">Regular untuk kelas grup, Private untuk kelas personal</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Level Sabuk
                        </label>
                        <input type="text" name="belt_level" class="form-control" value="<?= htmlspecialchars($member['belt_level']) ?>" 
                               style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease;"
                               placeholder="Contoh: Putih, Kuning, Orange, Hijau, Biru, Coklat, Hitam">
                        <small style="color: #6c757d; font-size: 0.85rem;">Level sabuk saat ini (kosongkan jika pemula)</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 600; color: #495057; margin-bottom: 8px; display: block;">
                            Catatan Medis
                        </label>
                        <textarea name="medical_notes" class="form-control" rows="5" 
                                  style="border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; transition: all 0.3s ease; resize: vertical;"
                                  placeholder="Riwayat cedera, alergi, kondisi medis khusus, obat-obatan rutin, dll..."><?= htmlspecialchars($member['medical_notes']) ?></textarea>
                        <small style="color: #6c757d; font-size: 0.85rem;">Informasi penting untuk keselamatan saat latihan</small>
                    </div>
                    
                    <!-- Read-only System Info -->
                    <div style="background: #e9ecef; padding: 20px; border-radius: 10px; margin-top: 25px;">
                        <h6 style="color: #495057; margin-bottom: 15px; display: flex; align-items: center;">
                            <i class="fas fa-database" style="margin-right: 8px;"></i>
                            Informasi Sistem
                        </h6>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.9rem; color: #6c757d;">
                            <div>
                                <strong style="color: #495057;">Kode Member:</strong><br>
                                <span style="font-family: monospace; background: white; padding: 4px 8px; border-radius: 4px; color: #1E459F; font-weight: bold;"><?= $member['member_code'] ?></span>
                            </div>
                            <div>
                                <strong style="color: #495057;">Username:</strong><br>
                                <span style="font-family: monospace; background: white; padding: 4px 8px; border-radius: 4px;"><?= $member['username'] ?></span>
                            </div>
                            <div>
                                <strong style="color: #495057;">Bergabung:</strong><br>
                                <span><?= formatDate($member['join_date']) ?></span>
                            </div>
                            <div>
                                <strong style="color: #495057;">Lama Gabung:</strong><br>
                                <span><?= floor((time() - strtotime($member['join_date'])) / (60 * 60 * 24)) ?> hari</span>
                            </div>
                        </div>
                        <small style="color: #6c757d; margin-top: 10px; display: block; font-style: italic;">
                            <i class="fas fa-info-circle"></i>
                            Data sistem ini tidak dapat diubah dari form ini
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="margin-top: 40px; padding-top: 25px; border-top: 2px solid #f1f3f4; text-align: center;">
                <button type="submit" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #1E459F, #CF2A2A); border: none; padding: 15px 40px; border-radius: 10px; font-weight: 600; min-width: 200px; transition: all 0.3s ease;">
                    <i class="fas fa-save"></i>
                    Update Data Member
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 15px; padding: 15px 30px; border-radius: 10px; font-weight: 600; min-width: 150px; background: #6c757d; border: none;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                
                <a href="view.php?id=<?= $member_id ?>" class="btn btn-info btn-lg" style="margin-left: 15px; padding: 15px 30px; border-radius: 10px; font-weight: 600; min-width: 150px; background: #17a2b8; border: none;">
                    <i class="fas fa-eye"></i>
                    Lihat Detail
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Custom CSS -->
<style>
.form-control:focus, .form-select:focus {
    border-color: #1E459F !important;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25) !important;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3) !important;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #1a3d8f, #b8242e) !important;
}

.alert {
    border: none !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        display: block !important;
    }
    
    div[style*="grid-template-columns: 1fr 1fr"] > div {
        margin-bottom: 30px;
    }
    
    .card-header div[style*="display: flex"] {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 15px;
    }
    
    .btn-lg {
        width: 100%;
        margin: 5px 0 !important;
    }
    
    div[style*="text-align: center"] {
        text-align: left !important;
    }
}

/* Loading State */
.btn.loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    margin: auto;
    border: 2px solid transparent;
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    const emailInput = form.querySelector('input[name="email"]');
    const phoneInputs = form.querySelectorAll('input[type="tel"]');
    
    // Email validation
    emailInput.addEventListener('blur', function() {
        const email = this.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.style.borderColor = '#dc3545';
            this.parentNode.querySelector('small').style.color = '#dc3545';
            this.parentNode.querySelector('small').textContent = 'Format email tidak valid!';
        } else {
            this.style.borderColor = '#28a745';
            this.parentNode.querySelector('small').style.color = '#6c757d';
            this.parentNode.querySelector('small').textContent = 'Email untuk login dan komunikasi';
        }
    });
    
    // Phone number formatting
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                this.value = value;
            } else if (value.startsWith('62')) {
                this.value = '0' + value.substring(2);
            }
        });
    });
    
    // Form submission with loading state
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let hasError = false;
        
        // Validate required fields
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                hasError = true;
            } else {
                field.style.borderColor = '#28a745';
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Mohon lengkapi semua field yang wajib diisi!');
            return;
        }
        
        // Add loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
    });
    
    // Real-time validation feedback
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.hasAttribute('required') && this.value.trim()) {
                this.style.borderColor = '#28a745';
            }
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        });
    }, 5000);
});

// Prevent accidental form submission on Enter key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') {
        e.preventDefault();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>