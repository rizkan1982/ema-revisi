<?php
$page_title = "Kelola Kelas";
require_once '../../includes/header.php';
requireRole(['admin']);

// Get all classes with their details
$classes = $db->fetchAll("
    SELECT c.*, u.full_name as trainer_name, t.trainer_code,
           COUNT(DISTINCT mc.member_id) as enrolled_count,
           COUNT(DISTINCT s.id) as schedule_count
    FROM classes c
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
    GROUP BY c.id
    ORDER BY c.class_name ASC
");

// Get all trainers for the form
$trainers = $db->fetchAll("
    SELECT t.*, u.full_name 
    FROM trainers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.is_active = 1
    ORDER BY u.full_name ASC
");

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $class_name = trim($_POST['class_name']);
                    $martial_art_type = $_POST['martial_art_type'];
                    $class_type = $_POST['class_type'];
                    $trainer_id = $_POST['trainer_id'];
                    $max_participants = intval($_POST['max_participants']);
                    $duration_minutes = intval($_POST['duration_minutes']);
                    $monthly_fee = floatval($_POST['monthly_fee']);
                    $description = trim($_POST['description']);
                    
                    $db->query("
                        INSERT INTO classes (class_name, martial_art_type, class_type, trainer_id, 
                                           max_participants, duration_minutes, monthly_fee, description) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ", [$class_name, $martial_art_type, $class_type, $trainer_id, 
                        $max_participants, $duration_minutes, $monthly_fee, $description]);
                    
                    $success = "Kelas berhasil ditambahkan!";
                    break;
                    
                case 'toggle_status':
                    $class_id = intval($_POST['class_id']);
                    $db->query("UPDATE classes SET is_active = NOT is_active WHERE id = ?", [$class_id]);
                    $success = "Status kelas berhasil diubah!";
                    break;
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div style="margin-bottom: 30px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Kelola Kelas</h3>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
        </div>
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

<!-- Add New Class Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h4 class="card-title">Tambah Kelas Baru</h4>
    </div>
    
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <div style="padding: 25px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <div class="form-group">
                        <label class="form-label">Nama Kelas *</label>
                        <input type="text" name="class_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Bela Diri *</label>
                        <select name="martial_art_type" class="form-control form-select" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="kickboxing">Kickboxing</option>
                            <option value="boxing">Boxing</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Kelas *</label>
                        <select name="class_type" class="form-control form-select" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="regular">Regular</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <div class="form-group">
                        <label class="form-label">Pelatih *</label>
                        <select name="trainer_id" class="form-control form-select" required>
                            <option value="">-- Pilih Pelatih --</option>
                            <?php foreach ($trainers as $trainer): ?>
                            <option value="<?= $trainer['id'] ?>">
                                <?= htmlspecialchars($trainer['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Maks Peserta</label>
                            <input type="number" name="max_participants" class="form-control" min="1" value="20">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Durasi (menit)</label>
                            <input type="number" name="duration_minutes" class="form-control" min="30" value="60">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Biaya Bulanan (Rp)</label>
                        <input type="number" name="monthly_fee" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi kelas..."></textarea>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Tambah Kelas
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Existing Classes -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Daftar Kelas</h4>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Kelas</th>
                    <th>Pelatih</th>
                    <th>Tipe</th>
                    <th>Peserta</th>
                    <th>Jadwal</th>
                    <th>Biaya</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classes as $class): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($class['class_name']) ?></strong><br>
                        <small class="text-muted"><?= ucfirst($class['class_type']) ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($class['trainer_name']) ?><br>
                        <small class="text-muted"><?= $class['trainer_code'] ?></small>
                    </td>
                    <td>
                        <span class="badge <?= $class['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                            <?= ucfirst($class['martial_art_type']) ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= $class['enrolled_count'] ?></strong>/<?= $class['max_participants'] ?><br>
                        <small class="text-muted">
                            <?= round(($class['enrolled_count'] / $class['max_participants']) * 100) ?>% penuh
                        </small>
                    </td>
                    <td>
                        <span class="badge badge-secondary">
                            <?= $class['schedule_count'] ?> jadwal
                        </span>
                    </td>
                    <td>
                        <strong><?= formatRupiah($class['monthly_fee']) ?></strong>
                    </td>
                    <td>
                        <?php if ($class['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Non-Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $class['is_active'] ? 'btn-warning' : 'btn-success' ?>" 
                                        title="<?= $class['is_active'] ? 'Non-aktifkan' : 'Aktifkan' ?>">
                                    <i class="fas <?= $class['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                </button>
                            </form>
                            
                            <a href="view_class.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-info" title="Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>