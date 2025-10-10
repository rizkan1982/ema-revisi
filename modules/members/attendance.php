<?php
$page_title = "Absensi Member";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

$member_id = intval($_GET['id']);
$success = '';
$error = '';

$member = $db->fetch("
    SELECT m.*, u.full_name
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.id = ?
", [$member_id]);

if (!$member) {
    redirect('modules/members/?error=not_found');
}

// Handle quick attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_attendance'])) {
    try {
        $today = date('Y-m-d');
        $current_time = date('H:i:s');
        
        // Check if already attended today
        $existing = $db->fetch("
            SELECT id FROM attendances 
            WHERE member_id = ? AND DATE(created_at) = ?
        ", [$member_id, $today]);
        
        if ($existing) {
            $error = "Member sudah melakukan absensi hari ini!";
        } else {
            // Insert new attendance record
            $db->query("
                INSERT INTO attendances (member_id, class_id, status, created_at, updated_at) 
                VALUES (?, 1, 'present', NOW(), NOW())
            ", [$member_id]);
            
            $success = "Absensi berhasil dicatat untuk hari ini!";
            
            // Redirect to attendance today page
            echo "<script>
                setTimeout(function() {
                    window.open('../schedule/attendance_today.php', '_blank');
                }, 1500);
            </script>";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get attendance history - using attendances table
$attendances = $db->fetchAll("
    SELECT a.*, 
           COALESCE(c.class_name, 'General Class') as class_name,
           COALESCE(u.full_name, 'System') as trainer_name,
           DATE(a.created_at) as attendance_date,
           TIME(a.created_at) as check_in_time,
           NULL as check_out_time
    FROM attendances a
    LEFT JOIN classes c ON a.class_id = c.id
    LEFT JOIN trainers t ON c.trainer_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE a.member_id = ?
    ORDER BY a.created_at DESC
    LIMIT 50
", [$member_id]);

// Get attendance statistics
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendances 
    WHERE member_id = ?
", [$member_id]);

$attendance_rate = $stats['total_sessions'] > 0 ? 
    round(($stats['present_count'] / $stats['total_sessions']) * 100, 1) : 0;
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Absensi Member</h3>
        <div style="display: flex; gap: 10px;">
            <a href="view.php?id=<?= $member_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <a href="../schedule/attendance_today.php" class="btn btn-info" target="_blank">
                <i class="fas fa-calendar-day"></i>
                Lihat Absensi Hari Ini
            </a>
            <a href="export_attendance.php?member_id=<?= $member_id ?>" class="btn btn-success">
                <i class="fas fa-file-excel"></i>
                Export Excel
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

<!-- Member Info -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; border-left: 5px solid #1E459F;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; background: linear-gradient(135deg, #1E459F, #CF2A2A);">
                <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
            </div>
            <div>
                <h3 style="color: #1E459F; margin: 0;"><?= htmlspecialchars($member['full_name']) ?></h3>
                <div style="color: #6c757d; font-size: 1.1rem; margin-top: 5px;">
                    <strong>Kode Member:</strong> <?= $member['member_code'] ?>
                </div>
                <div style="margin-top: 8px;">
                    <span class="badge <?= $member['martial_art_type'] === 'kickboxing' ? 'badge-info' : ($member['martial_art_type'] === 'boxing' ? 'badge-warning' : 'badge-primary') ?>">
                        <?= ucfirst($member['martial_art_type']) ?>
                    </span>
                    <span class="badge <?= $member['class_type'] === 'regular' ? 'badge-success' : 'badge-warning' ?>">
                        <?= ucfirst(str_replace('_', ' ', $member['class_type'])) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Quick Attendance Button -->
        <div style="text-align: center;">
            <form method="POST" action="" style="display: inline-block;">
                <input type="hidden" name="quick_attendance" value="1">
                <button type="submit" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #28a745, #20c997); border: none; padding: 15px 25px; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);" onclick="return confirm('Konfirmasi absensi untuk hari ini?')">
                    <i class="fas fa-user-check" style="font-size: 1.2rem; margin-right: 8px;"></i>
                    <div>
                        <div style="font-size: 1.1rem;">ABSEN SEKARANG</div>
                        <small style="opacity: 0.9; font-size: 0.85rem;"><?= date('d F Y') ?></small>
                    </div>
                </button>
            </form>
            <div style="margin-top: 10px; font-size: 0.9rem; color: #6c757d;">
                <i class="fas fa-clock"></i>
                Waktu: <?= date('H:i:s') ?>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Statistics -->
<div class="stats-grid" style="margin-bottom: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
    <div class="stat-card" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(0,123,255,0.3);">
        <div class="stat-content">
            <div class="stat-info">
                <h3 style="font-size: 2.5rem; margin: 0;"><?= $stats['total_sessions'] ?></h3>
                <p style="margin: 8px 0 0 0; opacity: 0.9;">Total Sesi</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8; margin-top: 10px;">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(40,167,69,0.3);">
        <div class="stat-content">
            <div class="stat-info">
                <h3 style="font-size: 2.5rem; margin: 0;"><?= $stats['present_count'] ?></h3>
                <p style="margin: 8px 0 0 0; opacity: 0.9;">Hadir</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8; margin-top: 10px;">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #ffc107, #d39e00); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(255,193,7,0.3);">
        <div class="stat-content">
            <div class="stat-info">
                <h3 style="font-size: 2.5rem; margin: 0;"><?= $stats['late_count'] ?></h3>
                <p style="margin: 8px 0 0 0; opacity: 0.9;">Terlambat</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8; margin-top: 10px;">
                <i class="fas fa-clock"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1, #59359a); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 8px 25px rgba(111,66,193,0.3);">
        <div class="stat-content">
            <div class="stat-info">
                <h3 style="font-size: 2.5rem; margin: 0;"><?= $attendance_rate ?>%</h3>
                <p style="margin: 8px 0 0 0; opacity: 0.9;">Tingkat Kehadiran</p>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.8; margin-top: 10px;">
                <i class="fas fa-chart-pie"></i>
            </div>
        </div>
    </div>
</div>

<!-- Attendance History -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Riwayat Kehadiran (50 Terakhir)</h4>
    </div>
    
    <?php if (empty($attendances)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-calendar-times" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <h5>Belum Ada Data Absensi</h5>
            <p>Member ini belum memiliki riwayat kehadiran</p>
            <div style="margin-top: 20px;">
                <p style="color: #1E459F; font-weight: 600;">Gunakan tombol "ABSEN SEKARANG" untuk memulai absensi!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Pelatih</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendances as $attendance): ?>
                    <tr>
                        <td>
                            <strong><?= formatDate($attendance['attendance_date']) ?></strong>
                            <br>
                            <small class="text-muted"><?= date('l', strtotime($attendance['attendance_date'])) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($attendance['class_name']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($attendance['trainer_name']) ?>
                        </td>
                        <td>
                            <?= $attendance['check_in_time'] ? date('H:i', strtotime($attendance['check_in_time'])) : '-' ?>
                        </td>
                        <td>
                            <?= $attendance['check_out_time'] ? date('H:i', strtotime($attendance['check_out_time'])) : '-' ?>
                        </td>
                        <td>
                            <?php
                            $badge_class = '';
                            $icon = '';
                            switch ($attendance['status']) {
                                case 'present':
                                    $badge_class = 'badge-success';
                                    $icon = 'fas fa-check-circle';
                                    break;
                                case 'late':
                                    $badge_class = 'badge-warning';
                                    $icon = 'fas fa-clock';
                                    break;
                                case 'absent':
                                    $badge_class = 'badge-danger';
                                    $icon = 'fas fa-times-circle';
                                    break;
                                default:
                                    $badge_class = 'badge-secondary';
                                    $icon = 'fas fa-question-circle';
                            }
                            ?>
                            <span class="badge <?= $badge_class ?>">
                                <i class="<?= $icon ?>"></i>
                                <?= ucfirst($attendance['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $attendance['notes'] ? htmlspecialchars($attendance['notes']) : '-' ?>
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
.btn:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.stat-card {
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr !important;
    }
    
    .btn-lg {
        padding: 12px 20px !important;
        font-size: 0.9rem !important;
    }
}
</style>

<!-- JavaScript -->
<script>
// Auto dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Real-time clock update
setInterval(function() {
    const timeElement = document.querySelector('div[style*="Waktu:"]');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toTimeString().slice(0, 8);
        timeElement.innerHTML = '<i class="fas fa-clock"></i> Waktu: ' + timeString;
    }
}, 1000);
</script>

<?php require_once '../../includes/footer.php'; ?>