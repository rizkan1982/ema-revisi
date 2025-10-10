<?php
$page_title = "Detail Member";
require_once '../../includes/header.php';
requireRole(['admin', 'trainer']);

$member_id = intval($_GET['id']);

// Get member detail
$member = $db->fetch("
    SELECT m.*, u.full_name, u.email, u.phone, u.username, u.created_at, u.is_active
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.id = ?
", [$member_id]);

if (!$member) {
    redirect('modules/members/?error=member_not_found');
}

// Ultra safe function to check table and columns
function ultraSafeQuery($db, $query, $params = []) {
    try {
        return $db->fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Database query failed: " . $e->getMessage());
        return [];
    }
}

function ultraSafeCount($db, $query, $params = []) {
    try {
        $result = $db->fetch($query, $params);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Database count failed: " . $e->getMessage());
        return 0;
    }
}

// Get statistics with ultra safe queries
$stats = [
    'total_classes' => ultraSafeCount($db, "SELECT COUNT(*) as count FROM member_classes WHERE member_id = ?", [$member_id]),
    'active_classes' => ultraSafeCount($db, "SELECT COUNT(*) as count FROM member_classes WHERE member_id = ? AND status = 'active'", [$member_id]),
    'total_payments' => ultraSafeCount($db, "SELECT COUNT(*) as count FROM payments WHERE member_id = ?", [$member_id]),
    'paid_payments' => ultraSafeCount($db, "SELECT COUNT(*) as count FROM payments WHERE member_id = ? AND status = 'paid'", [$member_id]),
    'total_attendance' => ultraSafeCount($db, "SELECT COUNT(*) as count FROM attendances WHERE member_id = ?", [$member_id]),
    'present_attendance' => ultraSafeCount($db, "SELECT COUNT(*) as count FROM attendances WHERE member_id = ? AND status = 'present'", [$member_id])
];

// Simple safe queries for recent data
$recent_payments = ultraSafeQuery($db, "
    SELECT 
        id,
        member_id,
        COALESCE(amount, 0) as amount,
        COALESCE(status, 'pending') as status,
        COALESCE(payment_date, created_at) as payment_date,
        COALESCE(notes, 'Payment') as class_name,
        created_at
    FROM payments 
    WHERE member_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$member_id]);

$recent_attendance = ultraSafeQuery($db, "
    SELECT 
        a.id,
        a.member_id,
        a.status,
        a.created_at,
        'Class Session' as class_name,
        DATE(a.created_at) as class_date,
        TIME(a.created_at) as start_time
    FROM attendances a
    WHERE a.member_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 5
", [$member_id]);

// Display functions
function getMartialArtDisplayName($type) {
    $types = [
        'savate' => 'Savate (French Kickboxing)',
        'kickboxing' => 'Kickboxing',
        'boxing' => 'Boxing'
    ];
    return $types[$type] ?? ucfirst($type);
}

function getClassTypeDisplayName($type) {
    $types = [
        'regular' => 'Regular (Kelas Grup)',
        'private_6x' => 'Private - 6x Sebulan',
        'private_8x' => 'Private - 8x Sebulan',
        'private_10x' => 'Private - 10x Sebulan'
    ];
    return $types[$type] ?? ucfirst($type);
}

function getBadgeClass($type, $category = 'martial_art') {
    if ($category === 'martial_art') {
        $classes = [
            'savate' => 'badge-primary',
            'kickboxing' => 'badge-info', 
            'boxing' => 'badge-warning'
        ];
    } else {
        $classes = [
            'regular' => 'badge-success',
            'private_6x' => 'badge-warning',
            'private_8x' => 'badge-info',
            'private_10x' => 'badge-danger'
        ];
    }
    return $classes[$type] ?? 'badge-secondary';
}

$attendance_percentage = $stats['total_attendance'] > 0 ? round(($stats['present_attendance'] / $stats['total_attendance']) * 100, 1) : 0;
?>

<style>
.stat-card {
    border: none;
    border-radius: 15px;
    color: white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.info-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}
.badge-primary { background: linear-gradient(135deg, #007bff, #0056b3); }
.badge-info { background: linear-gradient(135deg, #17a2b8, #138496); }
.badge-warning { background: linear-gradient(135deg, #ffc107, #d39e00); }
.badge-success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.badge-danger { background: linear-gradient(135deg, #dc3545, #c82333); }
.badge-secondary { background: linear-gradient(135deg, #6c757d, #5a6268); }
@media (max-width: 768px) {
    .mobile-stack { flex-direction: column !important; }
    .mobile-full { width: 100% !important; margin-bottom: 10px; }
}
</style>

<!-- Header Section -->
<div style="margin-bottom: 30px;">
    <div class="card" style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white; border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(30,69,159,0.3);">
        <div class="card-body" style="padding: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;" class="mobile-stack">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 2rem; font-weight: bold;"><?= htmlspecialchars($member['full_name']) ?></h2>
                        <div style="margin: 8px 0; opacity: 0.9; font-size: 1.1rem;">
                            <i class="fas fa-id-badge"></i>
                            <strong><?= $member['member_code'] ?></strong>
                            <span style="margin: 0 15px;">|</span>
                            <i class="fas fa-user"></i>
                            <?= $member['username'] ?>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 8px 15px; font-size: 0.9rem;">
                                <i class="fas fa-fist-raised" style="margin-right: 5px;"></i>
                                <?= getMartialArtDisplayName($member['martial_art_type']) ?>
                            </span>
                            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 8px 15px; font-size: 0.9rem;">
                                <i class="fas fa-users" style="margin-right: 5px;"></i>
                                <?= getClassTypeDisplayName($member['class_type']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;" class="mobile-stack">
                    <a href="edit.php?id=<?= $member_id ?>" class="btn btn-warning mobile-full" style="border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; text-decoration: none;">
                        <i class="fas fa-edit"></i> Edit Member
                    </a>
                    <a href="attendance.php?id=<?= $member_id ?>" class="btn btn-success mobile-full" style="border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; text-decoration: none;">
                        <i class="fas fa-check"></i> Kelola Absensi
                    </a>
                    <a href="index.php" class="btn mobile-full" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
        <div class="card-body" style="padding: 25px; text-align: center;">
            <i class="fas fa-chalkboard-teacher" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.9;"></i>
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['active_classes'] ?></h3>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1.1rem;">Kelas Aktif</p>
            <small style="opacity: 0.7;">dari <?= $stats['total_classes'] ?> total</small>
        </div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #007bff, #0056b3);">
        <div class="card-body" style="padding: 25px; text-align: center;">
            <i class="fas fa-money-bill-wave" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.9;"></i>
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['paid_payments'] ?></h3>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1.1rem;">Pembayaran Lunas</p>
            <small style="opacity: 0.7;">dari <?= $stats['total_payments'] ?> total</small>
        </div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #ffc107, #d39e00);">
        <div class="card-body" style="padding: 25px; text-align: center;">
            <i class="fas fa-calendar-check" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.9;"></i>
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $stats['present_attendance'] ?></h3>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1.1rem;">Kehadiran</p>
            <small style="opacity: 0.7;">dari <?= $stats['total_attendance'] ?> sesi</small>
        </div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1, #59359a);">
        <div class="card-body" style="padding: 25px; text-align: center;">
            <i class="fas fa-percentage" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.9;"></i>
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?= $attendance_percentage ?>%</h3>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1.1rem;">Tingkat Kehadiran</p>
            <small style="opacity: 0.7;">persentase hadir</small>
        </div>
    </div>
</div>

<!-- Main Content -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;" class="mobile-stack">
    <!-- Member Details -->
    <div>
        <div class="info-card">
            <div style="background: linear-gradient(135deg, #1E459F, #2056b8); color: white; border-radius: 15px 15px 0 0; padding: 20px;">
                <h4 style="margin: 0; font-size: 1.3rem;">
                    <i class="fas fa-user"></i> Informasi Detail Member
                </h4>
            </div>
            <div style="padding: 30px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;" class="mobile-stack">
                    <div>
                        <h5 style="color: #1E459F; margin-bottom: 20px; font-size: 1.2rem;">
                            <i class="fas fa-user-circle"></i> Data Personal
                        </h5>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Nama Lengkap:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1E459F;">
                                <strong><?= htmlspecialchars($member['full_name']) ?></strong>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Email:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
                                <i class="fas fa-envelope" style="margin-right: 8px; color: #007bff;"></i>
                                <a href="mailto:<?= htmlspecialchars($member['email']) ?>" style="color: #007bff; text-decoration: none;">
                                    <?= htmlspecialchars($member['email']) ?>
                                </a>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Nomor Telepon:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
                                <i class="fas fa-phone" style="margin-right: 8px; color: #28a745;"></i>
                                <?= $member['phone'] ? '<a href="tel:'.htmlspecialchars($member['phone']).'" style="color: #28a745; text-decoration: none;">'.htmlspecialchars($member['phone']).'</a>' : '<em style="color: #6c757d;">Tidak diisi</em>' ?>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Tanggal Lahir:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ffc107;">
                                <i class="fas fa-birthday-cake" style="margin-right: 8px; color: #ffc107;"></i>
                                <?= $member['birth_date'] ? formatDate($member['birth_date']) : '<em style="color: #6c757d;">Tidak diisi</em>' ?>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Kontak Darurat:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #dc3545;">
                                <i class="fas fa-phone-alt" style="margin-right: 8px; color: #dc3545;"></i>
                                <?= $member['emergency_contact'] ? '<a href="tel:'.htmlspecialchars($member['emergency_contact']).'" style="color: #dc3545; text-decoration: none;">'.htmlspecialchars($member['emergency_contact']).'</a>' : '<em style="color: #6c757d;">Tidak diisi</em>' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h5 style="color: #1E459F; margin-bottom: 20px; font-size: 1.2rem;">
                            <i class="fas fa-fist-raised"></i> Informasi Member
                        </h5>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Kode Member:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #6f42c1; font-family: monospace; font-size: 1.1rem;">
                                <i class="fas fa-id-badge" style="margin-right: 8px; color: #6f42c1;"></i>
                                <strong style="color: #6f42c1;"><?= $member['member_code'] ?></strong>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Username:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #17a2b8;">
                                <i class="fas fa-user" style="margin-right: 8px; color: #17a2b8;"></i>
                                <code style="background: none; color: #17a2b8; font-size: 1rem;"><?= htmlspecialchars($member['username']) ?></code>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Bergabung:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
                                <i class="fas fa-calendar-plus" style="margin-right: 8px; color: #28a745;"></i>
                                <?= formatDate($member['join_date']) ?>
                                <small style="color: #6c757d; display: block; margin-top: 5px;">
                                    <?= floor((time() - strtotime($member['join_date'])) / (60 * 60 * 24)) ?> hari yang lalu
                                </small>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Level Sabuk:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #fd7e14;">
                                <i class="fas fa-medal" style="margin-right: 8px; color: #fd7e14;"></i>
                                <?= $member['belt_level'] ? htmlspecialchars($member['belt_level']) : '<em style="color: #6c757d;">Pemula/Belum ditentukan</em>' ?>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: 600; color: #495057; display: block; margin-bottom: 8px;">Status Akun:</label>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px;">
                                <?php if ($member['is_active']): ?>
                                    <span class="badge badge-success" style="padding: 8px 15px; font-size: 0.9rem;">
                                        <i class="fas fa-check-circle"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="padding: 8px 15px; font-size: 0.9rem;">
                                        <i class="fas fa-times-circle"></i> Nonaktif
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Address and Medical Notes -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f1f3f4;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="mobile-stack">
                        <div>
                            <h6 style="color: #1E459F; margin-bottom: 15px; font-size: 1.1rem;">
                                <i class="fas fa-map-marker-alt"></i> Alamat Lengkap
                            </h6>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; min-height: 80px; border-left: 4px solid #17a2b8;">
                                <?= $member['address'] ? nl2br(htmlspecialchars($member['address'])) : '<em style="color: #6c757d;">Alamat belum diisi</em>' ?>
                            </div>
                        </div>
                        <div>
                            <h6 style="color: #1E459F; margin-bottom: 15px; font-size: 1.1rem;">
                                <i class="fas fa-notes-medical"></i> Catatan Medis
                            </h6>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; min-height: 80px; border-left: 4px solid #ffc107;">
                                <?= $member['medical_notes'] ? nl2br(htmlspecialchars($member['medical_notes'])) : '<em style="color: #6c757d;">Tidak ada catatan khusus</em>' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activities Sidebar -->
    <div>
        <!-- Recent Payments -->
        <div class="info-card">
            <div style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 15px 15px 0 0; padding: 15px 20px;">
                <h5 style="margin: 0; font-size: 1.1rem;">
                    <i class="fas fa-money-bill-wave"></i> Riwayat Pembayaran
                </h5>
            </div>
            <div style="padding: 20px; max-height: 350px; overflow-y: auto;">
                <?php if (empty($recent_payments)): ?>
                    <div style="text-align: center; color: #6c757d; padding: 40px 0;">
                        <i class="fas fa-file-invoice-dollar" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                        <p style="margin: 0;"><strong>Belum ada pembayaran</strong></p>
                        <small>Riwayat pembayaran akan muncul di sini</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_payments as $payment): ?>
                        <div style="border-bottom: 1px solid #f1f3f4; padding: 15px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong style="color: #1E459F;"><?= htmlspecialchars($payment['class_name'] ?? 'Payment') ?></strong>
                                    <div style="color: #28a745; font-weight: 600; margin-top: 5px;">
                                        Rp <?= number_format($payment['amount'] ?? 0, 0, ',', '.') ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge <?= ($payment['status'] ?? 'pending') === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ($payment['status'] ?? 'pending') === 'paid' ? 'Lunas' : 'Pending' ?>
                                    </span>
                                    <div style="color: #6c757d; font-size: 0.85rem; margin-top: 5px;">
                                        <?= formatDate($payment['payment_date'] ?? $payment['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Attendance -->
        <div class="info-card">
            <div style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; border-radius: 15px 15px 0 0; padding: 15px 20px;">
                <h5 style="margin: 0; font-size: 1.1rem;">
                    <i class="fas fa-calendar-check"></i> Riwayat Kehadiran
                </h5>
            </div>
            <div style="padding: 20px; max-height: 350px; overflow-y: auto;">
                <?php if (empty($recent_attendance)): ?>
                    <div style="text-align: center; color: #6c757d; padding: 40px 0;">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                        <p style="margin: 0;"><strong>Belum ada absensi</strong></p>
                        <small>Riwayat kehadiran akan muncul di sini</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_attendance as $attendance): ?>
                        <div style="border-bottom: 1px solid #f1f3f4; padding: 15px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong style="color: #1E459F;"><?= htmlspecialchars($attendance['class_name'] ?? 'Class Session') ?></strong>
                                    <div style="color: #6c757d; font-size: 0.9rem; margin-top: 5px;">
                                        <?= formatDate($attendance['class_date'] ?? $attendance['created_at']) ?>
                                        <?php if (!empty($attendance['start_time']) && $attendance['start_time'] !== '00:00:00'): ?>
                                            | <?= $attendance['start_time'] ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $status = $attendance['status'] ?? 'absent';
                                    $status_classes = [
                                        'present' => 'badge-success',
                                        'late' => 'badge-warning', 
                                        'absent' => 'badge-danger',
                                        'excused' => 'badge-info'
                                    ];
                                    $status_labels = [
                                        'present' => 'Hadir',
                                        'late' => 'Terlambat',
                                        'absent' => 'Tidak Hadir', 
                                        'excused' => 'Izin'
                                    ];
                                    ?>
                                    <span class="badge <?= $status_classes[$status] ?? 'badge-secondary' ?>">
                                        <?= $status_labels[$status] ?? ucfirst($status) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>