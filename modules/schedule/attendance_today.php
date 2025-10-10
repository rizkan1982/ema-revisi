<?php
$page_title = "Absensi Hari Ini";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

$today = date('Y-m-d');
$success = '';
$error = '';

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'mark_attendance') {
            $member_id = intval($_POST['member_id']);
            $status = $_POST['status'];
            $check_in_time = $_POST['check_in_time'] ?: date('H:i:s');
            $notes = trim($_POST['notes']);
            
            // Check if already marked today
            $existing = $db->fetch("
                SELECT id FROM attendances 
                WHERE member_id = ? AND DATE(created_at) = ?
            ", [$member_id, $today]);
            
            if ($existing) {
                $db->query("
                    UPDATE attendances 
                    SET status = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$status, $notes, $existing['id']]);
                $success = "Absensi berhasil diperbarui!";
            } else {
                $db->query("
                    INSERT INTO attendances (member_id, class_id, status, notes, created_at, updated_at) 
                    VALUES (?, 1, ?, ?, NOW(), NOW())
                ", [$member_id, $status, $notes]);
                $success = "Absensi berhasil dicatat!";
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all active members for quick attendance
$all_members = $db->fetchAll("
    SELECT m.id, m.member_code, u.full_name, m.martial_art_type, m.class_type
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE u.is_active = 1
    ORDER BY u.full_name ASC
");

// Get attendance records for today
$attendance_records = $db->fetchAll("
    SELECT a.*, m.member_code, u.full_name as member_name,
           TIME(a.created_at) as check_in_time
    FROM attendances a
    JOIN members m ON a.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE DATE(a.created_at) = ?
    ORDER BY a.created_at DESC
", [$today]);

// Get statistics for today
$today_stats = $db->fetch("
    SELECT 
        COUNT(*) as total_attendances,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendances 
    WHERE DATE(created_at) = ?
", [$today]);
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white;">
        <h3 class="card-title">
            <i class="fas fa-calendar-check"></i>
            Absensi Hari Ini - <?= date('d F Y') ?>
        </h3>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div style="color: white; opacity: 0.9; margin-right: 15px;">
                <i class="fas fa-clock"></i>
                <span id="current-time"><?= date('H:i:s') ?></span>
            </div>
            <a href="../members/index.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                <i class="fas fa-users"></i>
                Daftar Member
            </a>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-check-circle"></i>
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-exclamation-circle"></i>
        <?= $error ?>
    </div>
<?php endif; ?>

<!-- Today's Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(0,123,255,0.3);">
        <i class="fas fa-users" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
        <h3 style="margin: 0; font-size: 2.5rem;"><?= $today_stats['total_attendances'] ?></h3>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Total Absensi</p>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(40,167,69,0.3);">
        <i class="fas fa-check-circle" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
        <h3 style="margin: 0; font-size: 2.5rem;"><?= $today_stats['present_count'] ?></h3>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Hadir</p>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #ffc107, #d39e00); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(255,193,7,0.3);">
        <i class="fas fa-clock" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
        <h3 style="margin: 0; font-size: 2.5rem;"><?= $today_stats['late_count'] ?></h3>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Terlambat</p>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #dc3545, #a71e2a); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(220,53,69,0.3);">
        <i class="fas fa-times-circle" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8;"></i>
        <h3 style="margin: 0; font-size: 2.5rem;"><?= $today_stats['absent_count'] ?></h3>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Tidak Hadir</p>
    </div>
</div>

<!-- Quick Attendance Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white;">
        <h4 class="card-title" style="margin: 0;">
            <i class="fas fa-user-plus"></i>
            Tambah Absensi Cepat
        </h4>
    </div>
    <div class="card-body" style="padding: 25px;">
        <form method="POST" action="">
            <input type="hidden" name="action" value="mark_attendance">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr auto auto; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Pilih Member</label>
                    <select name="member_id" class="form-control form-select" required>
                        <option value="">-- Pilih Member --</option>
                        <?php foreach ($all_members as $member): ?>
                            <option value="<?= $member['id'] ?>">
                                <?= htmlspecialchars($member['full_name']) ?> 
                                (<?= $member['member_code'] ?>) - 
                                <?= ucfirst($member['martial_art_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control form-select" required>
                        <option value="present">Hadir</option>
                        <option value="late">Terlambat</option>
                        <option value="absent">Tidak Hadir</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Waktu</label>
                    <input type="time" name="check_in_time" class="form-control" value="<?= date('H:i') ?>">
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 12px 20px;">
                    <i class="fas fa-plus"></i>
                    Catat Absensi
                </button>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Keterangan (Opsional)</label>
                <input type="text" name="notes" class="form-control" placeholder="Catatan tambahan...">
            </div>
        </form>
    </div>
</div>

<!-- Today's Attendance Records -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Daftar Absensi Hari Ini</h4>
        <button onclick="location.reload()" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i>
            Refresh
        </button>
    </div>
    
    <?php if (empty($attendance_records)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-user-check" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <h5>Belum Ada Absensi Hari Ini</h5>
            <p>Gunakan form di atas untuk menambah absensi member</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>No</th>
                        <th>Waktu</th>
                        <th>Member</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($attendance_records as $record): ?>
                    <tr>
                        <td><strong><?= $no++ ?></strong></td>
                        <td>
                            <strong><?= date('H:i', strtotime($record['check_in_time'])) ?></strong>
                            <br>
                            <small class="text-muted"><?= date('s') ?>s</small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($record['member_name']) ?></strong>
                        </td>
                        <td>
                            <span style="font-family: monospace; background: #f8f9fa; padding: 2px 6px; border-radius: 4px;">
                                <?= $record['member_code'] ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $badge_class = '';
                            $icon = '';
                            switch ($record['status']) {
                                case 'present':
                                    $badge_class = 'badge-success';
                                    $icon = 'fas fa-check-circle';
                                    $text = 'Hadir';
                                    break;
                                case 'late':
                                    $badge_class = 'badge-warning';
                                    $icon = 'fas fa-clock';
                                    $text = 'Terlambat';
                                    break;
                                case 'absent':
                                    $badge_class = 'badge-danger';
                                    $icon = 'fas fa-times-circle';
                                    $text = 'Tidak Hadir';
                                    break;
                                default:
                                    $badge_class = 'badge-secondary';
                                    $icon = 'fas fa-question-circle';
                                    $text = ucfirst($record['status']);
                            }
                            ?>
                            <span class="badge <?= $badge_class ?>" style="padding: 6px 12px; font-size: 0.85rem;">
                                <i class="<?= $icon ?>"></i>
                                <?= $text ?>
                            </span>
                        </td>
                        <td>
                            <?= $record['notes'] ? htmlspecialchars($record['notes']) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Custom CSS -->
<style>
.stat-card {
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.form-control:focus, .form-select:focus {
    border-color: #1E459F;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25);
}

.badge {
    border-radius: 20px;
}

.table-hover tbody tr:hover {
    background-color: rgba(30, 69, 159, 0.05);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns: 2fr 1fr auto auto"] {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="grid-template-columns: 2fr 1fr auto auto"] > * {
        margin-bottom: 10px;
    }
}
</style>

<!-- JavaScript -->
<script>
// Real-time clock update
setInterval(function() {
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        const now = new Date();
        timeElement.textContent = now.toTimeString().slice(0, 8);
    }
}, 1000);

// Auto refresh page every 2 minutes
setInterval(function() {
    location.reload();
}, 120000);

// Auto dismiss alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const memberSelect = document.querySelector('select[name="member_id"]');
    const statusSelect = document.querySelector('select[name="status"]');
    
    if (!memberSelect.value || !statusSelect.value) {
        e.preventDefault();
        alert('Mohon pilih member dan status!');
        return false;
    }
    
    // Loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
});
</script>

<?php require_once '../../includes/footer.php'; ?>