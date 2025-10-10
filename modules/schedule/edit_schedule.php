<?php
$page_title = "Edit Jadwal";
require_once '../../includes/header.php';
requireRole(['admin']);

$schedule_id = intval($_GET['id']);
$success = '';
$error = '';

// Get schedule data
$schedule = $db->fetch("
    SELECT s.*, c.class_name, c.id as class_id
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
", [$schedule_id]);

if (!$schedule) {
    redirect('modules/schedule/?error=schedule_not_found');
}

// Get all active classes for dropdown
$classes = $db->fetchAll("
    SELECT c.*, u.full_name as trainer_name 
    FROM classes c 
    JOIN trainers t ON c.trainer_id = t.id 
    JOIN users u ON t.user_id = u.id 
    WHERE c.is_active = 1
    ORDER BY c.class_name ASC
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $class_id = intval($_POST['class_id']);
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($class_id) || empty($day_of_week) || empty($start_time) || empty($end_time)) {
            throw new Exception('Semua field wajib diisi!');
        }
        
        if ($start_time >= $end_time) {
            throw new Exception('Waktu mulai harus lebih kecil dari waktu selesai!');
        }
        
        // Check for conflicts (exclude current schedule)
        $existing = $db->fetch("
            SELECT s.id, c.class_name 
            FROM schedules s 
            JOIN classes c ON s.class_id = c.id
            WHERE s.id != ? AND s.day_of_week = ? AND s.is_active = 1
            AND ((s.start_time <= ? AND s.end_time > ?) OR (s.start_time < ? AND s.end_time >= ?))
        ", [$schedule_id, $day_of_week, $start_time, $start_time, $end_time, $end_time]);
        
        if ($existing) {
            throw new Exception("Jadwal bertabrakan dengan kelas: {$existing['class_name']}");
        }
        
        // Update schedule
        $db->query("
            UPDATE schedules 
            SET class_id = ?, day_of_week = ?, start_time = ?, end_time = ?, is_active = ?
            WHERE id = ?
        ", [$class_id, $day_of_week, $start_time, $end_time, $is_active, $schedule_id]);
        
        $success = "Jadwal berhasil diperbarui!";
        
        // Refresh data
        $schedule = $db->fetch("
            SELECT s.*, c.class_name, c.id as class_id
            FROM schedules s
            JOIN classes c ON s.class_id = c.id
            WHERE s.id = ?
        ", [$schedule_id]);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$day_names = [
    'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu', 'thursday' => 'Kamis',
    'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu'
];
?>

<!-- Header -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 1.5rem;">
                    <i class="fas fa-edit"></i>
                    Edit Jadwal
                </h3>
                <small style="opacity: 0.9;">Perbarui informasi jadwal kelas</small>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="view_schedule.php?id=<?= $schedule_id ?>" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                    <i class="fas fa-eye"></i>
                    Detail
                </a>
                <a href="index.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
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

<!-- Form -->
<div class="card" style="border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
    <div class="card-header" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 15px 15px 0 0;">
        <h4 style="margin: 0;">
            <i class="fas fa-calendar-alt"></i>
            Form Edit Jadwal
        </h4>
    </div>
    
    <form method="POST" action="" id="editScheduleForm">
        <div class="card-body" style="padding: 30px;">
            <!-- Current Schedule Info -->
            <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #1E459F;">
                <h6 style="color: #1E459F; margin-bottom: 10px;">Jadwal Saat Ini:</h6>
                <div style="color: #495057;">
                    <strong><?= htmlspecialchars($schedule['class_name']) ?></strong> - 
                    <?= $day_names[$schedule['day_of_week']] ?? ucfirst($schedule['day_of_week']) ?> 
                    (<?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?>)
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h5 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-chalkboard-teacher"></i>
                        Informasi Kelas
                    </h5>
                    
                    <div class="form-group">
                        <label class="form-label">Pilih Kelas *</label>
                        <select name="class_id" class="form-control form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $schedule['class_id'] == $class['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['class_name']) ?> - <?= htmlspecialchars($class['trainer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Tersedia <?= count($classes) ?> kelas aktif</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Hari *</label>
                        <select name="day_of_week" class="form-control form-select" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach ($day_names as $day_en => $day_id): ?>
                                <option value="<?= $day_en ?>" <?= $schedule['day_of_week'] === $day_en ? 'selected' : '' ?>>
                                    <?= $day_id ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div style="padding: 10px 0;">
                            <label class="form-check-label" style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $schedule['is_active'] ? 'checked' : '' ?> style="margin-right: 10px;">
                                <span>Jadwal Aktif</span>
                            </label>
                            <small class="text-muted">Centang untuk mengaktifkan jadwal ini</small>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h5 style="color: #1E459F; margin-bottom: 20px;">
                        <i class="fas fa-clock"></i>
                        Waktu Jadwal
                    </h5>
                    
                    <div class="form-group">
                        <label class="form-label">Waktu Mulai *</label>
                        <input type="time" name="start_time" class="form-control" value="<?= $schedule['start_time'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Waktu Selesai *</label>
                        <input type="time" name="end_time" class="form-control" value="<?= $schedule['end_time'] ?>" required>
                    </div>
                    
                    <div id="durationInfo" style="padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #17a2b8; margin-top: 15px;">
                        <small style="color: #17a2b8;">
                            <i class="fas fa-info-circle"></i>
                            <span id="durationText">Durasi akan dihitung otomatis</span>
                        </small>
                    </div>
                    
                    <!-- Quick Time Presets -->
                    <div style="margin-top: 20px;">
                        <label class="form-label">Quick Time Presets:</label>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setTime('06:00', '07:30')">06:00-07:30</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setTime('17:00', '18:30')">17:00-18:30</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setTime('19:00', '20:30')">19:00-20:30</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setTime('20:00', '21:30')">20:00-21:30</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer" style="background: #f8f9fa; border-radius: 0 0 15px 15px; padding: 25px; text-align: center;">
            <button type="submit" class="btn btn-primary btn-lg" style="min-width: 200px; background: linear-gradient(135deg, #1E459F, #CF2A2A); border: none;">
                <i class="fas fa-save"></i>
                Update Jadwal
            </button>
            <a href="view_schedule.php?id=<?= $schedule_id ?>" class="btn btn-secondary btn-lg" style="margin-left: 15px; min-width: 150px;">
                <i class="fas fa-times"></i>
                Batal
            </a>
            <a href="index.php" class="btn btn-info btn-lg" style="margin-left: 15px; min-width: 150px;">
                <i class="fas fa-list"></i>
                Daftar Jadwal
            </a>
        </div>
    </form>
</div>

<!-- Danger Zone -->
<div class="card" style="border: 2px solid #dc3545; border-radius: 15px; margin-top: 30px;">
    <div class="card-header" style="background: #dc3545; color: white; border-radius: 13px 13px 0 0;">
        <h5 style="margin: 0;">
            <i class="fas fa-exclamation-triangle"></i>
            Danger Zone
        </h5>
    </div>
    <div class="card-body" style="padding: 25px;">
        <p style="margin-bottom: 15px; color: #6c757d;">
            Tindakan di bawah ini bersifat permanen dan tidak dapat dibatalkan.
        </p>
        <button type="button" class="btn btn-danger" onclick="deleteSchedule(<?= $schedule_id ?>)">
            <i class="fas fa-trash"></i>
            Hapus Jadwal
        </button>
    </div>
</div>

<!-- Custom CSS -->
<style>
.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #1E459F;
    box-shadow: 0 0 0 0.2rem rgba(30, 69, 159, 0.25);
}

.btn {
    transition: all 0.3s ease;
    border-radius: 8px;
    font-weight: 600;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.alert {
    border-radius: 10px;
    border: none;
}

@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    
    .btn-lg {
        width: 100%;
        margin: 5px 0 !important;
    }
}
</style>

<!-- JavaScript -->
<script>
function setTime(startTime, endTime) {
    document.querySelector('input[name="start_time"]').value = startTime;
    document.querySelector('input[name="end_time"]').value = endTime;
    calculateDuration();
}

function calculateDuration() {
    const startTime = document.querySelector('input[name="start_time"]').value;
    const endTime = document.querySelector('input[name="end_time"]').value;
    
    if (startTime && endTime) {
        const start = new Date('1970-01-01 ' + startTime);
        const end = new Date('1970-01-01 ' + endTime);
        
        if (end > start) {
            const diff = (end - start) / (1000 * 60); // minutes
            const hours = Math.floor(diff / 60);
            const minutes = diff % 60;
            
            let durationText = 'Durasi: ';
            if (hours > 0) durationText += hours + ' jam ';
            if (minutes > 0) durationText += minutes + ' menit';
            
            document.getElementById('durationText').textContent = durationText;
            document.getElementById('durationInfo').style.borderColor = '#28a745';
            document.getElementById('durationInfo').style.background = '#d4edda';
            document.getElementById('durationText').style.color = '#155724';
        } else {
            document.getElementById('durationText').textContent = 'Waktu tidak valid!';
            document.getElementById('durationInfo').style.borderColor = '#dc3545';
            document.getElementById('durationInfo').style.background = '#f8d7da';
            document.getElementById('durationText').style.color = '#721c24';
        }
    }
}

// Event listeners
document.querySelector('input[name="start_time"]').addEventListener('change', calculateDuration);
document.querySelector('input[name="end_time"]').addEventListener('change', calculateDuration);

// Form validation
document.getElementById('editScheduleForm').addEventListener('submit', function(e) {
    const startTime = document.querySelector('input[name="start_time"]').value;
    const endTime = document.querySelector('input[name="end_time"]').value;
    const classId = document.querySelector('select[name="class_id"]').value;
    const dayOfWeek = document.querySelector('select[name="day_of_week"]').value;
    
    let hasError = false;
    let errorMessage = '';
    
    // Check required fields
    if (!classId) {
        errorMessage += '- Pilih kelas\n';
        hasError = true;
    }
    
    if (!dayOfWeek) {
        errorMessage += '- Pilih hari\n';
        hasError = true;
    }
    
    if (!startTime) {
        errorMessage += '- Isi waktu mulai\n';
        hasError = true;
    }
    
    if (!endTime) {
        errorMessage += '- Isi waktu selesai\n';
        hasError = true;
    }
    
    // Check time logic
    if (startTime && endTime && startTime >= endTime) {
        errorMessage += '- Waktu mulai harus lebih kecil dari waktu selesai\n';
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        alert('Mohon perbaiki error berikut:\n\n' + errorMessage);
        return false;
    }
    
    // Loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
});

function deleteSchedule(scheduleId) {
    if (confirm('PERINGATAN!\n\nApakah Anda yakin ingin menghapus jadwal ini?\n\nTindakan ini akan:\n- Menghapus jadwal secara permanen\n- Menghapus riwayat absensi terkait\n- Tidak dapat dibatalkan\n\nKetik "HAPUS" untuk konfirmasi:')) {
        const confirmation = prompt('Ketik "HAPUS" (tanpa tanda kutip) untuk mengkonfirmasi penghapusan:');
        
        if (confirmation === 'HAPUS') {
            // Create form for deletion
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_schedule.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'schedule_id';
            input.value = scheduleId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        } else {
            alert('Konfirmasi gagal. Penghapusan dibatalkan.');
        }
    }
}

// Auto-save draft (optional feature)
let draftTimeout;
function saveDraft() {
    const formData = new FormData(document.getElementById('editScheduleForm'));
    const draftData = {};
    
    for (let [key, value] of formData.entries()) {
        draftData[key] = value;
    }
    
    localStorage.setItem('schedule_edit_draft_<?= $schedule_id ?>', JSON.stringify(draftData));
    
    // Show draft saved indicator
    const indicator = document.createElement('div');
    indicator.textContent = '💾 Draft tersimpan';
    indicator.style.position = 'fixed';
    indicator.style.top = '10px';
    indicator.style.right = '10px';
    indicator.style.background = '#28a745';
    indicator.style.color = 'white';
    indicator.style.padding = '8px 12px';
    indicator.style.borderRadius = '5px';
    indicator.style.fontSize = '0.85rem';
    indicator.style.zIndex = '1001';
    indicator.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
    
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}

// Auto-save every 30 seconds if form has changes
document.getElementById('editScheduleForm').addEventListener('change', function() {
    clearTimeout(draftTimeout);
    draftTimeout = setTimeout(saveDraft, 30000);
});

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedDraft = localStorage.getItem('schedule_edit_draft_<?= $schedule_id ?>');
    
    if (savedDraft) {
        const draftData = JSON.parse(savedDraft);
        let hasChanges = false;
        
        Object.keys(draftData).forEach(key => {
            const element = document.querySelector(`[name="${key}"]`);
            if (element && element.value !== draftData[key]) {
                hasChanges = true;
            }
        });
        
        if (hasChanges && confirm('Draft ditemukan! Apakah Anda ingin memuat perubahan yang tersimpan?')) {
            Object.keys(draftData).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element) {
                    if (element.type === 'checkbox') {
                        element.checked = draftData[key] === '1';
                    } else {
                        element.value = draftData[key];
                    }
                }
            });
            calculateDuration();
        }
    }
    
    // Initial duration calculation
    calculateDuration();
});

// Clear draft after successful save
<?php if ($success): ?>
localStorage.removeItem('schedule_edit_draft_<?= $schedule_id ?>');
<?php endif; ?>

// Prevent accidental page leave
let formChanged = false;
document.getElementById('editScheduleForm').addEventListener('change', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'Anda memiliki perubahan yang belum disimpan. Yakin ingin meninggalkan halaman?';
    }
});

// Remove warning after form submit
document.getElementById('editScheduleForm').addEventListener('submit', function() {
    formChanged = false;
});
</script>

<?php require_once '../../includes/footer.php'; ?>