<?php
$page_title = "Dashboard";
require_once '../../includes/header.php';

// Get base path dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$domain = $_SERVER['HTTP_HOST'];
$base_path = dirname(dirname($_SERVER['REQUEST_URI']));
$full_base_url = $protocol . $domain . $base_path;

// Get basic statistics
$stats = [];

if (getUserRole() === 'admin') {
    $stats['total_members'] = $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE u.is_active = 1")['count'] ?? 0;
    $stats['active_trainers'] = $db->fetch("SELECT COUNT(*) as count FROM trainers t JOIN users u ON t.user_id = u.id WHERE u.is_active = 1")['count'] ?? 0;
    $stats['monthly_income'] = $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE) AND YEAR(payment_date) = YEAR(CURRENT_DATE) AND status = 'paid'")['total'] ?? 0;
    $stats['pending_payments'] = $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")['count'] ?? 0;
    
    $recent_payments = $db->fetchAll("
        SELECT p.*, u.full_name, m.member_code 
        FROM payments p 
        JOIN members m ON p.member_id = m.id 
        JOIN users u ON m.user_id = u.id 
        ORDER BY p.payment_date DESC 
        LIMIT 5
    ");
} elseif (getUserRole() === 'trainer') {
    $stats['classes_today'] = $db->fetch("
        SELECT COUNT(*) as count 
        FROM schedules s 
        JOIN classes c ON s.class_id = c.id 
        JOIN trainers t ON c.trainer_id = t.id 
        WHERE t.user_id = ? AND s.day_of_week = LOWER(DAYNAME(CURRENT_DATE))
    ", [$_SESSION['user_id']])['count'] ?? 0;
    
    $stats['my_students'] = $db->fetch("
        SELECT COUNT(DISTINCT mc.member_id) as count 
        FROM member_classes mc 
        JOIN classes c ON mc.class_id = c.id 
        JOIN trainers t ON c.trainer_id = t.id 
        WHERE t.user_id = ? AND mc.status = 'active'
    ", [$_SESSION['user_id']])['count'] ?? 0;
} else {
    $my_classes = $db->fetchAll("
        SELECT c.*, t.user_id as trainer_user_id, u.full_name as trainer_name,
               s.day_of_week, s.start_time, s.end_time
        FROM member_classes mc 
        JOIN classes c ON mc.class_id = c.id 
        JOIN trainers t ON c.trainer_id = t.id 
        JOIN users u ON t.user_id = u.id 
        JOIN schedules s ON c.id = s.class_id 
        JOIN members m ON mc.member_id = m.id 
        WHERE m.user_id = ? AND mc.status = 'active'
    ", [$_SESSION['user_id']]);
    
    $payment_status = $db->fetch("
        SELECT * FROM payments 
        WHERE member_id = (SELECT id FROM members WHERE user_id = ?) 
        ORDER BY payment_date DESC LIMIT 1
    ", [$_SESSION['user_id']]);
}
?>

<!-- PWA Install Notification dengan Deteksi Platform -->
<div id="pwa-install-banner" style="display: none; background: linear-gradient(45deg, #1E459F, #CF2A2A); color: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.2);">
    <div class="install-banner-content">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="font-size: 3rem;">
                <i class="fas fa-mobile-alt" id="install-icon"></i>
            </div>
            <div>
                <h4 style="margin: 0; color: #FABD32;">
                    <i class="fas fa-star"></i>
                    <span id="install-title">Install EMA Camp App</span>
                </h4>
                <p style="margin: 5px 0 0 0; opacity: 0.9;" id="install-instruction">
                    Akses lebih cepat dengan shortcut di home screen! Bisa digunakan offline dan mendapat notifikasi real-time.
                </p>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button id="pwa-install-button" class="btn btn-lg install-btn">
                <i class="fas fa-download"></i>
                <span id="install-text">Install Now</span>
            </button>
            <button id="pwa-dismiss" class="btn btn-lg dismiss-btn">
                <i class="fas fa-times"></i>
                Nanti
            </button>
        </div>
        
        <!-- Platform Info -->
        <div id="platform-info" style="margin-top: 15px; font-size: 0.85rem; opacity: 0.8; display: none;">
            <i class="fas fa-info-circle"></i>
            <span id="platform-message"></span>
        </div>
    </div>
</div>

<!-- Welcome Message -->
<div class="alert alert-info welcome-message">
    <h4 style="margin: 0; color: #1E459F;">
        <i class="fas fa-hand-paper"></i>
        Selamat datang, <?= $_SESSION['user_name'] ?>!
    </h4>
    <p style="margin: 5px 0 0 0;">
        <?php
        $hour = date('H');
        if ($hour < 12) echo 'Selamat pagi';
        elseif ($hour < 17) echo 'Selamat siang';
        else echo 'Selamat malam';
        ?> dan selamat beraktivitas di EMA Camp Management System.
        
        <!-- Online Status -->
        <span id="online-status" class="badge badge-success status-badge">
            <i class="fas fa-wifi"></i>
            Online
        </span>
        
        <!-- HTTPS Status for PWA -->
        <span id="https-status" class="badge status-badge" style="margin-left: 10px;">
            <i class="fas fa-lock"></i>
            <span id="https-text">Checking SSL...</span>
        </span>
    </p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <?php if (getUserRole() === 'admin'): ?>
        <div class="stat-card blue">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['total_members'] ?></h3>
                    <p>Total Anggota</p>
                </div>
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['active_trainers'] ?></h3>
                    <p>Pelatih Aktif</p>
                </div>
                <div class="stat-icon red">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card yellow">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= formatRupiah($stats['monthly_income']) ?></h3>
                    <p>Pendapatan Bulan Ini</p>
                </div>
                <div class="stat-icon yellow">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card cream">
            <div class="stat-content">
                <div class="stat-info">
                    <h3><?= $stats['pending_payments'] ?></h3>
                    <p>Pembayaran Tertunda</p>
                </div>
                <div class="stat-icon cream">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="card quick-actions-card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h3>
        
        <div class="app-controls">
            <button id="fullscreen-btn" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-expand"></i>
                <span class="btn-text">Fullscreen</span>
            </button>
            <button id="offline-mode-btn" class="btn btn-outline-info btn-sm">
                <i class="fas fa-wifi"></i>
                <span id="offline-text">Online Mode</span>
            </button>
        </div>
    </div>
    
    <div class="quick-actions-content">
        <div class="actions-grid">
            <?php if (getUserRole() === 'admin'): ?>
                <a href="<?= BASE_URL ?>modules/members/add.php" class="btn btn-primary quick-action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Tambah Member Baru</span>
                </a>
                <a href="<?= BASE_URL ?>modules/finance/add_payment.php" class="btn btn-success quick-action-btn">
                    <i class="fas fa-money-bill"></i>
                    <span>Catat Pembayaran</span>
                </a>
                <a href="<?= BASE_URL ?>modules/schedule/add_event.php" class="btn btn-warning quick-action-btn">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Tambah Event</span>
                </a>
                <a href="<?= BASE_URL ?>modules/reports/" class="btn btn-info quick-action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <span>Lihat Laporan</span>
                </a>
            <?php endif; ?>
            
            <a href="<?= BASE_URL ?>modules/schedule/" class="btn btn-primary quick-action-btn">
                <i class="fas fa-calendar"></i>
                <span>Lihat Jadwal</span>
            </a>
            <a href="<?= BASE_URL ?>modules/notifications/" class="btn btn-secondary quick-action-btn">
                <i class="fas fa-bell"></i>
                <span>Notifikasi</span>
            </a>
        </div>
    </div>
</div>

<!-- Desktop PWA Info Modal -->
<div id="desktop-pwa-modal" class="install-modal" style="display: none;">
    <div class="install-modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-desktop"></i>
                Install Desktop App
            </h3>
            <button class="close-modal" onclick="closeDesktopModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="desktop-requirements">
                <h5><i class="fas fa-check-circle text-success"></i> Requirements for Desktop PWA:</h5>
                
                <div class="requirement-list">
                    <div class="requirement-item">
                        <i class="fas fa-lock"></i>
                        <span><strong>HTTPS Required:</strong> Website harus menggunakan SSL certificate</span>
                    </div>
                    
                    <div class="requirement-item">
                        <i class="fab fa-chrome"></i>
                        <span><strong>Browser Support:</strong> Chrome, Edge, atau Opera (Firefox tidak support)</span>
                    </div>
                    
                    <div class="requirement-item">
                        <i class="fas fa-server"></i>
                        <span><strong>Service Worker:</strong> Harus aktif dan registered</span>
                    </div>
                </div>
                
                <div class="current-status">
                    <h6>Status Saat Ini:</h6>
                    <div id="current-protocol" class="status-item">
                        <i class="fas fa-globe"></i>
                        Protocol: <span id="protocol-status"></span>
                    </div>
                    <div id="current-browser" class="status-item">
                        <i class="fas fa-browser"></i>
                        Browser: <span id="browser-status"></span>
                    </div>
                    <div id="current-sw" class="status-item">
                        <i class="fas fa-cog"></i>
                        Service Worker: <span id="sw-status"></span>
                    </div>
                </div>
                
                <div class="install-tip">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Tip:</strong> Jika requirements terpenuhi, tombol install akan muncul otomatis di address bar browser atau di menu "Install App".
                </div>
            </div>
            
            <button onclick="closeDesktopModal()" class="btn btn-primary modal-btn">
                <i class="fas fa-check"></i>
                Mengerti
            </button>
        </div>
    </div>
</div>

<!-- iOS Install Instructions Modal -->
<div id="ios-install-modal" class="install-modal" style="display: none;">
    <div class="install-modal-content">
        <div class="modal-header">
            <h3>
                <i class="fab fa-apple"></i>
                Install di iPhone/iPad
            </h3>
            <button class="close-modal" onclick="closeIOSModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="install-steps">
                <div class="install-step">
                    <span class="step-number">1</span>
                    <i class="fas fa-share step-icon"></i>
                    <span class="step-text">Tap tombol <strong>Share</strong> di Safari</span>
                </div>
                
                <div class="install-step">
                    <span class="step-number">2</span>
                    <i class="fas fa-plus-square step-icon"></i>
                    <span class="step-text">Pilih <strong>"Add to Home Screen"</strong></span>
                </div>
                
                <div class="install-step">
                    <span class="step-number">3</span>
                    <i class="fas fa-check step-icon"></i>
                    <span class="step-text">Tap <strong>"Add"</strong> untuk install</span>
                </div>
            </div>
            
            <div class="install-info">
                <i class="fas fa-info-circle"></i>
                Setelah diinstall, aplikasi akan muncul di home screen dan bisa digunakan seperti aplikasi native!
            </div>
            
            <button onclick="closeIOSModal()" class="btn btn-primary modal-btn">
                <i class="fas fa-check"></i>
                Mengerti
            </button>
        </div>
    </div>
</div>

<!-- Enhanced PWA JavaScript with Desktop Detection -->
<script>
console.log('🚀 Enhanced PWA Script Loading...');

// Comprehensive Device and Browser Detection
const userAgent = navigator.userAgent;
const isIOS = /iPad|iPhone|iPod/.test(userAgent);
const isSafari = /^((?!chrome|android).)*safari/i.test(userAgent);
const isInStandaloneMode = () => ('standalone' in window.navigator) && (window.navigator.standalone);
const isAndroid = /Android/.test(userAgent);
const isChrome = /Chrome/.test(userAgent) && !/Edge/.test(userAgent);
const isEdge = /Edge/.test(userAgent) || /Edg/.test(userAgent);
const isFirefox = /Firefox/.test(userAgent);
const isOpera = /Opera|OPR/.test(userAgent);
const isDesktop = !(/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent));
const isHTTPS = location.protocol === 'https:' || location.hostname === 'localhost';

console.log('📱 Device Detection:', { 
    isIOS, isSafari, isAndroid, isChrome, isEdge, isFirefox, isOpera, 
    isDesktop, isHTTPS, isStandalone: isInStandaloneMode() 
});

// PWA Support Detection
const supportsPWA = isChrome || isEdge || isOpera || (isSafari && isIOS);
const supportsDesktopPWA = isDesktop && (isChrome || isEdge || isOpera) && isHTTPS;

console.log('🔧 PWA Support:', { supportsPWA, supportsDesktopPWA });

// Get dynamic paths
const getCurrentBasePath = () => {
    const path = window.location.pathname;
    const modulesIndex = path.indexOf('/modules');
    return modulesIndex !== -1 ? path.substring(0, modulesIndex) : path.substring(0, path.lastIndexOf('/'));
};

const basePath = getCurrentBasePath();
const swPath = basePath + '/sw.js';

console.log('🛠️ Paths:', { basePath, swPath });

// PWA Elements
let deferredPrompt;
const installBtn = document.getElementById('pwa-install-button');
const installBanner = document.getElementById('pwa-install-banner');
const dismissBtn = document.getElementById('pwa-dismiss');
const installText = document.getElementById('install-text');
const installInstruction = document.getElementById('install-instruction');
const installTitle = document.getElementById('install-title');
const installIcon = document.getElementById('install-icon');
const platformInfo = document.getElementById('platform-info');
const platformMessage = document.getElementById('platform-message');

// HTTPS Status Update
function updateHTTPSStatus() {
    const httpsStatus = document.getElementById('https-status');
    const httpsText = document.getElementById('https-text');
    
    if (isHTTPS) {
        httpsStatus.className = 'badge badge-success status-badge';
        httpsText.textContent = 'Secure (HTTPS)';
    } else {
        httpsStatus.className = 'badge badge-warning status-badge';
        httpsText.textContent = 'Not Secure (HTTP)';
    }
}

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        console.log('📋 Registering Service Worker:', swPath);
        
        navigator.serviceWorker.register(swPath, {
            scope: basePath + '/'
        })
            .then(function(registration) {
                console.log('✅ SW registered successfully', registration.scope);
                updateSWStatus('Active');
                
                registration.addEventListener('updatefound', () => {
                    console.log('🔄 SW Update found');
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateNotification();
                        }
                    });
                });
            })
            .catch(function(error) {
                console.error('❌ SW registration failed:', error);
                updateSWStatus('Failed');
            });
    });
} else {
    console.warn('⚠️ Service Worker not supported');
    updateSWStatus('Not Supported');
}

function updateSWStatus(status) {
    const swStatus = document.getElementById('sw-status');
    if (swStatus) swStatus.textContent = status;
}

// Enhanced PWA Install Banner Logic
function showInstallBanner() {
    console.log('🎯 Checking install banner conditions...');
    
    if (localStorage.getItem('pwa-dismissed-v4')) {
        console.log('📝 Banner dismissed before');
        return;
    }
    
    if (isInStandaloneMode()) {
        console.log('📱 Already in standalone mode');
        return;
    }
    
    if (!supportsPWA) {
        console.log('❌ PWA not supported on this platform');
        return;
    }
    
    setTimeout(() => {
        console.log('🎪 Showing install banner');
        
        if (isDesktop) {
            // Desktop PWA
            installIcon.className = 'fas fa-desktop';
            installTitle.textContent = 'Install Desktop App';
            installInstruction.textContent = 'Install aplikasi di komputer untuk akses yang lebih cepat dan mudah.';
            
            if (!supportsDesktopPWA) {
                platformInfo.style.display = 'block';
                if (!isHTTPS) {
                    platformMessage.textContent = 'Perlu HTTPS untuk install di desktop. Coba di server yang sudah SSL.';
                } else if (!isChrome && !isEdge && !isOpera) {
                    platformMessage.textContent = 'Gunakan Chrome, Edge, atau Opera untuk install di desktop.';
                }
            }
            
        } else if (isIOS && isSafari) {
            // iOS Safari
            installIcon.className = 'fab fa-apple';
            installTitle.textContent = 'Install di iPhone/iPad';
            installText.innerHTML = '<i class="fas fa-info-circle"></i> How to Install';
            installInstruction.innerHTML = 'Tap <i class="fas fa-share"></i> Share button, then "Add to Home Screen".';
            
        } else if (isAndroid) {
            // Android
            installIcon.className = 'fab fa-android';
            installTitle.textContent = 'Install Android App';
            installInstruction.textContent = 'Install aplikasi untuk pengalaman yang lebih baik dan notifikasi real-time.';
        }
        
        installBanner.style.display = 'block';
        
    }, 2000);
}

// Desktop PWA Modal Functions
function showDesktopPWAInfo() {
    console.log('🖥️ Showing desktop PWA info');
    const modal = document.getElementById('desktop-pwa-modal');
    
    // Update status
    document.getElementById('protocol-status').textContent = isHTTPS ? 'HTTPS ✅' : 'HTTP ❌';
    document.getElementById('browser-status').textContent = getBrowserName();
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDesktopModal() {
    document.getElementById('desktop-pwa-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function getBrowserName() {
    if (isChrome) return 'Chrome ✅';
    if (isEdge) return 'Edge ✅';  
    if (isOpera) return 'Opera ✅';
    if (isFirefox) return 'Firefox ❌';
    if (isSafari) return 'Safari ❌';
    return 'Unknown';
}

// Enhanced beforeinstallprompt handler
window.addEventListener('beforeinstallprompt', (e) => {
    console.log('🎊 beforeinstallprompt fired');
    e.preventDefault();
    deferredPrompt = e;
    
    installBanner.style.display = 'block';
    
    const handleInstall = async () => {
        console.log('⬇️ Install clicked');
        if (deferredPrompt) {
            try {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                
                console.log('👤 User choice:', outcome);
                
                if (outcome === 'accepted') {
                    console.log('✅ PWA install accepted');
                    installBanner.style.display = 'none';
                    showNotification('🎉 Aplikasi berhasil diinstall!', 'success');
                    localStorage.setItem('pwa-installed', 'true');
                } else {
                    console.log('❌ PWA install dismissed');
                }
                
                deferredPrompt = null;
            } catch (error) {
                console.error('💥 Install error:', error);
                showNotification('Gagal menginstall aplikasi', 'error');
            }
        }
    };
    
    // Event listener for install button
    if (installBtn) {
        const newInstallBtn = installBtn.cloneNode(true);
        installBtn.parentNode.replaceChild(newInstallBtn, installBtn);
        
        newInstallBtn.addEventListener('click', (e) => {
            e.preventDefault();
            
            if (isDesktop && !supportsDesktopPWA) {
                showDesktopPWAInfo();
            } else if (isIOS && isSafari) {
                showIOSInstructions();
            } else {
                handleInstall();
            }
        });
    }
});

// App Installation Success
window.addEventListener('appinstalled', (evt) => {
    console.log('🎉 PWA installed successfully');
    installBanner.style.display = 'none';
    showNotification('🚀 EMA Camp siap digunakan!', 'success');
    localStorage.setItem('pwa-installed', 'true');
});

// iOS Installation Instructions
function showIOSInstructions() {
    console.log('🍎 Showing iOS instructions');
    document.getElementById('ios-install-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeIOSModal() {
    document.getElementById('ios-install-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Dismiss Banner
if (dismissBtn) {
    dismissBtn.addEventListener('click', () => {
        console.log('❌ Banner dismissed');
        installBanner.style.display = 'none';
        localStorage.setItem('pwa-dismissed-v4', 'true');
        showNotification('💡 Anda bisa install aplikasi kapan saja dari menu browser', 'info');
    });
}

// Fullscreen Toggle
const fullscreenBtn = document.getElementById('fullscreen-btn');
if (fullscreenBtn) {
    fullscreenBtn.addEventListener('click', function() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
            this.innerHTML = '<i class="fas fa-expand"></i><span class="btn-text">Fullscreen</span>';
        } else {
            document.documentElement.requestFullscreen().catch(err => {
                console.warn('Fullscreen failed:', err);
            });
            this.innerHTML = '<i class="fas fa-compress"></i><span class="btn-text">Exit Fullscreen</span>';
        }
    });
}

// Online/Offline Status
function updateOnlineStatus() {
    const status = document.getElementById('online-status');
    const offlineBtn = document.getElementById('offline-mode-btn');
    const offlineText = document.getElementById('offline-text');
    
    if (navigator.onLine) {
        if (status) {
            status.className = 'badge badge-success status-badge';
            status.innerHTML = '<i class="fas fa-wifi"></i> Online';
        }
        if (offlineText) offlineText.textContent = 'Online Mode';
        if (offlineBtn) offlineBtn.className = 'btn btn-outline-success btn-sm';
    } else {
        if (status) {
            status.className = 'badge badge-warning status-badge';
            status.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline';
        }
        if (offlineText) offlineText.textContent = 'Offline Mode';
        if (offlineBtn) offlineBtn.className = 'btn btn-outline-warning btn-sm';
    }
}

// Network Events
window.addEventListener('online', () => {
    console.log('📶 Back online');
    updateOnlineStatus();
    showNotification('Koneksi internet kembali normal', 'success', 3000);
});

window.addEventListener('offline', () => {
    console.log('📵 Gone offline');
    updateOnlineStatus();
    showNotification('Anda sedang offline. Beberapa fitur mungkin terbatas.', 'warning', 5000);
});

// Enhanced Notification System
function showNotification(message, type = 'info', duration = 4000) {
    console.log('🔔 Showing notification:', message, type);
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle', 
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="notification-close">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    requestAnimationFrame(() => {
        notification.classList.add('show');
    });
    
    if (duration > 0) {
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, duration);
    }
}

// Update Notification
function showUpdateNotification() {
    console.log('🔄 Showing update notification');
    const updateBanner = document.createElement('div');
    updateBanner.className = 'update-banner';
    updateBanner.innerHTML = `
        <div class="update-content">
            <div class="update-info">
                <h6><i class="fas fa-download"></i> Update Tersedia</h6>
                <p>Versi baru EMA Camp tersedia dengan fitur dan perbaikan terbaru.</p>
            </div>
            <div class="update-actions">
                <button onclick="updateApp()" class="btn btn-sm btn-warning">
                    <i class="fas fa-sync"></i> Update
                </button>
                <button onclick="this.parentElement.parentElement.remove()" class="btn btn-sm btn-secondary">
                    Nanti
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(updateBanner);
}

function updateApp() {
    console.log('🔄 Updating app...');
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for(let registration of registrations) {
                registration.unregister();
            }
            showNotification('Aplikasi sedang diupdate...', 'info');
            setTimeout(() => {
                window.location.reload(true);
            }, 1000);
        });
    }
}

// Enhanced Quick Actions
function initQuickActions() {
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
}

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 DOM Loaded - Initializing...');
    
    updateOnlineStatus();
    updateHTTPSStatus();
    initQuickActions();
    showInstallBanner();
    
    if (isInStandaloneMode()) {
        document.body.classList.add('pwa-standalone');
        console.log('📱 Running in standalone mode');
    }
    
    if (localStorage.getItem('pwa-installed')) {
        console.log('✅ PWA already installed');
        if (installBanner) installBanner.style.display = 'none';
    }
    
    console.log('🚀 App initialization complete');
});

// iOS Specific Optimizations
if (isIOS) {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🍎 Applying iOS optimizations...');
        
        const inputs = document.querySelectorAll('input, select, textarea');
        const viewport = document.querySelector('meta[name="viewport"]');
        
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (viewport) {
                    viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
                }
            });
            input.addEventListener('blur', function() {
                if (viewport) {
                    viewport.setAttribute('content', 'width=device-width, initial-scale=1.0');
                }
            });
        });
    });
}

// Debug info
console.log('🔍 PWA Debug Info:', {
    basePath, swPath, isIOS, isSafari, isAndroid, isChrome, isEdge,
    isDesktop, isHTTPS, supportsPWA, supportsDesktopPWA, 
    isStandalone: isInStandaloneMode()
});
</script>

<!-- Enhanced PWA Styles -->
<style>
/* Hide PWA Install Button in Header - sesuai permintaan */
#pwa-install-btn {
    display: none !important;
}

/* Install Banner dengan Background Tombol Putih */
.install-banner-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.install-btn {
    background: white !important;
    color: #1E459F !important;
    border: 2px solid #1E459F !important;
    font-weight: bold;
    padding: 12px 25px;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.install-btn:hover {
    background: #f8f9fa !important;
    color: #1E459F !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(30, 69, 159, 0.2);
}

.install-btn:active {
    transform: translateY(0);
    background: #e9ecef !important;
}

.dismiss-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 12px 20px;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.dismiss-btn:hover {
    background: rgba(255,255,255,0.3);
}

/* Desktop PWA Modal */
.desktop-requirements {
    text-align: left;
}

.requirement-list {
    margin: 20px 0;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.requirement-item i {
    color: #1E459F;
    font-size: 1.2rem;
    min-width: 24px;
}

.current-status {
    margin: 20px 0;
    padding: 15px;
    background: #e3f2fd;
    border-radius: 10px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 8px 0;
}

.status-item i {
    color: #1976d2;
    min-width: 20px;
}

.install-tip {
    padding: 15px;
    background: #fff3cd;
    border-radius: 8px;
    color: #856404;
    margin: 15px 0;
}

/* Welcome Message */
.welcome-message {
    background: linear-gradient(45deg, rgba(30, 69, 159, 0.1), rgba(250, 189, 50, 0.1));
    border-left: 5px solid #1E459F;
    margin-bottom: 30px;
    border-radius: 15px;
    padding: 20px;
}

.status-badge {
    margin-left: 10px;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Quick Actions Card */
.quick-actions-card {
    margin-top: 30px;
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.quick-actions-card .card-header {
    background: linear-gradient(135deg, #1E459F, #2056b8);
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.app-controls {
    display: flex;
    gap: 10px;
}

.quick-actions-content {
    padding: 25px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    cursor: pointer;
}

.quick-action-btn i {
    font-size: 2rem;
}

.quick-action-btn span {
    font-weight: 600;
    text-align: center;
}

.quick-action-btn:hover {
    text-decoration: none;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

/* Enhanced Mobile Responsive */
@media (max-width: 768px) {
    .install-banner-content {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .install-banner-content > div:first-child {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .requirement-item {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }
    
    .install-btn, .dismiss-btn {
        width: 100%;
        margin: 5px 0;
    }
}

/* Rest of the existing styles remain the same... */
/* ... (semua style notification, modal, stats, dll tetap sama) ... */
</style>

<?php require_once '../../includes/footer.php'; ?>