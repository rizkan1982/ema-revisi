<?php
$page_title = "Detail Jadwal";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

$schedule_id = intval($_GET['id']);

$schedule = $db->fetch("
    SELECT s.*, c.class_name, c.martial_art_type, c.class_type, c.max_participants, c.duration_minutes, c.monthly_fee,
           u.full_name as trainer_name, t.trainer_code, t.specialization,
           COUNT(DISTINCT mc.member_id) as enrolled_count
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    WHERE s.id = ?
    GROUP BY s.id
", [$schedule_id]);

if (!$schedule) {
    redirect('modules/schedule/?error=schedule_not_found');
}

// Get enrolled members for this class
$enrolled_members = $db->fetchAll("
    SELECT m.*, u.full_name, u.email, u.phone, mc.enrollment_date
    FROM member_classes mc
    JOIN members m ON mc.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE mc.class_id = ? AND mc.status = 'active'
    ORDER BY u.full_name ASC
", [$schedule['class_id']]);

// Get recent attendance for this schedule
$recent_attendance = $db->fetchAll("
    SELECT a.*, u.full_name as member_name, m.member_code
    FROM attendances a
    JOIN members m ON a.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE a.class_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
", [$schedule['class_id']]);

// Day names mapping
$day_names = [
    'monday' => 'Senin',
    'tuesday' => 'Selasa',
    'wednesday' => 'Rabu',
    'thursday' => 'Kamis',
    'friday' => 'Jumat',
    'saturday' => 'Sabtu',
    'sunday' => 'Minggu'
];

$capacity_percentage = $schedule['max_participants'] > 0 ? round(($schedule['enrolled_count'] / $schedule['max_participants']) * 100, 1) : 0;
?>

<!-- Header -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0; font-size: 1.5rem;">
                    <i class="fas fa-calendar-alt"></i>
                    Detail Jadwal
                </h3>
                <small style="opacity: 0.9;"><?= htmlspecialchars($schedule['class_name']) ?></small>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="index.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
                <?php if (getUserRole() === 'admin'): ?>
                <a href="edit_schedule.php?id=<?= $schedule_id ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i>
                    Edit
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Information -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Main Info -->
    <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="card-header" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 15px 15px 0 0;">
            <h4 style="margin: 0;">
                <i class="fas fa-info-circle"></i>
                Informasi Jadwal
            </h4>
        </div>
        <div class="card-body" style="padding: 25px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <div>
                    <h6 style="color: #1E459F; margin-bottom: 15px; font-size: 1.1rem;">
                        <i class="fas fa-chalkboard-teacher"></i>
                        Detail Kelas
                    </h6>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Nama Kelas:</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #1E459F;">
                            <strong><?= htmlspecialchars($schedule['class_name']) ?></strong>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Tipe Bela Diri:</label>
                        <span class="badge <?= $schedule['martial_art_type'] === 'kickboxing' ? 'badge-info' : ($schedule['martial_art_type'] === 'boxing' ? 'badge-warning' : 'badge-primary') ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                            <?= ucfirst($schedule['martial_art_type']) ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Tipe Kelas:</label>
                        <span class="badge <?= $schedule['class_type'] === 'regular' ? 'badge-success' : 'badge-warning' ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                            <?= ucfirst(str_replace('_', ' ', $schedule['class_type'])) ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Durasi:</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <i class="fas fa-clock" style="color: #ffc107; margin-right: 5px;"></i>
                            <?= $schedule['duration_minutes'] ?> menit
                        </div>
                    </div>
                </div>
                
                <div>
                    <h6 style="color: #1E459F; margin-bottom: 15px; font-size: 1.1rem;">
                        <i class="fas fa-calendar-week"></i>
                        Jadwal & Waktu
                    </h6>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Hari:</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #28a745;">
                            <strong><?= $day_names[$schedule['day_of_week']] ?? ucfirst($schedule['day_of_week']) ?></strong>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Waktu:</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <i class="fas fa-clock" style="color: #007bff; margin-right: 5px;"></i>
                            <strong><?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?></strong>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Status:</label>
                        <span class="badge <?= $schedule['is_active'] ? 'badge-success' : 'badge-secondary' ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                            <i class="fas <?= $schedule['is_active'] ? 'fa-check-circle' : 'fa-pause-circle' ?>"></i>
                            <?= $schedule['is_active'] ? 'Aktif' : 'Non-Aktif' ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 5px;">Biaya Bulanan:</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #ffc107;">
                            <strong style="color: #FABD32; font-size: 1.1rem;"><?= formatRupiah($schedule['monthly_fee']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Trainer Info -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #f1f3f4;">
                <h6 style="color: #1E459F; margin-bottom: 15px; font-size: 1.1rem;">
                    <i class="fas fa-user-tie"></i>
                    Informasi Pelatih
                </h6>
                <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 15px; border-radius: 8px; border-left: 4px solid #FABD32;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #FABD32, #E1DCCA); display: flex; align-items: center; justify-content: center; font-weight: bold; color: white;">
                            <?= strtoupper(substr($schedule['trainer_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <strong style="font-size: 1.1rem; color: #1E459F;"><?= htmlspecialchars($schedule['trainer_name']) ?></strong>
                            <div style="color: #6c757d; margin-top: 2px;">
                                <span style="margin-right: 15px;">
                                    <i class="fas fa-id-badge" style="margin-right: 5px;"></i>
                                    <?= $schedule['trainer_code'] ?>
                                </span>
                                <?php if ($schedule['specialization']): ?>
                                    <span>
                                        <i class="fas fa-star" style="margin-right: 5px;"></i>
                                        <?= htmlspecialchars($schedule['specialization']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Sidebar -->
    <div>
        <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,123,255,0.3); background: linear-gradient(135deg, #007bff, #0056b3); color: white; margin-bottom: 20px;">
            <div class="card-body" style="padding: 25px; text-align: center;">
                <i class="fas fa-users" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.9;"></i>
                <h3 style="margin: 0; font-size: 2.5rem;"><?= $schedule['enrolled_count'] ?></h3>
                <p style="margin: 8px 0 5px 0; opacity: 0.9;">Member Terdaftar</p>
                <small style="opacity: 0.8;">dari <?= $schedule['max_participants'] ?> maksimal</small>
                <div class="progress" style="height: 8px; margin-top: 15px; background: rgba(255,255,255,0.3);">
                    <div class="progress-bar" style="width: <?= $capacity_percentage ?>%; background: rgba(255,255,255,0.8);"></div>
                </div>
                <small style="opacity: 0.8;"><?= $capacity_percentage ?>% kapasitas</small>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="card-header" style="background: #f8f9fa; border-radius: 15px 15px 0 0;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-bolt"></i>
                    Aksi Cepat
                </h5>
            </div>
            <div class="card-body" style="padding: 20px;">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="attendance.php?schedule_id=<?= $schedule_id ?>&date=<?= date('Y-m-d') ?>" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 8px;">
                        <i class="fas fa-check"></i>
                        Absensi Hari Ini
                    </a>
                    
                    <a href="view_class.php?id=<?= $schedule['class_id'] ?>" class="btn btn-info" style="width: 100%; padding: 12px; border-radius: 8px;">
                        <i class="fas fa-eye"></i>
                        Detail Kelas
                    </a>
                    
                    <?php if (getUserRole() === 'admin'): ?>
                    <a href="edit_schedule.php?id=<?= $schedule_id ?>" class="btn btn-warning" style="width: 100%; padding: 12px; border-radius: 8px;">
                        <i class="fas fa-edit"></i>
                        Edit Jadwal
                    </a>
                    <?php endif; ?>
                    
                    <a href="calendar_view.php" class="btn btn-secondary" style="width: 100%; padding: 12px; border-radius: 8px;">
                        <i class="fas fa-calendar"></i>
                        Lihat Kalender
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Members List -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <!-- Enrolled Members -->
    <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="card-header" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 15px 15px 0 0;">
            <h5 style="margin: 0;">
                <i class="fas fa-users"></i>
                Member Terdaftar (<?= count($enrolled_members) ?>)
            </h5>
        </div>
        <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
            <?php if (empty($enrolled_members)): ?>
                <div style="padding: 40px; text-align: center; color: #6c757d;">
                    <i class="fas fa-user-plus" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <h6>Belum Ada Member</h6>
                    <small>Belum ada member yang terdaftar di kelas ini</small>
                </div>
            <?php else: ?>
                <?php foreach ($enrolled_members as $member): ?>
                    <div style="padding: 15px 20px; border-bottom: 1px solid #f1f3f4;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="color: #1E459F;"><?= htmlspecialchars($member['full_name']) ?></strong>
                                <div style="color: #6c757d; font-size: 0.9rem; margin-top: 2px;">
                                    <i class="fas fa-id-badge" style="margin-right: 5px;"></i>
                                    <?= $member['member_code'] ?>
                                    <span style="margin-left: 15px;">
                                        <i class="fas fa-calendar-plus" style="margin-right: 5px;"></i>
                                        <?= formatDate($member['enrollment_date']) ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <a href="../members/view.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-info" title="Detail Member">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Attendance -->
    <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="card-header" style="background: linear-gradient(135deg, #ffc107, #d39e00); color: white; border-radius: 15px 15px 0 0;">
            <h5 style="margin: 0;">
                <i class="fas fa-clock"></i>
                Absensi Terbaru
            </h5>
        </div>
        <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
            <?php if (empty($recent_attendance)): ?>
                <div style="padding: 40px; text-align: center; color: #6c757d;">
                    <i class="fas fa-calendar-check" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <h6>Belum Ada Absensi</h6>
                    <small>Belum ada riwayat absensi untuk kelas ini</small>
                </div>
            <?php else: ?>
                <?php foreach ($recent_attendance as $attendance): ?>
                    <div style="padding: 15px 20px; border-bottom: 1px solid #f1f3f4;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="color: #1E459F;"><?= htmlspecialchars($attendance['member_name']) ?></strong>
                                <div style="color: #6c757d; font-size: 0.9rem; margin-top: 2px;">
                                    <i class="fas fa-id-badge" style="margin-right: 5px;"></i>
                                    <?= $attendance['member_code'] ?>
                                    <span style="margin-left: 15px;">
                                        <i class="fas fa-clock" style="margin-right: 5px;"></i>
                                        <?= formatDate($attendance['created_at']) ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <?php
                                $status_class = 'badge-secondary';
                                switch($attendance['status']) {
                                    case 'present': $status_class = 'badge-success'; break;
                                    case 'late': $status_class = 'badge-warning'; break;
                                    case 'absent': $status_class = 'badge-danger'; break;
                                    case 'excused': $status_class = 'badge-info'; break;
                                }
                                ?>
                                <span class="badge <?= $status_class ?>" style="padding: 4px 8px;">
                                    <?= ucfirst($attendance['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.badge {
    border-radius: 15px;
    font-size: 0.85rem;
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns: 2fr 1fr"], 
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>