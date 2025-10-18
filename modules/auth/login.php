<?php
require_once '../../config/config.php';

if (isLoggedIn()) {
    redirect('modules/dashboard/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        $user = $db->fetch(
            "SELECT u.*, m.member_code, t.trainer_code 
             FROM users u 
             LEFT JOIN members m ON u.id = m.user_id 
             LEFT JOIN trainers t ON u.id = t.user_id 
             WHERE u.username = ? AND u.is_active = 1", 
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Clear any existing session data first
            $_SESSION = array();
            
            // Set new session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            
            if ($user['role'] === 'member') {
                $_SESSION['member_code'] = $user['member_code'];
            } elseif ($user['role'] === 'trainer') {
                $_SESSION['trainer_code'] = $user['trainer_code'];
            }
            
            // Update last login time
            $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            
            redirect('modules/dashboard/');
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#1E459F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/ema-logo.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/images/ema-logo.png">
</head>
<body>
    <!-- Animated Background -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #1E459F 0%, #CF2A2A 50%, #FABD32 100%); background-size: 400% 400%; animation: gradientShift 8s ease infinite; z-index: -1;"></div>
    
    <div class="login-container">
        <div class="login-card" style="box-shadow: 0 20px 40px rgba(0,0,0,0.2); backdrop-filter: blur(10px); background: rgba(255,255,255,0.95);">
            <div class="login-header">
                <!-- Logo Integration -->
                <div style="margin-bottom: 20px;">
                    <img src="<?= BASE_URL ?>assets/images/ema-logo.png" alt="EMA Camp Logo" style="width: 120px; height: auto; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                </div>
                
                <h2 style="background: linear-gradient(45deg, #1E459F, #CF2A2A); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">EMA CAMP</h2>
                <p style="color: #6c757d; font-weight: 500;">Elite Martial Art Management System</p>
                
                <!-- Version Badge -->
                <span style="background: linear-gradient(45deg, #FABD32, #E1DCCA); color: #1E459F; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">
                    v1.0 Professional
                </span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="animation: shake 0.5s ease-in-out;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" name="username" class="form-control" required style="transition: all 0.3s ease;">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" name="password" class="form-control" required style="transition: all 0.3s ease;">
                </div>

                <button type="submit" class="btn btn-gradient" style="width: 100%; margin-top: 20px; background: linear-gradient(45deg, #1E459F, #CF2A2A); border: none; padding: 12px; font-size: 1.1rem; font-weight: bold; transition: all 0.3s ease;">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk ke Sistem
                </button>
            </form>

            <!-- Registration Links -->
            <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, rgba(30, 69, 159, 0.1), rgba(207, 42, 42, 0.1)); border-radius: 10px; text-align: center;">
                <p style="color: #6c757d; margin-bottom: 10px; font-size: 0.9rem;">
                    <i class="fas fa-user-plus"></i> Belum punya akun?
                </p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a href="register.php" class="btn btn-outline-primary" style="flex: 1; padding: 10px; border: 2px solid #1E459F; color: #1E459F; text-decoration: none; border-radius: 5px; font-weight: 600;">
                        <i class="fas fa-user"></i> Daftar Member
                    </a>
                    <a href="register.php?type=staff" class="btn btn-outline-danger" style="flex: 1; padding: 10px; border: 2px solid #CF2A2A; color: #CF2A2A; text-decoration: none; border-radius: 5px; font-weight: 600;">
                        <i class="fas fa-user-tie"></i> Daftar Staff
                    </a>
                </div>
            </div>

            <!-- Quick Login Info -->
            <div style="margin-top: 20px; padding: 15px; background: rgba(30, 69, 159, 0.1); border-radius: 8px; border-left: 4px solid #1E459F;">
                <h6 style="color: #1E459F; margin-bottom: 10px;">
                    <i class="fas fa-info-circle"></i>
                    Demo Login
                </h6>
                <div style="font-size: 0.9rem; color: #6c757d;">
                    <strong>Username:</strong> admin<br>
                    <strong>Password:</strong> password
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px; color: #6c757d;">
                <small>&copy; 2025 EMA Camp - Elite Martial Art. All rights reserved.</small><br>
                <small style="margin-top: 5px; display: block;">
                    <i class="fas fa-shield-alt"></i>
                    Secure Login System
                </small>
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
        
        .form-control:focus {
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(30, 69, 159, 0.3);
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
    </style>
</body>
</html>