<?php
$page_title = "Detail Pelatih";
require_once '../../includes/header.php';
requireRole(['admin']);

$trainer_id = intval($_GET['id']);

$trainer = $db->fetch("
    SELECT t.*, u.full_name, u.email, u.phone, u.created_at, u.is_active
    FROM trainers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
", [$trainer_id]);

if (!$trainer) {
    redirect('modules/trainers/?error=not_found');
}

// Get trainer statistics
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT c.id) as total_classes,
        COUNT(DISTINCT mc.member_id) as total_students,
        COUNT(DISTINCT s.id) as total_schedules,
        COALESCE(AVG(tr.rating), 0) as avg_rating,
        COUNT(tr.id) as rating_count
    FROM trainers t
    LEFT JOIN classes c ON t.id = c.trainer_id AND c.is_active = 1
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
    LEFT JOIN trainer_ratings tr ON t.id = tr.trainer_id
    WHERE t.id = ?
", [$trainer_id]);

// Get trainer's classes
$classes = $db->fetchAll("
    SELECT c.*, COUNT(mc.member_id) as enrolled_count,
           COUNT(DISTINCT s.id) as schedule_count
    FROM classes c
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
    WHERE c.trainer_id = ? AND c.is_active = 1
    GROUP BY c.id
    ORDER BY c.class_name ASC
", [$trainer_id]);

// Get recent ratings
$recent_ratings = $db->fetchAll("
    SELECT tr.*, u.full_name as member_name, m.member_code
    FROM trainer_ratings tr
    JOIN members m ON tr.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE tr.trainer_id = ?
    ORDER BY tr.created_at DESC
    LIMIT 5
", [$trainer_id]);
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Detail Pelatih</h3>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <a href="edit_trainer.php?id=<?= $trainer_id ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i>
                Edit Pelatih
            </a>
        </div>
    </div>
</div>

<!-- Trainer Profile Header -->
<div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; padding: 40px; border-radius: 20px; margin-bottom: 30px;">
    <div style="display: flex; align-items: center; gap: 30px;">
        <div class="user-avatar" style="width: 120px; height: 120px; font-size: 3rem; background: rgba(255,255,255,0.2); border: 4px solid rgba(255,255,255,0.3);">
            <?= strtoupper(substr($trainer['full_name'], 0, 1)) ?>
        </div>
        
        <div style="flex: 1;">
            <h1 style="margin: 0; margin-bottom: 10px; font-size: 2.5rem;">
                <?= htmlspecialchars($trainer['full_name']) ?>
            </h1>
            <div style="font-size: 1.3rem; margin-bottom: 15px; opacity: 0.9;">
                <strong><?= $trainer['trainer_code'] ?></strong>
            </div>
            
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <div>
                    <i class="fas fa-medal"></i>
                    <strong><?= $trainer['experience_years'] ?></strong> tahun pengalaman
                </div>
                <div>
                    <i class="fas fa-users"></i>
                    <strong><?= $stats['total_students'] ?></strong> murid aktif
                </div>
                <div>
                    <i class="fas fa-chalkboard-teacher"></i>
                    <strong><?= $stats['total_classes'] ?></strong> kelas
                </div>
            </div>
            
            <!-- Rating Display -->
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="color: #FABD32; font-size: 1.5rem;">
                    <?php
                    $rating = round($stats['avg_rating']);
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                    }
                    ?>
                </div>
                <div>
                    <strong style="font-size: 1.2rem;"><?= number_format($stats['avg_rating'], 1) ?></strong>
                    <small style="opacity: 0.8;">(<?= $stats['rating_count'] ?> reviews)</small>
                </div>
            </div>
        </div>
        
        <div style="text-align: center;">
            <?php if ($trainer['is_active']): ?>
                <div style="background: #28a745; padding: 15px 25px; border-radius: 50px; margin-bottom: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 2rem;"></i>
                </div>
                <div style="font-weight: bold;">ACTIVE</div>
            <?php else: ?>
                <div style="background: #6c757d; padding: 15px 25px; border-radius: 50px; margin-bottom: 10px;">
                    <i class="fas fa-pause-circle" style="font-size: 2rem;"></i>
                </div>
                <div style="font-weight: bold;">INACTIVE</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_classes'] ?></h3>
                <p>Total Kelas</p>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_students'] ?></h3>
                <p>Total Murid</p>
            </div>
            <div class="stat-icon red">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_schedules'] ?></h3>
                <p>Jadwal Aktif</p>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-calendar"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $trainer['hourly_rate'] ? formatRupiah($trainer['hourly_rate']) : 'Not Set' ?></h3>
                <p>Tarif per Jam</p>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-money-check-alt"></i>
            </div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
    <!-- Main Details -->
    <div>
        <!-- Personal Information -->
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-user"></i>
                    Informasi Personal
                </h4>
            </div>
            
            <div style="padding: 25px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                    <div>
                        <div style="margin-bottom: 20px;">
                            <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">EMAIL</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-envelope" style="color: #1E459F;"></i>
                                <span><?= htmlspecialchars($trainer['email']) ?></span>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">TELEPON</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-phone" style="color: #CF2A2A;"></i>
                                <span><?= htmlspecialchars($trainer['phone'] ?: 'Tidak diisi') ?></span>
                            </div>
                        </div>
                        
                        <div>
                            <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">BERGABUNG</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-calendar" style="color: #FABD32;"></i>
                                <span><?= formatDate($trainer['hire_date']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div style="margin-bottom: 20px;">
                            <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">SPESIALISASI</label>
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #1E459F;">
                                <?= htmlspecialchars($trainer['specialization'] ?: 'Tidak diisi') ?>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">PENGALAMAN</label>
                            <div style="font-size: 1.3rem; font-weight: bold; color: #CF2A2A;">
                                <?= $trainer['experience_years'] ?> Tahun
                            </div>
                        </div>
                        
                        <div>
                            <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 5px;">TARIF PER JAM</label>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #FABD32;">
                                <?= $trainer['hourly_rate'] ? formatRupiah($trainer['hourly_rate']) : 'Belum diset' ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($trainer['certification']): ?>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                        <label style="font-weight: bold; color: #6c757d; font-size: 0.9rem; display: block; margin-bottom: 8px;">SERTIFIKASI</label>
                        <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #17a2b8;">
                            <?= nl2br(htmlspecialchars($trainer['certification'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Classes Taught -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-chalkboard-teacher"></i>
                    Kelas yang Diajar
                </h4>
                <a href="trainer_classes.php?id=<?= $trainer_id ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-cog"></i>
                    Kelola Kelas
                </a>
            </div>
            
            <?php if (empty($classes)): ?>
                <div style="padding: 40px; text-align: center; color: #6c757d;">
                    <i class="fas fa-chalkboard" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                    <h5>Belum Ada Kelas</h5>
                    <p>Pelatih ini belum ditugaskan ke kelas manapun</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; padding: 20px;">
                    <?php foreach ($classes as $class): ?>
                    <div style="border: 1px solid #dee2e6; border-radius: 10px; padding: 20px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h6 style="color: #1E459F; margin: 0;">
                                <?= htmlspecialchars($class['class_name']) ?>
                            </h6>
                            <span class="badge <?= $class['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                                <?= strtoupper($class['martial_art_type']) ?>
                            </span>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Enrolled:</span>
                                <strong><?= $class['enrolled_count'] ?>/<?= $class['max_participants'] ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="width: <?= ($class['enrolled_count'] / $class['max_participants']) * 100 ?>%; background: #1E459F;"></div>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span class="badge <?= $class['class_type'] === 'regular' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= ucfirst($class['class_type']) ?>
                                </span>
                                <span class="badge badge-secondary">
                                    <?= $class['schedule_count'] ?> jadwal
                                </span>
                            </div>
                            <div style="font-weight: bold; color: #FABD32;">
                                <?= formatRupiah($class['monthly_fee']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div>
        <!-- Contact Card -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-address-card"></i>
                    Kontak
                </h5>
            </div>
            <div style="padding: 20px;">
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <i class="fas fa-envelope" style="color: #1E459F;"></i>
                        <strong>Email</strong>
                    </div>
                    <div style="margin-left: 25px; color: #495057;">
                        <?= htmlspecialchars($trainer['email']) ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <i class="fas fa-phone" style="color: #CF2A2A;"></i>
                        <strong>Telepon</strong>
                    </div>
                    <div style="margin-left: 25px; color: #495057;">
                        <?= htmlspecialchars($trainer['phone'] ?: 'Tidak diisi') ?>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="mailto:<?= $trainer['email'] ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-envelope"></i>
                        Send Email
                    </a>
                    <?php if ($trainer['phone']): ?>
                    <a href="tel:<?= $trainer['phone'] ?>" class="btn btn-outline-success btn-sm" style="margin-left: 8px;">
                        <i class="fas fa-phone"></i>
                        Call
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Reviews -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-star"></i>
                    Review Terbaru
                </h5>
            </div>
            
            <?php if (empty($recent_ratings)): ?>
                <div style="padding: 30px; text-align: center; color: #6c757d;">
                    <i class="fas fa-comments" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>Belum ada review</p>
                </div>
            <?php else: ?>
                <div style="padding: 0;">
                    <?php foreach ($recent_ratings as $rating): ?>
                    <div style="padding: 15px 20px; border-bottom: 1px solid #dee2e6;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div>
                                <strong style="color: #1E459F; font-size: 0.9rem;">
                                    <?= htmlspecialchars($rating['member_name']) ?>
                                </strong>
                                <small style="color: #6c757d; margin-left: 8px;">
                                    <?= $rating['member_code'] ?>
                                </small>
                            </div>
                            <div style="color: #FABD32;">
                                <?php
                                $rating_value = round($rating['rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating_value ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if ($rating['review']): ?>
                        <div style="font-size: 0.9rem; color: #495057; font-style: italic;">
                            "<?= htmlspecialchars($rating['review']) ?>"
                        </div>
                        <?php endif; ?>
                        
                        <div style="font-size: 0.8rem; color: #6c757d; margin-top: 5px;">
                            <?= formatDate($rating['created_at']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>