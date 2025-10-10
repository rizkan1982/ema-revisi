<?php
$page_title = "Tambah Jadwal Baru";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

// Get all classes and trainers
$classes = $db->fetchAll("
    SELECT c.*, u.full_name as trainer_name 
    FROM classes c 
    JOIN trainers t ON c.trainer_id = t.id 
    JOIN users u ON t.user_id = u.id 
    WHERE c.is_active = 1
    ORDER BY c.class_name ASC
");

// Jika tidak ada kelas, buat kelas default
if (empty($classes)) {
    // Get trainers for default classes
    $trainers = $db->fetchAll("
        SELECT t.*, u.full_name 
        FROM trainers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.is_active = 1
        LIMIT 2
    ");
    
    if (!empty($trainers)) {
        // Create default classes
        $default_classes = [
            [
                'name' => 'Kickboxing Regular',
                'martial_art_type' => 'kickboxing',
                'class_type' => 'regular',
                'max_participants' => 20,
                'duration_minutes' => 90,
                'monthly_fee' => 250000,
                'description' => 'Kelas kickboxing reguler untuk semua level'
            ],
            [
                'name' => 'Boxing Regular',
                'martial_art_type' => 'boxing',
                'class_type' => 'regular',
                'max_participants' => 15,
                'duration_minutes' => 60,
                'monthly_fee' => 200000,
                'description' => 'Kelas boxing reguler untuk pemula hingga advanced'
            ]
        ];
        
        foreach ($default_classes as $index => $class_data) {
            $trainer_id = $trainers[$index % count($trainers)]['id'];
            
            $db->query("
                INSERT INTO classes (class_name, martial_art_type, class_type, trainer_id, 
                                   max_participants, duration_minutes, monthly_fee, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $class_data['name'], 
                $class_data['martial_art_type'], 
                $class_data['class_type'], 
                $trainer_id,
                $class_data['max_participants'], 
                $class_data['duration_minutes'], 
                $class_data['monthly_fee'], 
                $class_data['description']
            ]);
        }
        
        // Refresh classes list
        $classes = $db->fetchAll("
            SELECT c.*, u.full_name as trainer_name 
            FROM classes c 
            JOIN trainers t ON c.trainer_id = t.id 
            JOIN users u ON t.user_id = u.id 
            WHERE c.is_active = 1
            ORDER BY c.class_name ASC
        ");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $class_id = $_POST['class_id'];
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        // Check for conflicts
        $existing = $db->fetch("
            SELECT id FROM schedules 
            WHERE class_id = ? AND day_of_week = ? 
            AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
        ", [$class_id, $day_of_week, $start_time, $start_time, $end_time, $end_time]);
        
        if ($existing) {
            throw new Exception('Jadwal bertabrakan dengan jadwal yang sudah ada!');
        }
        
        $db->query("
            INSERT INTO schedules (class_id, day_of_week, start_time, end_time) 
            VALUES (?, ?, ?, ?)
        ", [$class_id, $day_of_week, $start_time, $end_time]);
        
        $success = "Jadwal berhasil ditambahkan!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tambah Jadwal Baru</h3>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <?php if (empty($classes)): ?>
            <a href="class_management.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Buat Kelas Baru
            </a>
            <?php endif; ?>
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
    
    <?php if (empty($classes)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-chalkboard-teacher" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;"></i>
            <h4>Belum Ada Kelas</h4>
            <p>Anda perlu membuat kelas terlebih dahulu sebelum dapat menambah jadwal</p>
            <a href="class_management.php" class="btn btn-primary btn-lg">
                <i class="fas fa-plus"></i>
                Buat Kelas Pertama
            </a>
        </div>
    <?php else: ?>
    
    <form method="POST" action="">
        <div style="padding: 25px;">
            <div class="form-group">
                <label class="form-label">Pilih Kelas *</label>
                <select name="class_id" class="form-control form-select" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= $class['id'] ?>">
                        <?= htmlspecialchars($class['class_name']) ?> - <?= htmlspecialchars($class['trainer_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Tersedia <?= count($classes) ?> kelas aktif
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Hari *</label>
                <select name="day_of_week" class="form-control form-select" required>
                    <option value="">-- Pilih Hari --</option>
                    <option value="monday">Senin</option>
                    <option value="tuesday">Selasa</option>
                    <option value="wednesday">Rabu</option>
                    <option value="thursday">Kamis</option>
                    <option value="friday">Jumat</option>
                    <option value="saturday">Sabtu</option>
                    <option value="sunday">Minggu</option>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Waktu Mulai *</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Waktu Selesai *</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
            </div>
            
            <!-- Quick Time Presets -->
            <div style="margin-bottom: 20px;">
                <label class="form-label">Quick Time Presets:</label>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setTime('06:00', '07:30')">06:00 - 07:30</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setTime('17:00', '18:30')">17:00 - 18:30</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setTime('19:00', '20:30')">19:00 - 20:30</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setTime('20:00', '21:30')">20:00 - 21:30</button>
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Simpan Jadwal
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
    
    <?php endif; ?>
</div>

<script>
function setTime(startTime, endTime) {
    document.querySelector('input[name="start_time"]').value = startTime;
    document.querySelector('input[name="end_time"]').value = endTime;
}
</script>

<?php require_once '../../includes/footer.php'; ?>