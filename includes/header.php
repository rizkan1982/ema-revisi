<?php
require_once '../../config/config.php';
requireLogin();

// Ambil data user dari session untuk digunakan di bawah
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'guest';
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> - <?= APP_NAME ?></title>

    <!-- FONTS & ICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS ANDA (SEMUA FILE DIGABUNGKAN) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/enchanced.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pwa_enchanced.css">

    <!-- PWA META TAGS -->
    <meta name="theme-color" content="#1E459F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="<?= BASE_URL ?>manifest.json">

    <!-- ICONS -->
    <link rel="icon" href="<?= BASE_URL ?>assets/images/ema-logo.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/images/ema-logo.png">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar (Struktur Sesuai CSS Anda) -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="<?= BASE_URL ?>assets/images/emariview.png" alt="EMA Camp Logo" class="sidebar-logo" style="width:100px; height:auto; border-radius:15px;" >
                <h3>EMA CAMP</h3>
                <small>Management System</small>
                <div style="margin-top: 15px;">
                    <span class="badge" style="background-color: rgba(250, 189, 50, 0.2); color: var(--primary-yellow);">
                        <i class="fas fa-crown"></i>
                        <?= strtoupper($userRole) ?>
                    </span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li>
                    <a href="<?= BASE_URL ?>modules/dashboard/" class="<?= strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt fa-fw"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                 <?php if (in_array(getUserRole(), ['admin', 'trainer'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/members/" class="<?= strpos($_SERVER['REQUEST_URI'], '/members/') !== false ? 'active' : '' ?>">
                        <i class="fas fa-users fa-fw"></i>
                        <span>Manajemen Anggota</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (getUserRole() === 'admin'): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/finance/" class="<?= strpos($_SERVER['REQUEST_URI'], '/finance/') !== false ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave fa-fw"></i>
                        <span>Keuangan</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/schedule/" class="<?= strpos($_SERVER['REQUEST_URI'], '/schedule/') !== false ? 'active' : '' ?>">
                        <i class="fas fa-calendar-alt fa-fw"></i>
                        <span>Jadwal & Kegiatan</span>
                    </a>
                </li>
                <?php if (getUserRole() === 'admin'): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/trainers/" class="<?= strpos($_SERVER['REQUEST_URI'], '/trainers/') !== false ? 'active' : '' ?>">
                        <i class="fas fa-user-tie fa-fw"></i>
                        <span>Pelatih & Staff</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/" class="<?= strpos($_SERVER['REQUEST_URI'], '/reports/') !== false ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar fa-fw"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/notifications/" class="<?= strpos($_SERVER['REQUEST_URI'], '/notifications/') !== false ? 'active' : '' ?>">
                        <i class="fas fa-bell fa-fw"></i>
                        <span>Notifikasi</span>
                        <!-- ID ditambahkan di sini untuk real-time update -->
                        <span id="notification-badge" class="notification-badge" style="display: none;"></span>
                    </a>
                </li>
                <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                    <a href="<?= BASE_URL ?>modules/auth/logout.php">
                        <i class="fas fa-sign-out-alt fa-fw"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <h1><?= $page_title ?? 'Dashboard' ?></h1>
                </div>
                <div class="header-right">
                    <!-- Tombol PWA Install (jika diperlukan oleh JS Anda) -->
                    <button id="pwa-install-btn" class="btn btn-primary" style="display:none;">Install App</button>
                    <div class="user-info">
                        <div class="user-avatar"><?= $userInitial ?></div>
                        <div class="user-details mobile-hide">
                            <strong><?= $userName ?></strong>
                            <small><?= ucfirst($userRole) ?></small>
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="content">
            <!-- Konten halaman dinamis Anda akan muncul di sini -->