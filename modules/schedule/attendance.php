<?php
$page_title = "Absensi Jadwal";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

$schedule_id = intval($_GET['schedule_id']);
$date = $_GET['date'] ?? date('Y-m-d');
$success = '';
$error = '';

// Get schedule information
$schedule = $db->fetch("
    SELECT s.*, c.class_name, c.martial_art_type, c.class_type,
           u.full_name as trainer_name, t.trainer_code
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE s.id = ?
", [$schedule_id]);

if (!$schedule) {
    redirect('modules/schedule/?error=schedule_not_found');
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    try {
        $attendances = $_POST['attendance'] ?? [];
        
        foreach ($attendances as $member_id => $data) {
            $status = $data['status'] ?? 'absent';
            $notes = trim($data['notes'] ?? '');
            
            // Check if attendance already exists
            $existing = $db->fetch("
                SELECT id FROM attendances 
                WHERE member_id = ? AND class_id = ? AND DATE(created_at) = ?
            ", [$member_id, $schedule['class_id'], $date]);
            
            if ($existing) {
                // Update existing
                $db->query("
                    UPDATE attendances 
                    SET status = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$status, $notes, $existing['id']]);
            } else {
                // Insert new
                $db->query("
                    INSERT INTO attendances (member_id, class_id, status, notes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ", [$member_id, $schedule['class_id'], $status, $notes, $date . ' ' . date('H:i:s')]);
            }
        }
        
        $success = "Absensi berhasil disimpan!";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get members enrolled in this class
$members = $db->fetchAll("
    SELECT m.*, u.full_name, u.email,
           a.status as attendance_status, a.notes as attendance_notes
    FROM member_classes mc
    JOIN members m ON mc.member_id = m.id
    JOIN users u ON m.user_id = u.id
    LEFT JOIN attendances a ON (m.id = a.member_id AND a.class_id = ? AND DATE(a.created_at) = ?)
    WHERE mc.class_id = ? AND mc.status = 'active' AND u.is_active = 1
    ORDER BY u.full_name ASC
", [$schedule['class_id'], $date, $schedule['class_id']]);

// Get attendance statistics for this date
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_members,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM member_classes mc
    LEFT JOIN attendances a ON (mc.member_id = a.member_id AND a.class_id = mc.class_id AND DATE(a.created_at) = ?)
    WHERE mc.class_id = ? AND mc.status = 'active'
", [$date, $schedule['class_id']]);

$day_names = [
    'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu', 'thursday' => 'Kamis',
    'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu'
];
?>

<!-- Header -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0; font-size: 1.5rem;">
                    <i class="fas fa-check-square"></i>
                    Absensi Kelas
                </h3>
                <small style="opacity: 0.9;"><?= htmlspecialchars($schedule['class_name']) ?> - <?= formatDate($date) ?></small>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="view_schedule.php?id=<?= $schedule_id ?>" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
                <a href="attendance_today.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                    <i class="fas fa-calendar-day"></i>
                    Absensi Hari Ini
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-check-circle"></i> <?= $success ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<!-- Schedule Info -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="card-body" style="padding: 25px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #1E459F, #2056b8); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                    <h4 style="color: #1E459F; margin: 0;"><?= htmlspecialchars($schedule['class_name']) ?></h4>
                    <div style="color: #6c757d; margin-top: 5px;">
                        <div><i class="fas fa-user-tie" style="margin-right: 5px;"></i> <?= htmlspecialchars($schedule['trainer_name']) ?></div>
                        <div><i class="fas fa-calendar" style="margin-right: 5px;"></i> <?= $day_names[$schedule['day_of_week']] ?? ucfirst($schedule['day_of_week']) ?></div>
                        <div><i class="fas fa-clock" style="margin-right: 5px;"></i> <?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?></div>
                    </div>
                    <div style="margin-top: 10px;">
                        <span class="badge <?= $schedule['martial_art_type'] === 'kickboxing' ? 'badge-info' : ($schedule['martial_art_type'] === 'boxing' ? 'badge-warning' : 'badge-primary') ?>">
                            <?= ucfirst($schedule['martial_art_type']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div class="card" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border: none; border-radius: 10px;">
                <div class="card-body" style="padding: 20px; text-align: center;">
                    <h3 style="margin: 0; font-size: 2rem;"><?= $stats['present_count'] ?></h3>
                    <small style="opacity: 0.9;">Hadir</small>
                </div>
            </div>
            <div class="card" style="background: linear-gradient(135deg, #dc3545, #a71e2a); color: white; border: none; border-radius: 10px;">
                <div class="card-body" style="padding: 20px; text-align: center;">
                    <h3 style="margin: 0; font-size: 2rem;"><?= $stats['absent_count'] ?></h3>
                    <small style="opacity: 0.9;">Tidak Hadir</small>
                </div>
            </div>
        </div>
        
        <div class="card" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; border: none; border-radius: 10px;">
            <div class="card-body" style="padding: 20px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= count($members) ?></h3>
                <small style="opacity: 0.9;">Total Member</small>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Form -->
<div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
    <div class="card-header" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 15px 15px 0 0;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0;">
                <i class="fas fa-list-check"></i>
                Daftar Absensi - <?= formatDate($date) ?>
            </h4>
            <div>
                <button type="button" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white;" onclick="markAllPresent()">
                    <i class="fas fa-check-double"></i>
                    Semua Hadir
                </button>
            </div>
        </div>
    </div>
    
    <?php if (empty($members)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-users" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <h5>Tidak Ada Member</h5>
            <p>Tidak ada member yang terdaftar di kelas ini</p>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="save_attendance" value="1">
            
            <div class="table-responsive">
                <table class="table">
                    <thead style="background: #f8f9fa;">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Member</th>
                            <th style="width: 200px;">Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($members as $member): ?>
                            <tr>
                                <td style="vertical-align: middle;">
                                    <strong><?= $no++ ?></strong>
                                </td>
                                <td style="vertical-align: middle;">
                                    <div>
                                        <strong style="color: #1E459F;"><?= htmlspecialchars($member['full_name']) ?></strong>
                                        <div style="color: #6c757d; font-size: 0.9rem;">
                                            <i class="fas fa-id-badge" style="margin-right: 5px;"></i>
                                            <?= $member['member_code'] ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="vertical-align: middle;">
                                    <select name="attendance[<?= $member['id'] ?>][status]" class="form-control attendance-status" required>
                                        <option value="present" <?= ($member['attendance_status'] ?? '') === 'present' ? 'selected' : '' ?>>
                                            ✓ Hadir
                                        </option>
                                        <option value="late" <?= ($member['attendance_status'] ?? '') === 'late' ? 'selected' : '' ?>>
                                            ⏰ Terlambat
                                        </option>
                                        <option value="absent" <?= ($member['attendance_status'] ?? 'absent') === 'absent' ? 'selected' : '' ?>>
                                            ✗ Tidak Hadir
                                        </option>
                                        <option value="excused" <?= ($member['attendance_status'] ?? '') === 'excused' ? 'selected' : '' ?>>
                                            📋 Izin
                                        </option>
                                    </select>
                                </td>
                                <td style="vertical-align: middle;">
                                    <input type="text" name="attendance[<?= $member['id'] ?>][notes]" 
                                           class="form-control" 
                                           placeholder="Catatan (opsional)..."
                                           value="<?= htmlspecialchars($member['attendance_notes'] ?? '') ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer" style="background: #f8f9fa; border-radius: 0 0 15px 15px; padding: 20px; text-align: center;">
                <button type="submit" class="btn btn-primary btn-lg" style="min-width: 200px;">
                    <i class="fas fa-save"></i>
                    Simpan Absensi
                </button>
                <a href="view_schedule.php?id=<?= $schedule_id ?>" class="btn btn-secondary btn-lg" style="margin-left: 15px; min-width: 150px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Custom CSS -->
<style>
.attendance-status {
    border: 2px solid #e9ecef;
    border-radius: 6px;
    padding: 8px 12px;
}

.attendance-status:focus {
    border-color: #1E459F;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25);
}

.table td, .table th {
    padding: 15px 12px;
    vertical-align: middle;
}

.badge {
    padding: 6px 12px;
    border-radius: 15px;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns: 2fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- JavaScript -->
<script>
function markAllPresent() {
    const selects = document.querySelectorAll('.attendance-status');
    selects.forEach(select => {
        select.value = 'present';
    });
}

// Auto save indication
let saveTimeout;
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('attendance-status')) {
        clearTimeout(saveTimeout);
        
        // Show saving indicator
        const indicator = document.createElement('small');
        indicator.textContent = '● Perubahan belum disimpan';
        indicator.style.color = '#ffc107';
        indicator.style.position = 'fixed';
        indicator.style.top = '10px';
        indicator.style.right = '10px';
        indicator.style.background = 'white';
        indicator.style.padding = '5px 10px';
        indicator.style.borderRadius = '5px';
        indicator.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        indicator.style.zIndex = '1000';
        indicator.id = 'save-indicator';
        
        const existing = document.getElementById('save-indicator');
        if (existing) existing.remove();
        document.body.appendChild(indicator);
        
        saveTimeout = setTimeout(() => {
            indicator.remove();
        }, 3000);
    }
});

// Form submission confirmation
document.querySelector('form').addEventListener('submit', function(e) {
    const presentCount = document.querySelectorAll('.attendance-status option[value="present"]:checked').length;
    const totalCount = document.querySelectorAll('.attendance-status').length;
    
    if (!confirm(`Konfirmasi simpan absensi:\n- ${presentCount} hadir dari ${totalCount} total member\n\nLanjutkan?`)) {
        e.preventDefault();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>