<?php
$page_title = "Kirim Notifikasi";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

// Get all users for recipient selection
$users = $db->fetchAll("
    SELECT u.id, u.full_name, u.email, u.role,
           CASE 
               WHEN u.role = 'member' THEN m.member_code
               WHEN u.role = 'trainer' THEN t.trainer_code
               ELSE 'ADM'
           END as code
    FROM users u
    LEFT JOIN members m ON u.id = m.user_id
    LEFT JOIN trainers t ON u.id = t.user_id
    WHERE u.is_active = 1
    ORDER BY u.role, u.full_name ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $recipients = $_POST['recipients'];
        $notification_type = $_POST['notification_type'];
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $send_email = isset($_POST['send_email']);
        $send_whatsapp = isset($_POST['send_whatsapp']);
        
        if (empty($recipients)) {
            throw new Exception('Pilih minimal satu penerima notifikasi!');
        }
        
        $sent_count = 0;
        
        foreach ($recipients as $recipient_id) {
            // Insert notification
            $db->query("
                INSERT INTO notifications (recipient_id, title, message, type) 
                VALUES (?, ?, ?, ?)
            ", [$recipient_id, $title, $message, $notification_type]);
            
            $sent_count++;
            
            // TODO: Implement email/whatsapp sending
            if ($send_email) {
                // Send email notification
                // mail() implementation here
            }
            
            if ($send_whatsapp) {
                // Send WhatsApp notification  
                // WhatsApp API implementation here
            }
        }
        
        $success = "Notifikasi berhasil dikirim ke $sent_count penerima!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-paper-plane"></i>
            Kirim Notifikasi Baru
        </h3>
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
        <div style="padding: 30px;">
            <!-- Recipients Selection -->
            <div class="form-group" style="margin-bottom: 30px;">
                <label class="form-label">
                    <i class="fas fa-users"></i>
                    Penerima Notifikasi *
                </label>
                
                <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                        <i class="fas fa-check-double"></i>
                        Pilih Semua
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectNone()">
                        <i class="fas fa-times"></i>
                        Batal Semua
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="selectByRole('member')">
                        <i class="fas fa-users"></i>
                        Semua Member
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="selectByRole('trainer')">
                        <i class="fas fa-user-tie"></i>
                        Semua Pelatih
                    </button>
                </div>
                
                <div style="max-height: 300px; overflow-y: auto; border: 2px solid #dee2e6; border-radius: 8px; padding: 15px;">
                    <?php
                    $current_role = '';
                    foreach ($users as $user):
                        if ($current_role !== $user['role']):
                            if ($current_role !== '') echo '</div>';
                            $current_role = $user['role'];
                            $role_name = ucfirst($current_role);
                            $role_icon = $current_role === 'member' ? 'fa-users' : ($current_role === 'trainer' ? 'fa-user-tie' : 'fa-user-shield');
                            echo "<div style='margin-bottom: 20px;'>";
                            echo "<h6 style='color: #1E459F; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #dee2e6;'>";
                            echo "<i class='fas $role_icon'></i> $role_name";
                            echo "</h6>";
                        endif;
                    ?>
                        <div class="form-check" style="margin-bottom: 8px;">
                            <input class="form-check-input recipient-checkbox" type="checkbox" name="recipients[]" value="<?= $user['id'] ?>" id="user_<?= $user['id'] ?>" data-role="<?= $user['role'] ?>">
                            <label class="form-check-label" for="user_<?= $user['id'] ?>" style="display: flex; justify-content: space-between; align-items: center; width: 100%; cursor: pointer;">
                                <span>
                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?= $user['code'] ?> • <?= htmlspecialchars($user['email']) ?>
                                    </small>
                                </span>
                                <span class="badge badge-<?= $user['role'] === 'member' ? 'info' : ($user['role'] === 'trainer' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                
                <div id="recipient-count" style="margin-top: 10px; font-size: 0.9rem; color: #6c757d;">
                    <i class="fas fa-info-circle"></i>
                    <span id="selected-count">0</span> penerima dipilih
                </div>
            </div>
            
            <!-- Notification Details -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                <div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i>
                            Tipe Notifikasi *
                        </label>
                        <select name="notification_type" class="form-control form-select" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="general">Pengumuman Umum</option>
                            <option value="payment_reminder">Reminder Pembayaran</option>
                            <option value="schedule_change">Perubahan Jadwal</option>
                            <option value="event_announcement">Pengumuman Event</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-heading"></i>
                            Judul Notifikasi *
                        </label>
                        <input type="text" name="title" class="form-control" required maxlength="200" placeholder="Contoh: Pengumuman Penting - Perubahan Jadwal">
                    </div>
                </div>
                
                <div>
                    <!-- Quick Templates -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-magic"></i>
                            Template Cepat
                        </label>
                        <select class="form-control form-select" onchange="loadTemplate(this.value)">
                            <option value="">-- Pilih Template --</option>
                            <option value="payment_reminder">Reminder Pembayaran</option>
                            <option value="schedule_change">Perubahan Jadwal</option>
                            <option value="event_announcement">Pengumuman Event</option>
                            <option value="maintenance">Maintenance System</option>
                            <option value="holiday">Hari Libur</option>
                        </select>
                    </div>
                    
                    <!-- Delivery Options -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-paper-plane"></i>
                            Opsi Pengiriman
                        </label>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 8px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_email" id="send_email">
                                <label class="form-check-label" for="send_email">
                                    <i class="fas fa-envelope"></i>
                                    Email
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_whatsapp" id="send_whatsapp">
                                <label class="form-check-label" for="send_whatsapp">
                                    <i class="fab fa-whatsapp"></i>
                                    WhatsApp
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_push" id="send_push" checked>
                                <label class="form-check-label" for="send_push">
                                    <i class="fas fa-bell"></i>
                                    Push Notification
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Message Content -->
            <div class="form-group" style="margin-bottom: 30px;">
                <label class="form-label">
                    <i class="fas fa-comment-alt"></i>
                    Isi Pesan *
                </label>
                <textarea name="message" class="form-control" rows="6" required maxlength="1000" placeholder="Tulis pesan notifikasi di sini..."></textarea>
                <small class="text-muted">
                    <span id="char-count">0</span>/1000 karakter
                </small>
            </div>
            
            <!-- Preview -->
            <div class="form-group" style="margin-bottom: 30px;">
                <label class="form-label">
                    <i class="fas fa-eye"></i>
                    Preview Notifikasi
                </label>
                <div id="notification-preview" style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1E459F; min-height: 80px;">
                    <div style="color: #6c757d; text-align: center; padding: 20px;">
                        <i class="fas fa-eye-slash"></i>
                        Preview akan muncul saat Anda mengisi judul dan pesan
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center;">
                <button type="submit" class="btn btn-primary btn-lg" id="send-button" disabled>
                    <i class="fas fa-paper-plane"></i>
                    Kirim Notifikasi
                </button>
                
                <button type="button" class="btn btn-success btn-lg" onclick="scheduleNotification()" style="margin-left: 10px;">
                    <i class="fas fa-clock"></i>
                    Jadwalkan
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<script>
// Templates
const templates = {
    'payment_reminder': {
        title: 'Reminder Pembayaran Iuran Bulanan',
        message: 'Halo! Ini adalah pengingat bahwa pembayaran iuran bulanan Anda akan segera jatuh tempo. Mohon untuk segera melakukan pembayaran agar tidak terkena denda keterlambatan. Terima kasih!'
    },
    'schedule_change': {
        title: 'Perubahan Jadwal Kelas',
        message: 'Terdapat perubahan jadwal kelas. Mohon periksa jadwal terbaru di aplikasi atau hubungi admin untuk informasi lebih lanjut. Terima kasih atas perhatiannya.'
    },
    'event_announcement': {
        title: 'Pengumuman Event Spesial',
        message: 'Kami mengundang Anda untuk mengikuti event spesial yang akan diadakan. Jangan lewatkan kesempatan emas ini! Daftarkan diri Anda segera karena tempat terbatas.'
    },
    'maintenance': {
        title: 'Maintenance Sistem',
        message: 'Sistem akan menjalani maintenance pada waktu yang telah ditentukan. Selama maintenance, sistem mungkin tidak dapat diakses sementara waktu. Mohon maaf atas ketidaknyamanannya.'
    },
    'holiday': {
        title: 'Pengumuman Hari Libur',
        message: 'Dalam rangka hari libur, EMA Camp akan tutup sementara. Kelas akan kembali normal setelah masa libur berakhir. Selamat berlibur!'
    }
};

function loadTemplate(templateKey) {
    if (templates[templateKey]) {
        document.querySelector('input[name="title"]').value = templates[templateKey].title;
        document.querySelector('textarea[name="message"]').value = templates[templateKey].message;
        updatePreview();
        updateCharCount();
    }
}

function selectAll() {
    document.querySelectorAll('.recipient-checkbox').forEach(cb => cb.checked = true);
    updateRecipientCount();
}

function selectNone() {
    document.querySelectorAll('.recipient-checkbox').forEach(cb => cb.checked = false);
    updateRecipientCount();
}

function selectByRole(role) {
    selectNone();
    document.querySelectorAll(`.recipient-checkbox[data-role="${role}"]`).forEach(cb => cb.checked = true);
    updateRecipientCount();
}

function updateRecipientCount() {
    const count = document.querySelectorAll('.recipient-checkbox:checked').length;
    document.getElementById('selected-count').textContent = count;
    document.getElementById('send-button').disabled = count === 0;
}

function updatePreview() {
    const title = document.querySelector('input[name="title"]').value;
    const message = document.querySelector('textarea[name="message"]').value;
    const previewDiv = document.getElementById('notification-preview');
    
    if (title || message) {
        previewDiv.innerHTML = `
            <div style="display: flex; align-items: start; gap: 10px;">
                <i class="fas fa-bell" style="color: #1E459F; margin-top: 3px;"></i>
                <div style="flex: 1;">
                    <div style="font-weight: bold; color: #1E459F; margin-bottom: 5px;">
                        ${title || '[Judul Notifikasi]'}
                    </div>
                    <div style="color: #495057; line-height: 1.5;">
                        ${message || '[Isi pesan notifikasi]'}
                    </div>
                    <div style="font-size: 0.8rem; color: #6c757d; margin-top: 8px;">
                        <i class="fas fa-clock"></i>
                        Baru saja
                    </div>
                </div>
            </div>
        `;
    } else {
        previewDiv.innerHTML = `
            <div style="color: #6c757d; text-align: center; padding: 20px;">
                <i class="fas fa-eye-slash"></i>
                Preview akan muncul saat Anda mengisi judul dan pesan
            </div>
        `;
    }
}

function updateCharCount() {
    const message = document.querySelector('textarea[name="message"]').value;
    document.getElementById('char-count').textContent = message.length;
}

function scheduleNotification() {
    alert('Fitur penjadwalan notifikasi akan segera hadir!');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Update recipient count on checkbox change
    document.querySelectorAll('.recipient-checkbox').forEach(cb => {
        cb.addEventListener('change', updateRecipientCount);
    });
    
    // Update preview on input
    document.querySelector('input[name="title"]').addEventListener('input', updatePreview);
    document.querySelector('textarea[name="message"]').addEventListener('input', function() {
        updatePreview();
        updateCharCount();
    });
    
    // Initial updates
    updateRecipientCount();
    updateCharCount();
});
</script>

<?php require_once '../../includes/footer.php'; ?>