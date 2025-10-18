<?php
require_once '../../config/config.php';

// Cek apakah registrasi publik diaktifkan
$memberRegistrationEnabled = getSetting('enable_public_registration', true);
$staffRegistrationEnabled = true; // ENABLED for staff registration

if (!$memberRegistrationEnabled && !$staffRegistrationEnabled) {
    redirect('modules/auth/login.php?error=registration_disabled');
}

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect('modules/dashboard/');
}

$error = '';
$success = '';
$registration_type = $_GET['type'] ?? 'member'; // member atau staff

// Validasi tipe registrasi - BOTH NOW ENABLED
if (!in_array($registration_type, ['member', 'staff'])) {
    $registration_type = 'member';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $type = $_POST['registration_type'];
    
    // Validasi
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($phone)) {
        $error = 'Semua field harus diisi!';
    } elseif ($password !== $password_confirm) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Cek apakah username atau email sudah digunakan
        $existingUser = $db->fetch(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        
        if ($existingUser) {
            $error = 'Username atau email sudah terdaftar!';
        } else {
            try {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Tentukan role
                $role = ($type === 'staff') ? 'staff' : 'member';
                
                // Set default permissions
                $can_manage_users = 0;
                $can_manage_stock = ($role === 'staff') ? 0 : 0; // Staff tidak bisa manage stock, hanya request
                $can_view_reports = 0;
                $can_manage_finance = 0;
                
                // Insert ke tabel users
                $db->query(
                    "INSERT INTO users (username, email, password, full_name, phone, role, is_active, 
                     can_manage_users, can_manage_stock, can_view_reports, can_manage_finance, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())",
                    [$username, $email, $hashedPassword, $full_name, $phone, $role, 
                     $can_manage_users, $can_manage_stock, $can_view_reports, $can_manage_finance]
                );
                
                $user_id = $db->lastInsertId();
                
                // Jika member, buat data di tabel members
                if ($role === 'member') {
                    // Data tambahan member dari form
                    $birth_date = $_POST['birth_date'] ?? null;
                    $address = trim($_POST['address'] ?? '');
                    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
                    $martial_art_type = $_POST['martial_art_type'] ?? 'kickboxing';
                    $class_type = $_POST['class_type'] ?? 'regular';
                    
                    // Generate member code
                    $member_code = generateCode('MBR', 6);
                    
                    $db->query(
                        "INSERT INTO members (user_id, member_code, birth_date, address, emergency_contact, 
                         join_date, martial_art_type, class_type) 
                         VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?)",
                        [$user_id, $member_code, $birth_date, $address, $emergency_contact, 
                         $martial_art_type, $class_type]
                    );
                }
                
                // Jika staff, buat data di tabel trainers
                if ($role === 'staff') {
                    // Data tambahan staff dari form
                    $specialization = trim($_POST['specialization'] ?? '');
                    $experience_years = (int)($_POST['experience_years'] ?? 0);
                    $certification = trim($_POST['certification'] ?? '');
                    
                    // Generate trainer code
                    $trainer_code = generateCode('TRN', 6);
                    
                    $db->query(
                        "INSERT INTO trainers (user_id, trainer_code, specialization, experience_years, 
                         certification, hire_date) 
                         VALUES (?, ?, ?, ?, ?, CURDATE())",
                        [$user_id, $trainer_code, $specialization, $experience_years, $certification]
                    );
                }
                
                // Log aktivitas
                logActivity('register', 'users', $user_id, null, [
                    'username' => $username,
                    'email' => $email,
                    'role' => $role
                ]);
                
                // Kirim notifikasi ke admin
                $adminUsers = $db->fetchAll("SELECT id FROM users WHERE role IN ('super_admin', 'admin') AND is_active = 1");
                foreach ($adminUsers as $admin) {
                    sendNotification(
                        $admin['id'],
                        'Registrasi Baru',
                        "User baru {$full_name} ({$role}) telah mendaftar dan menunggu aktivasi.",
                        'system',
                        'users',
                        $user_id,
                        BASE_URL . 'modules/users/'
                    );
                }
                
                $success = 'Registrasi berhasil! Silakan login dengan akun Anda.';
                
                // Redirect setelah 2 detik
                header("refresh:2;url=" . BASE_URL . "modules/auth/login.php");
                
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - <?= APP_NAME ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#1E459F">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/ema-logo.png">
</head>
<body>
    <!-- Animated Background -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #1E459F 0%, #CF2A2A 50%, #FABD32 100%); background-size: 400% 400%; animation: gradientShift 8s ease infinite; z-index: -1;"></div>
    
    <div class="login-container">
        <div class="login-card" style="box-shadow: 0 20px 40px rgba(0,0,0,0.2); backdrop-filter: blur(10px); background: rgba(255,255,255,0.95); max-width: 600px;">
            <div class="login-header">
                <div style="margin-bottom: 20px;">
                    <img src="<?= BASE_URL ?>assets/images/ema-logo.png" alt="EMA Camp Logo" style="width: 100px; height: auto; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                </div>
                
                <h2 style="background: linear-gradient(45deg, #1E459F, #CF2A2A); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    Registrasi <?= $registration_type === 'staff' ? 'Pelatih/Staff' : 'Member' ?>
                </h2>
                <p style="color: #6c757d; font-weight: 500;">EMA CAMP Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="animation: shake 0.5s ease-in-out;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="animation: fadeIn 0.5s ease-in-out;">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <!-- Tab Switcher -->
            <div class="registration-tabs" style="display: flex; gap: 10px; margin-bottom: 20px;">
                <a href="?type=member" class="tab-btn <?= $registration_type === 'member' ? 'active' : '' ?>" style="flex: 1; padding: 12px; text-align: center; border-radius: 8px; text-decoration: none; background: <?= $registration_type === 'member' ? 'linear-gradient(45deg, #1E459F, #2056b8)' : '#e9ecef' ?>; color: <?= $registration_type === 'member' ? 'white' : '#6c757d' ?>; font-weight: bold; transition: all 0.3s ease;">
                    <i class="fas fa-user"></i> Member
                </a>
                <?php if ($staffRegistrationEnabled): ?>
                <a href="?type=staff" class="tab-btn <?= $registration_type === 'staff' ? 'active' : '' ?>" style="flex: 1; padding: 12px; text-align: center; border-radius: 8px; text-decoration: none; background: <?= $registration_type === 'staff' ? 'linear-gradient(45deg, #CF2A2A, #e03d3d)' : '#e9ecef' ?>; color: <?= $registration_type === 'staff' ? 'white' : '#6c757d' ?>; font-weight: bold; transition: all 0.3s ease;">
                    <i class="fas fa-user-tie"></i> Pelatih/Staff
                </a>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="registration_type" value="<?= $registration_type ?>">
                
                <!-- Data Akun -->
                <div style="background: rgba(30, 69, 159, 0.05); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h6 style="color: #1E459F; margin-bottom: 15px; font-weight: bold;">
                        <i class="fas fa-user-lock"></i> Data Akun
                    </h6>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Username *</label>
                        <input type="text" name="username" class="form-control" required style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" name="email" class="form-control" required style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-lock"></i> Password * (min. 6 karakter)</label>
                        <input type="password" name="password" class="form-control" minlength="6" required style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-lock"></i> Konfirmasi Password *</label>
                        <input type="password" name="password_confirm" class="form-control" minlength="6" required style="transition: all 0.3s ease;">
                    </div>
                </div>

                <!-- Data Pribadi -->
                <div style="background: rgba(207, 42, 42, 0.05); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h6 style="color: #CF2A2A; margin-bottom: 15px; font-weight: bold;">
                        <i class="fas fa-id-card"></i> Data Pribadi
                    </h6>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user-circle"></i> Nama Lengkap *</label>
                        <input type="text" name="full_name" class="form-control" required style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> No. Telepon *</label>
                        <input type="tel" name="phone" class="form-control" required style="transition: all 0.3s ease;">
                    </div>
                    
                    <?php if ($registration_type === 'member'): ?>
                    <!-- Member specific fields -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Tanggal Lahir</label>
                        <input type="date" name="birth_date" class="form-control" style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Alamat</label>
                        <textarea name="address" class="form-control" rows="3" style="transition: all 0.3s ease;"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone-square"></i> Kontak Darurat</label>
                        <input type="tel" name="emergency_contact" class="form-control" style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-fist-raised"></i> Jenis Bela Diri</label>
                        <select name="martial_art_type" class="form-control" style="transition: all 0.3s ease;">
                            <option value="kickboxing">Kickboxing</option>
                            <option value="boxing">Boxing</option>
                            <option value="savate">Savate</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-layer-group"></i> Tipe Kelas</label>
                        <select name="class_type" class="form-control" style="transition: all 0.3s ease;">
                            <option value="regular">Regular</option>
                            <option value="private_6x">Private 6x</option>
                            <option value="private_8x">Private 8x</option>
                            <option value="private_10x">Private 10x</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($registration_type === 'staff'): ?>
                    <!-- Staff specific fields -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-star"></i> Spesialisasi</label>
                        <input type="text" name="specialization" class="form-control" placeholder="Contoh: Kickboxing, Boxing" style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-clock"></i> Pengalaman (Tahun)</label>
                        <input type="number" name="experience_years" class="form-control" min="0" style="transition: all 0.3s ease;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-certificate"></i> Sertifikasi</label>
                        <textarea name="certification" class="form-control" rows="2" placeholder="Sebutkan sertifikasi yang Anda miliki" style="transition: all 0.3s ease;"></textarea>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-gradient" style="width: 100%; margin-top: 20px; background: linear-gradient(45deg, #1E459F, #CF2A2A); border: none; padding: 14px; font-size: 1.1rem; font-weight: bold; transition: all 0.3s ease;">
                    <i class="fas fa-user-plus"></i>
                    Daftar Sekarang
                </button>
            </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 25px;">
                <p style="color: #6c757d;">Sudah punya akun?
                    <a href="<?= BASE_URL ?>modules/auth/login.php" style="color: #1E459F; font-weight: bold; text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i> Login di sini
                    </a>
                </p>
            </div>

            <div style="text-align: center; margin-top: 30px; color: #6c757d;">
                <small>&copy; 2025 EMA Camp. All rights reserved.</small>
            </div>
        </div>
    </div>

    <style>
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-control:focus {
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(30, 69, 159, 0.3);
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .tab-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</body>
</html>
