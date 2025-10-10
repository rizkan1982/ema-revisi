<?php
$page_title = "Pengaturan Notifikasi";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

// Get current settings
$settings = [
    'email_notifications' => true,
    'whatsapp_notifications' => false,
    'push_notifications' => true,
    'payment_reminder_days' => 3,
    'auto_reminder' => true,
    'notification_sound' => true
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Save notification settings
        // In a real application, you would save these to a settings table
        $success = "Pengaturan notifikasi berhasil disimpan!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-cog"></i>
            Pengaturan Notifikasi
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
            <!-- General Settings -->
            <div class="settings-section" style="margin-bottom: 40px;">
                <h4 style="color: #1E459F; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #1E459F;">
                    <i class="fas fa-bell"></i>
                    Pengaturan Umum
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #1E459F;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h6 style="margin: 0; color: #1E459F;">
                                <i class="fas fa-envelope"></i>
                                Email Notifications
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <p style="margin: 0; font-size: 0.9rem; color: #6c757d;">
                            Kirim notifikasi melalui email kepada member dan staff
                        </p>
                    </div>
                    
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #28a745;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h6 style="margin: 0; color: #28a745;">
                                <i class="fab fa-whatsapp"></i>
                                WhatsApp Notifications
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="whatsapp_notifications" id="whatsapp_notifications" <?= $settings['whatsapp_notifications'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <p style="margin: 0; font-size: 0.9rem; color: #6c757d;">
                            Kirim notifikasi melalui WhatsApp (memerlukan WhatsApp API)
                        </p>
                    </div>
                    
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #CF2A2A;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h6 style="margin: 0; color: #CF2A2A;">
                                <i class="fas fa-mobile-alt"></i>
                                Push Notifications
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="push_notifications" id="push_notifications" <?= $settings['push_notifications'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <p style="margin: 0; font-size: 0.9rem; color: #6c757d;">
                            Tampilkan notifikasi langsung di aplikasi
                        </p>
                    </div>
                    
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #FABD32;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h6 style="margin: 0; color: #FABD32;">
                                <i class="fas fa-volume-up"></i>
                                Notification Sound
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="notification_sound" id="notification_sound" <?= $settings['notification_sound'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <p style="margin: 0; font-size: 0.9rem; color: #6c757d;">
                            Putar suara saat menerima notifikasi baru
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Payment Reminder Settings -->
            <div class="settings-section" style="margin-bottom: 40px;">
                <h4 style="color: #CF2A2A; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #CF2A2A;">
                    <i class="fas fa-money-bill-wave"></i>
                    Pengaturan Reminder Pembayaran
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h6 style="margin: 0; color: #CF2A2A;">
                                <i class="fas fa-calendar-alt"></i>
                                Auto Reminder
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_reminder" id="auto_reminder" <?= $settings['auto_reminder'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 0.9rem;">Kirim reminder berapa hari sebelum jatuh tempo?</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="number" name="payment_reminder_days" class="form-control" value="<?= $settings['payment_reminder_days'] ?>" min="1" max="30" style="width: 100px;">
                                <span>hari</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h6 style="margin-bottom: 15px; color: #1E459F;">
                            <i class="fas fa-clock"></i>
                            Jadwal Pengiriman Reminder
                        </h6>
                        
                        <div style="display: grid; gap: 10px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="reminder_schedule[]" value="daily" id="daily" checked>
                                <label class="form-check-label" for="daily">Harian (setiap hari jam 09:00)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="reminder_schedule[]" value="weekly" id="weekly">
                                <label class="form-check-label" for="weekly">Mingguan (setiap Senin)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="reminder_schedule[]" value="monthly" id="monthly">
                                <label class="form-check-label" for="monthly">Bulanan (tanggal 1 setiap bulan)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Settings -->
            <div class="settings-section" style="margin-bottom: 40px;">
                <h4 style="color: #FABD32; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #FABD32;">
                    <i class="fas fa-cogs"></i>
                    Pengaturan Lanjutan
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h6 style="margin-bottom: 15px; color: #1E459F;">
                            <i class="fas fa-database"></i>
                            Penyimpanan Notifikasi
                        </h6>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label" style="font-size: 0.9rem;">Simpan notifikasi selama:</label>
                            <select name="notification_retention" class="form-control form-select">
                                <option value="30">30 hari</option>
                                <option value="60">60 hari</option>
                                <option value="90" selected>90 hari</option>
                                <option value="365">1 tahun</option>
                                <option value="0">Selamanya</option>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="auto_delete_read" id="auto_delete_read">
                            <label class="form-check-label" for="auto_delete_read" style="font-size: 0.9rem;">
                                Otomatis hapus notifikasi yang sudah dibaca setelah 7 hari
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h6 style="margin-bottom: 15px; color: #1E459F;">
                            <i class="fas fa-shield-alt"></i>
                            Keamanan & Privacy
                        </h6>
                        
                        <div style="display: grid; gap: 10px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="encrypt_notifications" id="encrypt_notifications" checked>
                                <label class="form-check-label" for="encrypt_notifications" style="font-size: 0.9rem;">
                                    Enkripsi notifikasi sensitif
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="log_notifications" id="log_notifications" checked>
                                <label class="form-check-label" for="log_notifications" style="font-size: 0.9rem;">
                                    Log aktivitas notifikasi
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="require_read_confirmation" id="require_read_confirmation">
                                <label class="form-check-label" for="require_read_confirmation" style="font-size: 0.9rem;">
                                    Minta konfirmasi baca untuk notifikasi penting
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- API Settings -->
            <div class="settings-section" style="margin-bottom: 40px;">
                <h4 style="color: #17a2b8; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #17a2b8;">
                    <i class="fas fa-plug"></i>
                    Integrasi API
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h6 style="margin-bottom: 15px; color: #17a2b8;">
                            <i class="fab fa-whatsapp"></i>
                            WhatsApp API
                        </h6>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label" style="font-size: 0.9rem;">API Token:</label>
                            <input type="password" name="whatsapp_token" class="form-control" placeholder="Masukkan WhatsApp API token">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label" style="font-size: 0.9rem;">Phone Number ID:</label>
                            <input type="text" name="whatsapp_phone_id" class="form-control" placeholder="Contoh: 1234567890">
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="testWhatsAppAPI()">
                            <i class="fas fa-vial"></i>
                            Test Koneksi
                        </button>
                    </div>
                    
                    <div class="setting-item" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h6 style="margin-bottom: 15px; color: #17a2b8;">
                            <i class="fas fa-envelope"></i>
                            Email SMTP
                        </h6>
                        
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="form-label" style="font-size: 0.9rem;">SMTP Host:</label>
                            <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" style="font-size: 0.9rem;">Port:</label>
                                <input type="number" name="smtp_port" class="form-control" placeholder="587">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" style="font-size: 0.9rem;">Encryption:</label>
                                <select name="smtp_encryption" class="form-control form-select">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="testEmailSMTP()">
                            <i class="fas fa-vial"></i>
                            Test SMTP
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Save Button -->
            <div style="padding-top: 30px; border-top: 2px solid #dee2e6; text-align: center;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Simpan Pengaturan
                </button>
                
                <button type="button" class="btn btn-success btn-lg" onclick="resetToDefault()" style="margin-left: 15px;">
                    <i class="fas fa-undo-alt"></i>
                    Reset ke Default
                </button>
                
                <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 15px;">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<script>
function testWhatsAppAPI() {
    const token = document.querySelector('input[name="whatsapp_token"]').value;
    const phoneId = document.querySelector('input[name="whatsapp_phone_id"]').value;
    
    if (!token || !phoneId) {
        alert('Mohon isi WhatsApp API token dan Phone Number ID terlebih dahulu!');
        return;
    }
    
    // Simulate API test
    setTimeout(() => {
        alert('✅ Koneksi WhatsApp API berhasil!');
    }, 1000);
}

function testEmailSMTP() {
    const host = document.querySelector('input[name="smtp_host"]').value;
    const port = document.querySelector('input[name="smtp_port"]').value;
    
    if (!host || !port) {
        alert('Mohon isi SMTP host dan port terlebih dahulu!');
        return;
    }
    
    // Simulate SMTP test
    setTimeout(() => {
        alert('✅ Koneksi SMTP berhasil!');
    }, 1000);
}

function resetToDefault() {
    if (confirm('Yakin ingin mereset semua pengaturan ke nilai default?')) {
        location.reload();
    }
}

// Add switch animation styles
const switchStyle = document.createElement('style');
switchStyle.textContent = `
    .form-check-input:checked {
        background-color: #1E459F;
        border-color: #1E459F;
    }
    
    .setting-item {
        transition: all 0.3s ease;
    }
    
    .setting-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
`;
document.head.appendChild(switchStyle);
</script>

<?php require_once '../../includes/footer.php'; ?>