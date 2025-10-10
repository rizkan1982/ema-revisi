<?php
$page_title = "Kelas Pelatih";
require_once '../../includes/header.php';
requireRole(['admin']);

$trainer_id = intval($_GET['id']);

$trainer = $db->fetch("
    SELECT t.*, u.full_name
    FROM trainers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
", [$trainer_id]);

if (!$trainer) {
    redirect('modules/trainers/?error=not_found');
}

// Get trainer's classes with detailed info
$classes = $db->fetchAll("
    SELECT c.*, 
           COUNT(DISTINCT mc.member_id) as enrolled_count,
           COUNT(DISTINCT s.id) as schedule_count,
           GROUP_CONCAT(DISTINCT CONCAT(s.day_of_week, ' ', s.start_time, '-', s.end_time) SEPARATOR ', ') as schedule_info
    FROM classes c
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
    WHERE c.trainer_id = ?
    GROUP BY c.id
    ORDER BY c.is_active DESC, c.class_name ASC
", [$trainer_id]);

$success = '';
$error = '';

// Handle class status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'toggle_status') {
            $class_id = intval($_POST['class_id']);
            
            $db->query("UPDATE classes SET is_active = NOT is_active WHERE id = ? AND trainer_id = ?", [$class_id, $trainer_id]);
            
            $success = "Status kelas berhasil diubah!";
            
            // Refresh data
            $classes = $db->fetchAll("
                SELECT c.*, 
                       COUNT(DISTINCT mc.member_id) as enrolled_count,
                       COUNT(DISTINCT s.id) as schedule_count,
                       GROUP_CONCAT(DISTINCT CONCAT(s.day_of_week, ' ', s.start_time, '-', s.end_time) SEPARATOR ', ') as schedule_info
                FROM classes c
                LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
                LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
                WHERE c.trainer_id = ?
                GROUP BY c.id
                ORDER BY c.is_active DESC, c.class_name ASC
            ", [$trainer_id]);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Kelas Pelatih</h3>
        <div style="display: flex; gap: 10px;">
            <a href="view_trainer.php?id=<?= $trainer_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <a href="../schedule/class_management.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Tambah Kelas Baru
            </a>
        </div>
    </div>
</div>

<!-- Trainer Info Header -->
<div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; background: rgba(255,255,255,0.2);">
            <?= strtoupper(substr($trainer['full_name'], 0, 1)) ?>
        </div>
        <div>
            <h2 style="margin: 0;"><?= htmlspecialchars($trainer['full_name']) ?></h2>
            <div style="margin-top: 8px; opacity: 0.9;">
                <strong>Kode:</strong> <?= $trainer['trainer_code'] ?> |
                <strong>Spesialisasi:</strong> <?= htmlspecialchars($trainer['specialization'] ?: 'Tidak diisi') ?>
            </div>
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

<!-- Classes Overview -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= count($classes) ?></h3>
                <p>Total Kelas</p>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= array_sum(array_column($classes, 'enrolled_count')) ?></h3>
                <p>Total Murid</p>
            </div>
            <div class="stat-icon red">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= array_sum(array_column($classes, 'schedule_count')) ?></h3>
                <p>Total Jadwal</p>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-calendar"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= count(array_filter($classes, function($c) { return $c['is_active']; })) ?></h3>
                <p>Kelas Aktif</p>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Classes Table -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Daftar Kelas</h4>
    </div>
    
    <?php if (empty($classes)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-chalkboard" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <h5>Belum Ada Kelas</h5>
            <p>Pelatih ini belum ditugaskan ke kelas manapun</p>
            <a href="../schedule/class_management.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Buat Kelas Pertama
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Kelas</th>
                        <th>Tipe</th>
                        <th>Kapasitas</th>
                        <th>Enrolled</th>
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
                            <strong style="color: #1E459F;"><?= htmlspecialchars($class['class_name']) ?></strong>
                            <?php if ($class['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($class['description'], 0, 50)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px; flex-direction: column;">
                                <span class="badge <?= $class['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                                    <?= ucfirst($class['martial_art_type']) ?>
                                </span>
                                <span class="badge <?= $class['class_type'] === 'regular' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= ucfirst($class['class_type']) ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <strong><?= $class['max_participants'] ?></strong> orang
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="font-size: 1.2rem; color: #1E459F;"><?= $class['enrolled_count'] ?></strong>
                                <div class="progress" style="flex: 1; height: 6px;">
                                    <div class="progress-bar" style="width: <?= ($class['enrolled_count'] / $class['max_participants']) * 100 ?>%; background: #1E459F;"></div>
                                </div>
                                <small class="text-muted"><?= round(($class['enrolled_count'] / $class['max_participants']) * 100) ?>%</small>
                            </div>
                        </td>
                        <td>
                            <?php if ($class['schedule_count'] > 0): ?>
                                <span class="badge badge-success"><?= $class['schedule_count'] ?> jadwal</span>
                                <br><small class="text-muted" title="<?= htmlspecialchars($class['schedule_info']) ?>" style="cursor: help;">
                                    <?= htmlspecialchars(substr($class['schedule_info'], 0, 20)) ?>...
                                </small>
                            <?php else: ?>
                                <span class="badge badge-warning">Belum ada jadwal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: #FABD32;"><?= formatRupiah($class['monthly_fee']) ?></strong>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                
                                <?php if ($class['is_active']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                    <br>
                                    <button type="submit" class="btn btn-sm btn-warning" style="margin-top: 5px;" onclick="return confirm('Yakin ingin menonaktifkan kelas ini?')">
                                        <i class="fas fa-pause"></i>
                                        Nonaktifkan
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Non-Aktif</span>
                                    <br>
                                    <button type="submit" class="btn btn-sm btn-success" style="margin-top: 5px;" onclick="return confirm('Yakin ingin mengaktifkan kelas ini?')">
                                        <i class="fas fa-play"></i>
                                        Aktifkan
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px; flex-direction: column;">
                                <a href="../schedule/view_class.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-info" title="Detail Kelas">
                                    <i class="fas fa-eye"></i>
                                    Detail
                                </a>
                                <a href="../schedule/add_schedule.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-primary" title="Tambah Jadwal">
                                    <i class="fas fa-calendar-plus"></i>
                                    Jadwal
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>