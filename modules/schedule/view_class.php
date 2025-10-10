<?php
$page_title = "Detail Kelas";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

$class_id = intval($_GET['id']);

$class = $db->fetch("
    SELECT c.*, u.full_name as trainer_name, t.trainer_code, t.specialization,
           COUNT(DISTINCT mc.member_id) as enrolled_count,
           COUNT(DISTINCT s.id) as schedule_count
    FROM classes c
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
    WHERE c.id = ?
    GROUP BY c.id
", [$class_id]);

if (!$class) {
    redirect('modules/schedule/class_management.php?error=not_found');
}

// Get class schedules
$schedules = $db->fetchAll("
    SELECT * FROM schedules 
    WHERE class_id = ? AND is_active = 1
    ORDER BY 
        CASE day_of_week
            WHEN 'monday' THEN 1
            WHEN 'tuesday' THEN 2
            WHEN 'wednesday' THEN 3
            WHEN 'thursday' THEN 4
            WHEN 'friday' THEN 5
            WHEN 'saturday' THEN 6
            WHEN 'sunday' THEN 7
        END,
        start_time
", [$class_id]);

// Get enrolled members
$members = $db->fetchAll("
    SELECT m.*, u.full_name, u.email, u.phone, mc.enrollment_date
    FROM member_classes mc
    JOIN members m ON mc.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE mc.class_id = ? AND mc.status = 'active'
    ORDER BY mc.enrollment_date DESC
", [$class_id]);
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Detail Kelas</h3>
        <div style="display: flex; gap: 10px;">
            <a href="class_management.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <a href="edit_class.php?id=<?= $class_id ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i>
                Edit Kelas
            </a>
        </div>
    </div>
</div>

<!-- Class Information -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Informasi Kelas</h4>
        </div>
        
        <div style="padding: 25px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Nama Kelas</label>
                        <div style="font-size: 1.3rem; font-weight: bold; color: #1E459F; margin-top: 5px;">
                            <?= htmlspecialchars($class['class_name']) ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Pelatih</label>
                        <div style="margin-top: 5px;">
                            <strong style="color: #1E459F;"><?= htmlspecialchars($class['trainer_name']) ?></strong><br>
                            <small class="text-muted"><?= $class['trainer_code'] ?></small><br>
                            <?php if ($class['specialization']): ?>
                                <small class="text-muted">Spesialisasi: <?= htmlspecialchars($class['specialization']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Tipe</label>
                        <div style="margin-top: 5px; display: flex; gap: 10px;">
                            <span class="badge <?= $class['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                                <?= ucfirst($class['martial_art_type']) ?>
                            </span>
                            <span class="badge <?= $class['class_type'] === 'regular' ? 'badge-success' : 'badge-warning' ?>">
                                <?= ucfirst($class['class_type']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Kapasitas</label>
                        <div style="margin-top: 5px;">
                            <div style="font-size: 1.5rem; font-weight: bold;">
                                <span style="color: #1E459F;"><?= $class['enrolled_count'] ?></span>
                                <span style="color: #6c757d;"> / <?= $class['max_participants'] ?></span>
                            </div>
                            <div class="progress" style="height: 8px; margin-top: 5px;">
                                <div class="progress-bar" style="width: <?= ($class['enrolled_count'] / $class['max_participants']) * 100 ?>%; background: #1E459F;"></div>
                            </div>
                            <small class="text-muted"><?= round(($class['enrolled_count'] / $class['max_participants']) * 100) ?>% terisi</small>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Durasi</label>
                        <div style="font-size: 1.2rem; color: #CF2A2A; margin-top: 5px;">
                            <i class="fas fa-clock"></i>
                            <?= $class['duration_minutes'] ?> menit
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Biaya Bulanan</label>
                        <div style="font-size: 1.3rem; font-weight: bold; color: #FABD32; margin-top: 5px;">
                            <?= formatRupiah($class['monthly_fee']) ?>
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Status</label>
                        <div style="margin-top: 5px;">
                            <?php if ($class['is_active']): ?>
                                <span class="badge badge-success" style="font-size: 1rem; padding: 8px 15px;">
                                    <i class="fas fa-check-circle"></i>
                                    Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="font-size: 1rem; padding: 8px 15px;">
                                    <i class="fas fa-pause-circle"></i>
                                    Non-Aktif
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($class['description']): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; text-transform: uppercase;">Deskripsi</label>
                    <div style="margin-top: 10px; color: #495057; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($class['description'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div>
        <div class="stat-card blue" style="margin-bottom: 20px;">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $class['enrolled_count'] ?></h3>
                    <p>Member Terdaftar</p>
                </div>
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card red" style="margin-bottom: 20px;">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $class['schedule_count'] ?></h3>
                    <p>Jadwal Aktif</p>
                </div>
                <div class="stat-icon red">
                    <i class="fas fa-calendar"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card yellow">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= round(($class['enrolled_count'] / $class['max_participants']) * 100) ?>%</h3>
                    <p>Kapasitas Terisi</p>
                </div>
                <div class="stat-icon yellow">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedules -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h4 class="card-title">Jadwal Kelas</h4>
        <a href="add_schedule.php?class_id=<?= $class_id ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i>
            Tambah Jadwal
        </a>
    </div>
    
    <?php if (empty($schedules)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
            <h5>Belum Ada Jadwal</h5>
            <p>Kelas ini belum memiliki jadwal yang aktif</p>
            <a href="add_schedule.php?class_id=<?= $class_id ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Tambah Jadwal Pertama
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Durasi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td>
                            <strong><?= ucfirst($schedule['day_of_week']) ?></strong>
                        </td>
                        <td><?= date('H:i', strtotime($schedule['start_time'])) ?></td>
                        <td><?= date('H:i', strtotime($schedule['end_time'])) ?></td>
                        <td>
                            <?php
                            $start = new DateTime($schedule['start_time']);
                            $end = new DateTime($schedule['end_time']);
                            $duration = $start->diff($end);
                            echo $duration->format('%h jam %i menit');
                            ?>
                        </td>
                        <td>
                            <?php if ($schedule['is_active']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Non-Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?= $schedule['id'] ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Enrolled Members -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Member Terdaftar</h4>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-success btn-sm" onclick="exportMembers()">
                <i class="fas fa-file-excel"></i>
                Export
            </button>
            <button class="btn btn-primary btn-sm" onclick="addMemberToClass()">
                <i class="fas fa-user-plus"></i>
                Tambah Member
            </button>
        </div>
    </div>
    
    <?php if (empty($members)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
            <h5>Belum Ada Member</h5>
            <p>Belum ada member yang terdaftar di kelas ini</p>
            <button class="btn btn-primary" onclick="addMemberToClass()">
                <i class="fas fa-user-plus"></i>
                Tambah Member Pertama
            </button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Kontak</th>
                        <th>Tgl Bergabung</th>
                        <th>Lama Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($member['full_name']) ?></strong><br>
                                <small class="text-muted"><?= $member['member_code'] ?></small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($member['email']) ?><br>
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($member['phone'] ?: 'Tidak diisi') ?>
                            </div>
                        </td>
                        <td>
                            <?= formatDate($member['enrollment_date']) ?>
                        </td>
                        <td>
                            <?php
                            $joined = new DateTime($member['enrollment_date']);
                            $now = new DateTime();
                            $duration = $joined->diff($now);
                            
                            if ($duration->days < 30) {
                                echo $duration->days . ' hari';
                            } elseif ($duration->days < 365) {
                                echo floor($duration->days / 30) . ' bulan';
                            } else {
                                echo $duration->y . ' tahun ' . floor(($duration->days % 365) / 30) . ' bulan';
                            }
                            ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="../members/view.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-info" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="removeMemberFromClass(<?= $member['id'] ?>)" title="Keluarkan dari Kelas">
                                    <i class="fas fa-user-times"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteSchedule(scheduleId) {
    if (confirm('Yakin ingin menghapus jadwal ini?')) {
        fetch(`delete_schedule.php?id=${scheduleId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}

function removeMemberFromClass(memberId) {
    if (confirm('Yakin ingin mengeluarkan member ini dari kelas?')) {
        fetch(`remove_member_from_class.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                member_id: memberId, 
                class_id: <?= $class_id ?> 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function addMemberToClass() {
    window.location.href = `add_member_to_class.php?class_id=<?= $class_id ?>`;
}

function exportMembers() {
    window.open(`export_class_members.php?class_id=<?= $class_id ?>`);
}
</script>

<?php require_once '../../includes/footer.php'; ?>