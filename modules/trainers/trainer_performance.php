<?php
$page_title = "Performance Pelatih";
require_once '../../includes/header.php';
requireRole(['admin']);

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get trainer performance data
$trainers_performance = $db->fetchAll("
    SELECT t.*, u.full_name,
           COUNT(DISTINCT c.id) as total_classes,
           COUNT(DISTINCT mc.member_id) as total_students,
           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
           COUNT(a.id) as total_sessions,
           COALESCE(AVG(tr.rating), 0) as avg_rating,
           COUNT(tr.id) as rating_count,
           SUM(CASE WHEN c.class_type = 'private' THEN 1 ELSE 0 END) as private_classes,
           SUM(CASE WHEN c.class_type = 'regular' THEN 1 ELSE 0 END) as regular_classes
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN classes c ON t.id = c.trainer_id AND c.is_active = 1
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN attendance a ON c.id = a.class_id AND a.attendance_date BETWEEN ? AND ?
    LEFT JOIN trainer_ratings tr ON t.id = tr.trainer_id
    WHERE u.is_active = 1
    GROUP BY t.id
    ORDER BY total_students DESC, avg_rating DESC
", [$start_date, $end_date]);
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Performance Pelatih</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>
</div>

<!-- Period Selector -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <form method="GET" action="" style="display: flex; gap: 15px; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Tanggal Selesai</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i>
                Filter
            </button>
            
            <button type="button" class="btn btn-success" onclick="exportPerformance()">
                <i class="fas fa-file-excel"></i>
                Export
            </button>
        </form>
    </div>
</div>

<!-- Performance Overview -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <?php
    $total_trainers = count($trainers_performance);
    $avg_students_per_trainer = $total_trainers > 0 ? round(array_sum(array_column($trainers_performance, 'total_students')) / $total_trainers, 1) : 0;
    $total_sessions = array_sum(array_column($trainers_performance, 'total_sessions'));
    $overall_attendance_rate = $total_sessions > 0 ? round((array_sum(array_column($trainers_performance, 'total_present')) / $total_sessions) * 100, 1) : 0;
    ?>
    
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $total_trainers ?></h3>
                <p>Total Pelatih Aktif</p>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $avg_students_per_trainer ?></h3>
                <p>Rata-rata Murid per Pelatih</p>
            </div>
            <div class="stat-icon red">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $total_sessions ?></h3>
                <p>Total Sesi Periode Ini</p>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $overall_attendance_rate ?>%</h3>
                <p>Overall Attendance Rate</p>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>
</div>

<!-- Performance Table -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Detail Performance Pelatih</h4>
    </div>
    
    <div class="table-responsive">
        <table class="table" id="performance-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Pelatih</th>
                    <th>Experience</th>
                    <th>Total Murid</th>
                    <th>Kelas</th>
                    <th>Attendance Rate</th>
                    <th>Rating</th>
                    <th>Performance Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trainers_performance as $index => $trainer): ?>
                <?php
                // Calculate attendance rate
                $attendance_rate = $trainer['total_sessions'] > 0 ? 
                    round(($trainer['total_present'] / $trainer['total_sessions']) * 100, 1) : 0;
                
                // Calculate performance score (weighted average)
                $performance_score = (
                    ($trainer['total_students'] * 0.3) +
                    ($attendance_rate * 0.3) +
                    ($trainer['avg_rating'] * 20 * 0.4)
                );
                
                $performance_score = round($performance_score, 1);
                
                // Determine performance level
                if ($performance_score >= 80) {
                    $performance_level = 'Outstanding';
                    $performance_class = 'badge-success';
                } elseif ($performance_score >= 60) {
                    $performance_level = 'Good';
                    $performance_class = 'badge-warning';
                } elseif ($performance_score >= 40) {
                    $performance_level = 'Average';
                    $performance_class = 'badge-info';
                } else {
                    $performance_level = 'Needs Improvement';
                    $performance_class = 'badge-danger';
                }
                ?>
                <tr>
                    <td>
                        <div style="width: 30px; height: 30px; border-radius: 50%; background: <?= $index < 3 ? '#FABD32' : '#e9ecef' ?>; color: <?= $index < 3 ? 'white' : '#6c757d' ?>; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?= $index + 1 ?>
                        </div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem; background: linear-gradient(135deg, #1E459F, #CF2A2A);">
                                <?= strtoupper(substr($trainer['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong style="color: #1E459F;"><?= htmlspecialchars($trainer['full_name']) ?></strong><br>
                                <small class="text-muted"><?= $trainer['trainer_code'] ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong><?= $trainer['experience_years'] ?></strong> tahun<br>
                        <small class="text-muted"><?= htmlspecialchars($trainer['specialization'] ?: 'N/A') ?></small>
                    </td>
                    <td>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #1E459F;">
                                <?= $trainer['total_students'] ?>
                            </div>
                            <small class="text-muted">murid aktif</small>
                        </div>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <?php if ($trainer['regular_classes'] > 0): ?>
                                <span class="badge badge-success"><?= $trainer['regular_classes'] ?> Regular</span>
                            <?php endif; ?>
                            <?php if ($trainer['private_classes'] > 0): ?>
                                <span class="badge badge-warning"><?= $trainer['private_classes'] ?> Private</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= $trainer['total_classes'] ?> total kelas</small>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="progress" style="flex: 1; height: 8px;">
                                <div class="progress-bar" style="width: <?= $attendance_rate ?>%; background: <?= $attendance_rate >= 80 ? '#28a745' : ($attendance_rate >= 60 ? '#ffc107' : '#dc3545') ?>;"></div>
                            </div>
                            <span style="font-weight: bold; color: <?= $attendance_rate >= 80 ? '#28a745' : ($attendance_rate >= 60 ? '#ffc107' : '#dc3545') ?>;">
                                <?= $attendance_rate ?>%
                            </span>
                        </div>
                        <small class="text-muted"><?= $trainer['total_sessions'] ?> total sesi</small>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="color: #FABD32;">
                                <?php
                                $rating = round($trainer['avg_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <span style="font-weight: bold; color: #1E459F;">
                                <?= number_format($trainer['avg_rating'], 1) ?>
                            </span>
                        </div>
                        <small class="text-muted"><?= $trainer['rating_count'] ?> reviews</small>
                    </td>
                    <td>
                        <div style="text-align: center;">
                            <div style="font-size: 1.2rem; font-weight: bold; color: #CF2A2A;">
                                <?= $performance_score ?>
                            </div>
                            <div class="progress" style="height: 6px; margin-top: 5px;">
                                <div class="progress-bar" style="width: <?= $performance_score ?>%; background: <?= $performance_score >= 80 ? '#28a745' : ($performance_score >= 60 ? '#ffc107' : '#dc3545') ?>;"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $performance_class ?>">
                            <?= $performance_level ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function exportPerformance() {
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    window.open(`export_trainer_performance.php?start_date=${startDate}&end_date=${endDate}`);
}
</script>

<?php require_once '../../includes/footer.php'; ?>