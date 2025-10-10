<?php
$page_title = "Jadwal Pelatih";
require_once '../../includes/header.php';
requireRole(['admin']);

// Get all trainers
$trainers = $db->fetchAll("
    SELECT t.*, u.full_name
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    WHERE u.is_active = 1
    ORDER BY u.full_name ASC
");

// Get selected trainer's schedule
$selected_trainer = $_GET['trainer_id'] ?? null;
$trainer_schedules = [];
$trainer_info = null;

if ($selected_trainer) {
    $trainer_info = $db->fetch("
        SELECT t.*, u.full_name
        FROM trainers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ", [$selected_trainer]);
    
    $trainer_schedules = $db->fetchAll("
        SELECT s.*, c.class_name, c.martial_art_type, c.max_participants,
               COUNT(mc.member_id) as enrolled_count
        FROM schedules s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
        WHERE c.trainer_id = ? AND s.is_active = 1
        GROUP BY s.id
        ORDER BY 
            CASE s.day_of_week
                WHEN 'monday' THEN 1
                WHEN 'tuesday' THEN 2
                WHEN 'wednesday' THEN 3
                WHEN 'thursday' THEN 4
                WHEN 'friday' THEN 5
                WHEN 'saturday' THEN 6
                WHEN 'sunday' THEN 7
            END,
            s.start_time
    ", [$selected_trainer]);
}

$days = [
    'monday' => 'Senin',
    'tuesday' => 'Selasa',
    'wednesday' => 'Rabu',
    'thursday' => 'Kamis',
    'friday' => 'Jumat',
    'saturday' => 'Sabtu',
    'sunday' => 'Minggu'
];
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Jadwal Pelatih</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>
</div>

<!-- Trainer Selection -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <form method="GET" action="">
            <div style="display: flex; gap: 15px; align-items: end;">
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label class="form-label">Pilih Pelatih</label>
                    <select name="trainer_id" class="form-control form-select" onchange="this.form.submit()">
                        <option value="">-- Pilih Pelatih --</option>
                        <?php foreach ($trainers as $trainer): ?>
                        <option value="<?= $trainer['id'] ?>" <?= $selected_trainer == $trainer['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($trainer['full_name']) ?> (<?= $trainer['trainer_code'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($selected_trainer): ?>
                <div class="btn-toolbar">
                    <a href="?trainer_id=<?= $selected_trainer ?>&export=excel" class="btn btn-success" onclick="exportSchedule('excel')">
                        <i class="fas fa-file-excel"></i>
                        Excel
                    </a>
                    <a href="?trainer_id=<?= $selected_trainer ?>&export=pdf" class="btn btn-danger" onclick="exportSchedule('pdf')">
                        <i class="fas fa-file-pdf"></i>
                        PDF
                    </a>
                    <button type="button" class="btn btn-info" onclick="exportSchedule('csv')">
                        <i class="fas fa-file-csv"></i>
                        CSV
                    </button>
                    <button type="button" class="btn btn-warning" onclick="printSchedule()">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($trainer_info): ?>
<!-- Trainer Info -->
<div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 2rem; background: rgba(255,255,255,0.2);">
            <?= strtoupper(substr($trainer_info['full_name'], 0, 1)) ?>
        </div>
        <div>
            <h2 style="margin: 0;"><?= htmlspecialchars($trainer_info['full_name']) ?></h2>
            <div style="margin-top: 8px; opacity: 0.9;">
                <strong>Kode:</strong> <?= $trainer_info['trainer_code'] ?> |
                <strong>Pengalaman:</strong> <?= $trainer_info['experience_years'] ?> tahun |
                <strong>Spesialisasi:</strong> <?= htmlspecialchars($trainer_info['specialization'] ?: 'Tidak diisi') ?>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Schedule Grid -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Jadwal Mingguan - <?= htmlspecialchars($trainer_info['full_name']) ?></h4>
        <div style="display: flex; gap: 10px;">
            <span class="badge badge-info"><?= count($trainer_schedules) ?> jadwal aktif</span>
            <span class="badge badge-success"><?= array_sum(array_column($trainer_schedules, 'enrolled_count')) ?> total murid</span>
        </div>
    </div>
    
    <?php if (empty($trainer_schedules)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-calendar-times" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <h5>Belum Ada Jadwal</h5>
            <p>Pelatih ini belum memiliki jadwal mengajar yang aktif</p>
            <a href="../schedule/class_management.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Tambah Kelas & Jadwal
            </a>
        </div>
    <?php else: ?>
        <!-- Schedule Grid -->
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #dee2e6; margin: 20px;">
            <?php foreach ($days as $day_en => $day_id): ?>
                <div style="background: white; padding: 15px; min-height: 200px;">
                    <div style="text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #1E459F;">
                        <h6 style="color: #1E459F; margin: 0;"><?= $day_id ?></h6>
                    </div>
                    
                    <?php
                    $day_schedules = array_filter($trainer_schedules, function($s) use ($day_en) {
                        return $s['day_of_week'] === $day_en;
                    });
                    ?>
                    
                    <?php foreach ($day_schedules as $schedule): ?>
                        <div style="margin-bottom: 12px; padding: 10px; border-radius: 6px; border-left: 4px solid <?= $schedule['martial_art_type'] === 'kickboxing' ? '#1E459F' : '#CF2A2A' ?>; background: <?= $schedule['martial_art_type'] === 'kickboxing' ? 'rgba(30, 69, 159, 0.05)' : 'rgba(207, 42, 42, 0.05)' ?>;">
                            <div style="font-size: 0.85rem; font-weight: bold; color: #1E459F; margin-bottom: 5px;">
                                <?= htmlspecialchars($schedule['class_name']) ?>
                            </div>
                            
                            <div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 5px;">
                                <i class="fas fa-clock"></i>
                                <?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="badge <?= $schedule['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>" style="font-size: 0.65rem;">
                                    <?= strtoupper($schedule['martial_art_type']) ?>
                                </span>
                                
                                <span style="font-size: 0.75rem; color: #28a745;">
                                    <i class="fas fa-users"></i>
                                    <?= $schedule['enrolled_count'] ?>/<?= $schedule['max_participants'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($day_schedules)): ?>
                        <div style="text-align: center; color: #6c757d; margin-top: 50px; opacity: 0.5;">
                            <i class="fas fa-moon"></i><br>
                            <small>Free</small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Schedule List -->
        <div class="card" style="margin: 20px;">
            <div class="card-header">
                <h5 class="card-title">Detail Jadwal</h5>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Waktu</th>
                            <th>Kelas</th>
                            <th>Tipe</th>
                            <th>Kapasitas</th>
                            <th>Peserta</th>
                            <th>Utilization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trainer_schedules as $schedule): ?>
                        <tr>
                            <td>
                                <strong><?= $days[$schedule['day_of_week']] ?></strong>
                            </td>
                            <td>
                                <?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($schedule['class_name']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $schedule['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                                    <?= ucfirst($schedule['martial_art_type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $schedule['max_participants'] ?></strong>
                            </td>
                            <td>
                                <strong><?= $schedule['enrolled_count'] ?></strong>
                            </td>
                            <td>
                                <?php $utilization = ($schedule['enrolled_count'] / $schedule['max_participants']) * 100; ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="progress" style="flex: 1; height: 8px;">
                                        <div class="progress-bar" style="width: <?= $utilization ?>%; background: <?= $utilization >= 80 ? '#28a745' : ($utilization >= 50 ? '#ffc107' : '#dc3545') ?>;"></div>
                                    </div>
                                    <span style="font-size: 0.85rem; color: <?= $utilization >= 80 ? '#28a745' : ($utilization >= 50 ? '#ffc107' : '#dc3545') ?>;">
                                        <?= round($utilization) ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- No Trainer Selected -->
<div style="padding: 80px; text-align: center; color: #6c757d;">
    <i class="fas fa-user-tie" style="font-size: 5rem; margin-bottom: 25px; opacity: 0.3;"></i>
    <h4>Pilih Pelatih</h4>
    <p>Pilih pelatih dari dropdown di atas untuk melihat jadwal mengajarnya</p>
</div>
<?php endif; ?>

<script>
// Export functions
function exportSchedule(format) {
    const trainerId = <?= $selected_trainer ?: 'null' ?>;
    
    if (!trainerId) {
        alert('Pilih pelatih terlebih dahulu!');
        return;
    }
    
    const url = `export_trainer_schedule.php?trainer_id=${trainerId}&format=${format}`;
    
    // Show loading
    event.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    event.target.disabled = true;
    
    // Open export
    window.open(url, '_blank');
    
    // Reset button after delay
    setTimeout(() => {
        event.target.innerHTML = `<i class="fas fa-file-${format}"></i> ${format.toUpperCase()}`;
        event.target.disabled = false;
    }, 2000);
}

function printSchedule() {
    const trainerId = <?= $selected_trainer ?: 'null' ?>;
    
    if (!trainerId) {
        alert('Pilih pelatih terlebih dahulu!');
        return;
    }
    
    window.open(`print_trainer_schedule.php?trainer_id=${trainerId}`, '_blank');
}
</script>

<?php require_once '../../includes/footer.php'; ?>