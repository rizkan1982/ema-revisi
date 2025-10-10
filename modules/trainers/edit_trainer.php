<?php
$page_title = "Edit Pelatih";
require_once '../../includes/header.php';
requireRole(['admin']);

$trainer_id = intval($_GET['id']);
$success = '';
$error = '';

$trainer = $db->fetch("
    SELECT t.*, u.full_name, u.email, u.phone, u.username, u.is_active
    FROM trainers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
", [$trainer_id]);

if (!$trainer) {
    redirect('modules/trainers/?error=not_found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->beginTransaction();
        
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $specialization = trim($_POST['specialization']);
        $experience_years = intval($_POST['experience_years']);
        $certification = trim($_POST['certification']);
        $hourly_rate = floatval($_POST['hourly_rate']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check email uniqueness (exclude current user)
        $existing = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $trainer['user_id']]);
        if ($existing) {
            throw new Exception('Email sudah digunakan oleh user lain!');
        }
        
        // Update user data
        $db->query("
            UPDATE users 
            SET email = ?, full_name = ?, phone = ?, is_active = ?
            WHERE id = ?
        ", [$email, $full_name, $phone, $is_active, $trainer['user_id']]);
        
        // Update trainer data
        $db->query("
            UPDATE trainers 
            SET specialization = ?, experience_years = ?, certification = ?, hourly_rate = ?
            WHERE id = ?
        ", [$specialization, $experience_years, $certification, $hourly_rate, $trainer_id]);
        
        $db->getConnection()->commit();
        
        $success = "Data pelatih berhasil diperbarui!";
        
        // Refresh data
        $trainer = $db->fetch("
            SELECT t.*, u.full_name, u.email, u.phone, u.username, u.is_active
            FROM trainers t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.id = ?
        ", [$trainer_id]);
        
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Edit Pelatih</h3>
        <div style="display: flex; gap: 10px;">
            <a href="view_trainer.php?id=<?= $trainer_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <a href="view_trainer.php?id=<?= $trainer_id ?>" class="btn btn-info">
                <i class="fas fa-eye"></i>
                Lihat Detail
            </a>
        </div>
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
            <!-- Current Trainer Info -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #1E459F;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="user-avatar" style="width: 60px; height: 60px; font-size: 1.5rem; background: linear-gradient(135deg, #1E459F, #CF2A2A);">
                        <?= strtoupper(substr($trainer['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h4 style="color: #1E459F; margin: 0;"><?= htmlspecialchars($trainer['full_name']) ?></h4>
                        <div style="color: #6c757d;">
                            <strong>Kode:</strong> <?= $trainer['trainer_code'] ?> | 
                            <strong>Username:</strong> <?= $trainer['username'] ?>
                        </div>
                    </div>
                    
                    <div style="margin-left: auto;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $trainer['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                <strong>Status Aktif</strong>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Personal Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-user"></i>
                        Informasi Personal
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($trainer['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($trainer['full_name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($trainer['phone']) ?>">
                    </div>
                </div>
                
                <!-- Professional Information -->
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-user-tie"></i>
                        Informasi Profesional
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">Spesialisasi</label>
                        <input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($trainer['specialization']) ?>" placeholder="Contoh: Kickboxing, Boxing, MMA">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Pengalaman (Tahun)</label>
                        <input type="number" name="experience_years" class="form-control" min="0" step="1" value="<?= $trainer['experience_years'] ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tarif per Jam (Rp)</label>
                        <input type="number" name="hourly_rate" class="form-control" step="0.01" min="0" value="<?= $trainer['hourly_rate'] ?>">
                        <small class="text-muted">Digunakan untuk perhitungan payroll</small>
                    </div>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <label class="form-label">Sertifikasi & Kualifikasi</label>
                <textarea name="certification" class="form-control" rows="4" placeholder="Daftar sertifikasi, kualifikasi, dan achievement..."><?= htmlspecialchars($trainer['certification']) ?></textarea>
            </div>
            
            <!-- Read-only System Info -->
            <div style="background: #e9ecef; padding: 15px; border-radius: 8px; margin-top: 20px;">
                <h6 style="color: #495057; margin-bottom: 10px;">Informasi Sistem (Read-Only)</h6>
                <div style="font-size: 0.9rem; color: #6c757d; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><strong>Trainer Code:</strong> <?= $trainer['trainer_code'] ?></div>
                    <div><strong>Username:</strong> <?= $trainer['username'] ?></div>
                    <div><strong>Bergabung:</strong> <?= formatDate($trainer['hire_date']) ?></div>
                    <div><strong>Akun Dibuat:</strong> <?= formatDate($trainer['created_at']) ?></div>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Update Data Pelatih
                </button>
                
                <a href="view_trainer.php?id=<?= $trainer_id ?>" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                
                <a href="index.php" class="btn btn-info btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-list"></i>
                    Daftar Pelatih
                </a>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>