<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak | EMA Camp</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: linear-gradient(135deg, #CF2A2A 0%, #8B1A1A 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div style="text-align: center; color: white; max-width: 600px; padding: 40px;">
        <div style="font-size: 8rem; margin-bottom: 20px; opacity: 0.7;">
            <i class="fas fa-shield-alt"></i>
        </div>
        
        <h1 style="font-size: 4rem; margin-bottom: 20px; color: #FABD32;">403</h1>
        
        <h2 style="font-size: 1.8rem; margin-bottom: 20px;">Akses Ditolak</h2>
        
        <p style="font-size: 1.1rem; margin-bottom: 40px; opacity: 0.9;">
            Anda tidak memiliki izin untuk mengakses halaman ini. Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.
        </p>
        
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="../index.php" class="btn btn-primary btn-lg" style="background: #FABD32; color: #CF2A2A; border: none;">
                <i class="fas fa-home"></i>
                Kembali ke Dashboard
            </a>
            
            <a href="../modules/auth/logout.php" class="btn btn-lg" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
            <p style="opacity: 0.7;">
                <i class="fas fa-user-shield"></i>
                Level akses Anda: <?= getUserRole() ? ucfirst(getUserRole()) : 'Tidak diketahui' ?>
            </p>
        </div>
    </div>
</body>
</html>