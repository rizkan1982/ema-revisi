<?php
require_once '../../config/config.php';
requireLogin();
requireRole(['admin']);

$page_title = "Manajemen Pelatih & Staff";
require_once '../../includes/header.php';

// Check for success message dari redirect
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Get all trainers with their statistics
$trainers = $db->fetchAll("
    SELECT t.*, u.full_name, u.email, u.phone, u.created_at, u.is_active,
           COUNT(DISTINCT c.id) as total_classes,
           COUNT(DISTINCT mc.member_id) as total_students,
           COALESCE(AVG(rating.rating), 0) as avg_rating,
           COUNT(DISTINCT rating.id) as total_ratings
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN classes c ON t.id = c.trainer_id AND c.is_active = 1
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN (
        SELECT trainer_id, rating, id FROM trainer_ratings
    ) rating ON t.id = rating.trainer_id
    GROUP BY t.id, u.id
    ORDER BY u.full_name ASC
");

$stats = [
    'total_trainers' => count($trainers),
    'active_trainers' => count(array_filter($trainers, function($t) { return $t['is_active']; })),
    'total_classes' => array_sum(array_column($trainers, 'total_classes')),
    'avg_experience' => count($trainers) > 0 ? round(array_sum(array_column($trainers, 'experience_years')) / count($trainers), 1) : 0
];
?>

<!-- Success Message -->
<?php if ($success): ?>
<div class="alert alert-success" style="border-left: 5px solid #28a745; animation: slideIn 0.5s ease;">
    <i class="fas fa-check-circle"></i>
    <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_trainers'] ?></h3>
                <p>Total Pelatih</p>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-user-tie"></i>
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
                <i class="fas fa-user-check"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_classes'] ?></h3>
                <p>Total Kelas</p>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['avg_experience'] ?></h3>
                <p>Rata-rata Pengalaman (Tahun)</p>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-medal"></i>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <a href="add_trainer.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Tambah Pelatih
            </a>
            <a href="trainer_schedule.php" class="btn btn-success">
                <i class="fas fa-calendar-alt"></i>
                Jadwal Pelatih
            </a>
            <a href="trainer_performance.php" class="btn btn-warning">
                <i class="fas fa-chart-line"></i>
                Performance Report
            </a>
            <a href="payroll.php" class="btn btn-info">
                <i class="fas fa-money-check-alt"></i>
                Gaji & Payroll
            </a>
        </div>
    </div>
</div>

<!-- Trainers Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px;">
    <?php foreach ($trainers as $trainer): ?>
    <div class="trainer-card" style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.3s ease; border-left: 5px solid <?= $trainer['is_active'] ? '#28a745' : '#6c757d' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="trainer-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #1E459F, #CF2A2A); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold;">
                    <?= strtoupper(substr($trainer['full_name'], 0, 1)) ?>
                </div>
                
                <div>
                    <h4 style="color: #1E459F; margin: 0; font-size: 1.2rem;">
                        <?= htmlspecialchars($trainer['full_name']) ?>
                    </h4>
                    <div style="color: #6c757d; font-size: 0.9rem; margin-top: 2px;">
                        <?= $trainer['trainer_code'] ?>
                    </div>
                    <div style="margin-top: 5px;">
                        <?php if ($trainer['is_active']): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle"></i>
                                Aktif
                            </span>
                        <?php else: ?>
                            <span class="badge badge-secondary">
                                <i class="fas fa-pause-circle"></i>
                                Non-Aktif
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Rating Stars -->
            <div style="text-align: right;">
                <div class="rating-stars" style="color: #FABD32; font-size: 1.2rem;">
                    <?php
                    $rating = round($trainer['avg_rating']);
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                    }
                    ?>
                </div>
                <small class="text-muted">
                    <?= number_format($trainer['avg_rating'], 1) ?> (<?= $trainer['total_ratings'] ?> ulasan)
                </small>
            </div>
        </div>
        
        <!-- Trainer Details -->
        <div style="margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <div style="color: #6c757d; font-size: 0.85rem; margin-bottom: 3px;">Spesialisasi</div>
                    <div style="font-weight: 500; color: #1E459F;">
                        <?= htmlspecialchars($trainer['specialization'] ?: 'Tidak diisi') ?>
                    </div>
                </div>
                
                <div>
                    <div style="color: #6c757d; font-size: 0.85rem; margin-bottom: 3px;">Pengalaman</div>
                    <div style="font-weight: 500; color: #CF2A2A;">
                        <?= $trainer['experience_years'] ?> Tahun
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <div style="color: #6c757d; font-size: 0.85rem; margin-bottom: 3px;">Total Kelas</div>
                    <div style="font-weight: 500; color: #FABD32;">
                        <i class="fas fa-chalkboard"></i>
                        <?= $trainer['total_classes'] ?> Kelas
                    </div>
                </div>
                
                <div>
                    <div style="color: #6c757d; font-size: 0.85rem; margin-bottom: 3px;">Total Murid</div>
                    <div style="font-weight: 500; color: #28a745;">
                        <i class="fas fa-users"></i>
                        <?= $trainer['total_students'] ?> Murid
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Info -->
        <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <div style="margin-bottom: 8px; font-size: 0.9rem;">
                <i class="fas fa-envelope" style="color: #1E459F; margin-right: 8px;"></i>
                <?= htmlspecialchars($trainer['email']) ?>
            </div>
            <div style="font-size: 0.9rem;">
                <i class="fas fa-phone" style="color: #CF2A2A; margin-right: 8px;"></i>
                <?= htmlspecialchars($trainer['phone'] ?: 'Tidak diisi') ?>
            </div>
        </div>
        
        <!-- Hourly Rate -->
        <div style="margin-bottom: 20px; text-align: center; padding: 12px; background: linear-gradient(45deg, rgba(250, 189, 50, 0.1), rgba(225, 220, 202, 0.1)); border-radius: 8px;">
            <div style="color: #6c757d; font-size: 0.85rem;">Tarif per Jam</div>
            <div style="font-size: 1.3rem; font-weight: bold; color: #1E459F;">
                <?= $trainer['hourly_rate'] ? formatRupiah($trainer['hourly_rate']) : 'Belum diset' ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
            <a href="view_trainer.php?id=<?= $trainer['id'] ?>" class="btn btn-sm btn-info" title="Lihat Detail">
                <i class="fas fa-eye"></i>
                Detail
            </a>
            <a href="edit_trainer.php?id=<?= $trainer['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                <i class="fas fa-edit"></i>
                Edit
            </a>
            <a href="trainer_classes.php?id=<?= $trainer['id'] ?>" class="btn btn-sm btn-success" title="Kelas">
                <i class="fas fa-chalkboard-teacher"></i>
                Kelas
            </a>
            <button class="btn btn-sm btn-primary" onclick="showSchedule(<?= $trainer['id'] ?>)" title="Jadwal">
                <i class="fas fa-calendar"></i>
                Jadwal
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Trainer Schedule Modal -->
<div id="trainerScheduleModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 15px; padding: 0; max-width: 600px; width: 90%; max-height: 80vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <!-- Modal Header -->
        <div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-weight: 600;">
                <i class="fas fa-calendar-alt"></i>
                Jadwal Pelatih
            </h4>
            <button onclick="closeModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 5px; border-radius: 50%; transition: background 0.2s ease;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div id="scheduleModalContent" style="padding: 25px; max-height: 60vh; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">
                <div style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #1E459F; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                <div style="color: #1E459F; font-weight: 600;">Memuat jadwal pelatih...</div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div style="background: #f8f9fa; padding: 15px 25px; border-top: 1px solid #dee2e6;">
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="closeModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Styles -->
<style>
/* Loading Animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Trainer Card Animations */
.trainer-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.trainer-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

/* Button Enhancements */
.btn {
    transition: all 0.3s ease;
    border-radius: 6px;
    font-weight: 500;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.875rem;
}

/* Modal Enhancements */
.modal {
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Schedule Item Styling */
.schedule-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 10px;
}

.schedule-item:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* Rating Stars */
.rating-stars .fas {
    transition: color 0.2s ease;
}

.rating-stars:hover .fas {
    color: #FABD32 !important;
}

/* Badge Styling */
.badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
}

.badge-success { background: linear-gradient(135deg, #28a745, #20c997); }
.badge-warning { background: linear-gradient(135deg, #ffc107, #d39e00); }
.badge-info { background: linear-gradient(135deg, #17a2b8, #138496); }
.badge-danger { background: linear-gradient(135deg, #dc3545, #c82333); }
.badge-secondary { background: linear-gradient(135deg, #6c757d, #5a6268); }

/* Responsive Design */
@media (max-width: 768px) {
    .trainer-card {
        margin-bottom: 20px;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 5px;
    }
    
    div[style*="display: flex"] .btn {
        width: auto;
        flex: 1;
    }
    
    #trainerScheduleModal > div {
        width: 95% !important;
        max-height: 90vh !important;
    }
}

/* Error State Styling */
.error-state {
    color: #dc3545;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    padding: 15px;
}

.success-state {
    color: #155724;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 15px;
}
</style>

<script>
// Enhanced trainer card hover effects
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.trainer-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        });
    });
});

// Enhanced show trainer schedule with proper error handling
function showSchedule(trainerId) {
    const modal = document.getElementById('trainerScheduleModal');
    const content = document.getElementById('scheduleModalContent');
    
    // Show modal
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Show loading state
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div style="width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #1E459F; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <div style="color: #1E459F; font-weight: 600; font-size: 1.1rem;">Memuat jadwal pelatih...</div>
            <div style="color: #6c757d; margin-top: 8px;">Mohon tunggu sebentar</div>
        </div>
    `;
    
    // Fetch schedule data
    fetch(`ajax_trainer_schedule.php?trainer_id=${trainerId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Gagal memuat data jadwal');
            }
            
            let scheduleHtml = `
                <div style="text-align: center; margin-bottom: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="color: #1E459F; margin: 0;">${data.trainer_name}</h4>
                    <small style="color: #6c757d; font-size: 0.9rem;">${data.trainer_code}</small>
                    <div style="margin-top: 10px;">
                        <span class="badge badge-info">
                            <i class="fas fa-calendar-check"></i>
                            ${data.total_schedules || 0} jadwal aktif
                        </span>
                    </div>
                </div>
            `;
            
            if (data.schedules && data.schedules.length > 0) {
                scheduleHtml += `
                    <div style="display: grid; gap: 12px; max-height: 350px; overflow-y: auto; padding-right: 5px;">
                        ${data.schedules.map(schedule => `
                            <div class="schedule-item" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid ${schedule.martial_art_type === 'kickboxing' ? '#1E459F' : '#CF2A2A'}; transition: all 0.2s ease;">
                                <div>
                                    <div style="font-weight: 600; color: #1E459F; margin-bottom: 5px; font-size: 1rem;">
                                        ${schedule.class_name}
                                    </div>
                                    <div style="font-size: 0.85rem; color: #6c757d;">
                                        <i class="fas fa-calendar" style="margin-right: 5px;"></i>
                                        <span style="text-transform: capitalize;">${schedule.day_of_week}</span>
                                        <i class="fas fa-clock" style="margin-left: 15px; margin-right: 5px;"></i>
                                        ${schedule.start_time} - ${schedule.end_time}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge badge-${schedule.martial_art_type === 'kickboxing' ? 'info' : 'danger'}" style="margin-bottom: 5px; font-size: 0.7rem;">
                                        ${schedule.martial_art_type.toUpperCase()}
                                    </span>
                                    <div style="font-size: 0.8rem; color: #28a745; font-weight: 600;">
                                        <i class="fas fa-users"></i> ${schedule.enrolled_count}/${schedule.max_participants}
                                        <div style="font-size: 0.7rem; color: #6c757d; margin-top: 2px;">
                                            ${Math.round((schedule.enrolled_count / schedule.max_participants) * 100)}% full
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                scheduleHtml += `
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <h6 style="margin-bottom: 10px;">Belum Ada Jadwal</h6>
                        <div style="margin-bottom: 20px;">Pelatih ini belum memiliki jadwal mengajar</div>
                        <a href="trainer_classes.php?id=${trainerId}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i>
                            Tambah Jadwal
                        </a>
                    </div>
                `;
            }
            
            // Add action buttons if schedules exist
            if (data.schedules && data.schedules.length > 0) {
                scheduleHtml += `
                    <div style="margin-top: 20px; text-align: center; border-top: 1px solid #dee2e6; padding-top: 15px;">
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <a href="trainer_classes.php?id=${trainerId}" class="btn btn-primary btn-sm">
                                <i class="fas fa-cog"></i>
                                Kelola Jadwal
                            </a>
                            <a href="trainer_schedule.php?trainer_id=${trainerId}" class="btn btn-success btn-sm">
                                <i class="fas fa-calendar-alt"></i>
                                Detail Lengkap
                            </a>
                            <button onclick="exportTrainerSchedule(${trainerId})" class="btn btn-info btn-sm">
                                <i class="fas fa-download"></i>
                                Export
                            </button>
                        </div>
                    </div>
                `;
            }
            
            content.innerHTML = scheduleHtml;
        })
        .catch(error => {
            console.error('Error fetching schedule:', error);
            content.innerHTML = `
                <div class="error-state" style="text-align: center; padding: 30px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 15px; color: #dc3545;"></i>
                    <h6 style="color: #dc3545; margin-bottom: 10px;">Gagal Memuat Jadwal</h6>
                    <div style="color: #6c757d; margin-bottom: 20px; font-size: 0.9rem;">
                        ${error.message}
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button class="btn btn-primary btn-sm" onclick="showSchedule(${trainerId})">
                            <i class="fas fa-refresh"></i>
                            Coba Lagi
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="closeModal()">
                            <i class="fas fa-times"></i>
                            Tutup
                        </button>
                    </div>
                </div>
            `;
        });
}

function closeModal() {
    const modal = document.getElementById('trainerScheduleModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Export trainer schedule
function exportTrainerSchedule(trainerId) {
    const exportOptions = [
        { format: 'excel', icon: 'fas fa-file-excel', label: 'Excel', color: '#28a745' },
        { format: 'pdf', icon: 'fas fa-file-pdf', label: 'PDF', color: '#dc3545' },
        { format: 'csv', icon: 'fas fa-file-csv', label: 'CSV', color: '#1E459F' }
    ];
    
    const optionsHtml = exportOptions.map(option => `
        <button onclick="doExport(${trainerId}, '${option.format}')" class="btn btn-sm" style="background: ${option.color}; color: white; margin: 5px; min-width: 100px;">
            <i class="${option.icon}"></i>
            ${option.label}
        </button>
    `).join('');
    
    document.getElementById('scheduleModalContent').innerHTML = `
        <div style="text-align: center; padding: 30px;">
            <h5 style="color: #1E459F; margin-bottom: 20px;">
                <i class="fas fa-download"></i>
                Pilih Format Export
            </h5>
            <div style="margin-bottom: 20px;">
                ${optionsHtml}
            </div>
            <button onclick="showSchedule(${trainerId})" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Jadwal
            </button>
        </div>
    `;
}

function doExport(trainerId, format) {
    const button = event.target;
    const originalContent = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    button.disabled = true;
    
    // Open export URL
    window.open(`export_trainer_schedule.php?trainer_id=${trainerId}&format=${format}`, '_blank');
    
    // Reset button after delay
    setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
        
        // Show success message
        const successMsg = document.createElement('div');
        successMsg.innerHTML = `
            <div style="color: #28a745; font-weight: 600; margin-top: 10px; font-size: 0.9rem;">
                <i class="fas fa-check-circle"></i>
                Export ${format.toUpperCase()} berhasil!
            </div>
        `;
        button.parentNode.appendChild(successMsg);
        
        setTimeout(() => {
            if (successMsg.parentNode) {
                successMsg.remove();
            }
        }, 3000);
    }, 2000);
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('trainerScheduleModal');
    if (e.target === modal) {
        closeModal();
    }
});

// Keyboard support
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('trainerScheduleModal');
    if (e.key === 'Escape' && modal.style.display === 'block') {
        closeModal();
    }
});

// Enhanced button interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add ripple effect to buttons
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.5);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                if (ripple.parentNode) {
                    ripple.remove();
                }
            }, 600);
        });
    });
    
    // Add loading state to AJAX buttons
    document.querySelectorAll('button[onclick*="showSchedule"]').forEach(button => {
        button.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            this.disabled = true;
            
            // Reset after modal opens
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-calendar"></i> Jadwal';
                this.disabled = false;
            }, 1000);
        });
    });
});

// Ripple animation CSS
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyle);

// Auto-refresh trainer cards every 5 minutes (optional)
// setInterval(() => {
//     if (document.visibilityState === 'visible') {
//         location.reload();
//     }
// }, 300000);

// Performance monitoring (optional)
if ('performance' in window) {
    window.addEventListener('load', () => {
        const loadTime = performance.now();
        console.log(`Page loaded in ${Math.round(loadTime)}ms`);
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>