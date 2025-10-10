<?php
$page_title = "Tambah Event Baru";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $event_name = trim($_POST['event_name']);
        $event_type = $_POST['event_type'];
        $description = trim($_POST['description']);
        $event_date = $_POST['event_date'];
        $start_time = $_POST['start_time'] ?: null;
        $end_time = $_POST['end_time'] ?: null;
        $location = trim($_POST['location']);
        $registration_fee = floatval($_POST['registration_fee']);
        $max_participants = intval($_POST['max_participants']) ?: null;
        $registration_deadline = $_POST['registration_deadline'] ?: null;
        
        $db->query("
            INSERT INTO events (event_name, event_type, description, event_date, start_time, 
                              end_time, location, registration_fee, max_participants, 
                              registration_deadline, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [$event_name, $event_type, $description, $event_date, $start_time, 
            $end_time, $location, $registration_fee, $max_participants, 
            $registration_deadline, $_SESSION['user_id']]);
        
        $success = "Event berhasil ditambahkan!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tambah Event Baru</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
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
    
    <form method="POST" action="">
        <div style="padding: 25px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">Informasi Event</h4>
                    
                    <div class="form-group">
                        <label class="form-label">Nama Event *</label>
                        <input type="text" name="event_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe Event *</label>
                        <select name="event_type" class="form-control form-select" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="tournament">Tournament</option>
                            <option value="belt_test">Ujian Sabuk</option>
                            <option value="seminar">Seminar</option>
                            <option value="camp">Training Camp</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Deskripsi event..."></textarea>
                    </div>
                </div>
                
                <div>
                    <h4 style="color: #1E459F; margin-bottom: 20px;">Jadwal & Lokasi</h4>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Event *</label>
                        <input type="date" name="event_date" class="form-control" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Waktu Mulai</label>
                            <input type="time" name="start_time" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Waktu Selesai</label>
                            <input type="time" name="end_time" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Lokasi</label>
                        <input type="text" name="location" class="form-control" placeholder="Contoh: EMA Camp, Gedung ABC">
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 30px;">
                <div class="form-group">
                    <label class="form-label">Biaya Pendaftaran (Rp)</label>
                    <input type="number" name="registration_fee" class="form-control" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Maksimal Peserta</label>
                    <input type="number" name="max_participants" class="form-control" min="1" placeholder="Kosongkan jika unlimited">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deadline Pendaftaran</label>
                    <input type="date" name="registration_deadline" class="form-control">
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Simpan Event
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>