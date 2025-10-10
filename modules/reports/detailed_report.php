<?php
$page_title = "Laporan Detail";
require_once '../../includes/header.php';
requireRole(['admin']);

// Get parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// FIXED: Separate the aggregate query
$financial_summary = $db->fetch("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount,
        COALESCE(SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END), 0) as overdue_amount,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
    FROM payments 
    WHERE payment_date BETWEEN ? AND ?
", [$start_date, $end_date]);

// FIXED: Simplified member analysis
$member_analysis = $db->fetchAll("
    SELECT 
        m.martial_art_type,
        m.class_type,
        COUNT(*) as member_count,
        SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active_count
    FROM members m
    JOIN users u ON m.user_id = u.id
    GROUP BY m.martial_art_type, m.class_type
");

// FIXED: Simplified class performance without complex grouping
$class_performance = $db->fetchAll("
    SELECT 
        c.id,
        c.class_name,
        c.martial_art_type,
        c.class_type,
        c.max_participants,
        u.full_name as trainer_name
    FROM classes c
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE c.is_active = 1
    ORDER BY c.class_name ASC
");

// Get enrollment and attendance data separately
foreach ($class_performance as &$class) {
    $enrolled = $db->fetch("
        SELECT COUNT(*) as count 
        FROM member_classes 
        WHERE class_id = ? AND status = 'active'
    ", [$class['id']])['count'];
    
    // FIXED: Corrected attendance query
    $attendance_data = $db->fetch("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
        FROM attendance 
        WHERE class_id = ? AND attendance_date BETWEEN ? AND ?
    ", [$class['id'], $start_date, $end_date]);
    
    $class['enrolled_members'] = $enrolled;
    $class['utilization_rate'] = $class['max_participants'] > 0 ? 
        round(($enrolled / $class['max_participants']) * 100, 1) : 0;
    $class['total_sessions'] = $attendance_data['total_sessions'];
    $class['attendance_rate'] = $attendance_data['total_sessions'] > 0 ? 
        round(($attendance_data['present_count'] / $attendance_data['total_sessions']) * 100, 1) : 0;
}

// FIXED: Simplified trainer statistics
$trainer_statistics = $db->fetchAll("
    SELECT 
        t.id,
        u.full_name,
        t.trainer_code,
        t.experience_years,
        t.hourly_rate
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    WHERE u.is_active = 1
    ORDER BY u.full_name ASC
");

// Get additional trainer data separately
foreach ($trainer_statistics as &$trainer) {
    $class_data = $db->fetch("
        SELECT COUNT(*) as total_classes
        FROM classes 
        WHERE trainer_id = ? AND is_active = 1
    ", [$trainer['id']]);
    
    $student_data = $db->fetch("
        SELECT COUNT(DISTINCT mc.member_id) as total_students
        FROM classes c
        LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
        WHERE c.trainer_id = ?
    ", [$trainer['id']]);
    
    $rating_data = $db->fetch("
        SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count
        FROM trainer_ratings 
        WHERE trainer_id = ?
    ", [$trainer['id']]);
    
    $trainer['total_classes'] = $class_data['total_classes'];
    $trainer['total_students'] = $student_data['total_students'];
    $trainer['avg_rating'] = $rating_data['avg_rating'] ?: 0;
    $trainer['rating_count'] = $rating_data['rating_count'];
}

$outstanding_analysis = $db->fetchAll("
    SELECT 
        p.*,
        u.full_name,
        m.member_code,
        DATEDIFF(CURRENT_DATE, p.due_date) as days_overdue
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.status IN ('pending', 'overdue')
    ORDER BY p.due_date ASC
");
?>

<!-- Header Card -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Laporan Detail & Komprehensif</h3>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i>
                Print Report
            </button>
        </div>
    </div>
</div>

<!-- Executive Summary -->
<div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px;">
    <div style="text-align: center; margin-bottom: 25px;">
        <h2 style="margin: 0;">EXECUTIVE SUMMARY</h2>
        <p style="margin: 10px 0; opacity: 0.9;">Periode: <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?></p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">
                <?= formatRupiah($financial_summary['total_income']) ?>
            </div>
            <div style="opacity: 0.9;">Total Revenue</div>
        </div>
        
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">
                <?= array_sum(array_column($member_analysis, 'active_count')) ?>
            </div>
            <div style="opacity: 0.9;">Active Members</div>
        </div>
        
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">
                <?= count($class_performance) ?>
            </div>
            <div style="opacity: 0.9;">Active Classes</div>
        </div>
        
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">
                <?= count($trainer_statistics) ?>
            </div>
            <div style="opacity: 0.9;">Active Trainers</div>
        </div>
    </div>
</div>

<!-- Financial Analysis Detail -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">📊 Analisis Keuangan Detail</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Jumlah Transaksi</th>
                    <th>Total Amount</th>
                    <th>Persentase</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Pembayaran Lunas</strong></td>
                    <td><?= $financial_summary['paid_count'] ?></td>
                    <td><?= formatRupiah($financial_summary['total_income']) ?></td>
                    <td>
                        <?php 
                        $total = $financial_summary['total_income'] + $financial_summary['pending_amount'] + $financial_summary['overdue_amount'];
                        echo $total > 0 ? round(($financial_summary['total_income'] / $total) * 100, 1) . '%' : '0%';
                        ?>
                    </td>
                    <td><span class="badge badge-success">Completed</span></td>
                </tr>
                <tr>
                    <td><strong>Pembayaran Tertunda</strong></td>
                    <td><?= $financial_summary['pending_count'] ?></td>
                    <td><?= formatRupiah($financial_summary['pending_amount']) ?></td>
                    <td>
                        <?php 
                        echo $total > 0 ? round(($financial_summary['pending_amount'] / $total) * 100, 1) . '%' : '0%';
                        ?>
                    </td>
                    <td><span class="badge badge-warning">Pending</span></td>
                </tr>
                <tr>
                    <td><strong>Pembayaran Terlambat</strong></td>
                    <td><?= $financial_summary['overdue_count'] ?></td>
                    <td><?= formatRupiah($financial_summary['overdue_amount']) ?></td>
                    <td>
                        <?php 
                        echo $total > 0 ? round(($financial_summary['overdue_amount'] / $total) * 100, 1) . '%' : '0%';
                        ?>
                    </td>
                    <td><span class="badge badge-danger">Overdue</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Member Analysis by Martial Art -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">🥋 Analisis Member per Jenis Martial Art</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Jenis Martial Art</th>
                    <th>Tipe Kelas</th>
                    <th>Total Member</th>
                    <th>Member Aktif</th>
                    <th>Member Tidak Aktif</th>
                    <th>Tingkat Aktivitas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($member_analysis as $analysis): ?>
                <tr>
                    <td>
                        <span class="badge <?= $analysis['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                            <?= ucfirst($analysis['martial_art_type']) ?>
                        </span>
                    </td>
                    <td><?= ucwords(str_replace('_', ' ', $analysis['class_type'])) ?></td>
                    <td><strong><?= $analysis['member_count'] ?></strong></td>
                    <td><span class="text-success"><?= $analysis['active_count'] ?></span></td>
                    <td><span class="text-danger"><?= $analysis['member_count'] - $analysis['active_count'] ?></span></td>
                    <td>
                        <?php 
                        $activity_rate = $analysis['member_count'] > 0 ? round(($analysis['active_count'] / $analysis['member_count']) * 100, 1) : 0;
                        ?>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar <?= $activity_rate >= 80 ? 'bg-success' : ($activity_rate >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                 style="width: <?= $activity_rate ?>%"><?= $activity_rate ?>%</div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Class Performance Analysis -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">📚 Analisis Performa Kelas</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Kelas</th>
                    <th>Jenis</th>
                    <th>Trainer</th>
                    <th>Kapasitas</th>
                    <th>Member Terdaftar</th>
                    <th>Utilization Rate</th>
                    <th>Total Sesi</th>
                    <th>Attendance Rate</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_performance as $class): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($class['class_name']) ?></strong></td>
                    <td>
                        <span class="badge <?= $class['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                            <?= ucfirst($class['martial_art_type']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($class['trainer_name']) ?></td>
                    <td><?= $class['max_participants'] ?></td>
                    <td><?= $class['enrolled_members'] ?></td>
                    <td>
                        <div class="progress" style="height: 15px;">
                            <div class="progress-bar <?= $class['utilization_rate'] >= 80 ? 'bg-success' : ($class['utilization_rate'] >= 60 ? 'bg-warning' : 'bg-info') ?>" 
                                 style="width: <?= $class['utilization_rate'] ?>%">
                                <?= $class['utilization_rate'] ?>%
                            </div>
                        </div>
                    </td>
                    <td><?= $class['total_sessions'] ?></td>
                    <td>
                        <div class="progress" style="height: 15px;">
                            <div class="progress-bar <?= $class['attendance_rate'] >= 80 ? 'bg-success' : ($class['attendance_rate'] >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                 style="width: <?= $class['attendance_rate'] ?>%">
                                <?= $class['attendance_rate'] ?>%
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php
                        $performance_score = ($class['utilization_rate'] + $class['attendance_rate']) / 2;
                        if ($performance_score >= 80): ?>
                            <span class="badge badge-success">Excellent</span>
                        <?php elseif ($performance_score >= 60): ?>
                            <span class="badge badge-warning">Good</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Needs Improvement</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Trainer Statistics -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">👨‍🏫 Statistik Pelatih</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Pelatih</th>
                    <th>Kode</th>
                    <th>Pengalaman</th>
                    <th>Tarif/Jam</th>
                    <th>Total Kelas</th>
                    <th>Total Murid</th>
                    <th>Rating</th>
                    <th>Reviews</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trainer_statistics as $trainer): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($trainer['full_name']) ?></strong></td>
                    <td><?= $trainer['trainer_code'] ?></td>
                    <td><?= $trainer['experience_years'] ?> tahun</td>
                    <td><?= formatRupiah($trainer['hourly_rate']) ?></td>
                    <td><?= $trainer['total_classes'] ?></td>
                    <td><strong><?= $trainer['total_students'] ?></strong></td>
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
                            <span><?= number_format($trainer['avg_rating'], 1) ?></span>
                        </div>
                    </td>
                    <td><?= $trainer['rating_count'] ?> reviews</td>
                    <td>
                        <?php
                        $performance = ($trainer['total_students'] * 0.4) + ($trainer['avg_rating'] * 0.6 * 20);
                        if ($performance >= 80): ?>
                            <span class="badge badge-success">Outstanding</span>
                        <?php elseif ($performance >= 60): ?>
                            <span class="badge badge-primary">Good</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Average</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Outstanding Payments Analysis -->
<?php if (!empty($outstanding_analysis)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">⚠️ Analisis Pembayaran Outstanding</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Kode Member</th>
                    <th>Tanggal Jatuh Tempo</th>
                    <th>Hari Terlambat</th>
                    <th>Amount</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    <th>Priority</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outstanding_analysis as $payment): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($payment['full_name']) ?></strong></td>
                    <td><?= $payment['member_code'] ?></td>
                    <td><?= formatDate($payment['due_date']) ?></td>
                    <td>
                        <?php if ($payment['days_overdue'] > 0): ?>
                            <span class="text-danger"><?= $payment['days_overdue'] ?> hari</span>
                        <?php else: ?>
                            <span class="text-warning">Belum jatuh tempo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatRupiah($payment['amount']) ?></td>
                    <td><?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?></td>
                    <td>
                        <span class="badge <?= $payment['status'] === 'overdue' ? 'badge-danger' : 'badge-warning' ?>">
                            <?= ucfirst($payment['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($payment['days_overdue'] > 30): ?>
                            <span class="badge badge-danger">High</span>
                        <?php elseif ($payment['days_overdue'] > 7): ?>
                            <span class="badge badge-warning">Medium</span>
                        <?php else: ?>
                            <span class="badge badge-info">Low</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style media="print">
    .btn, .card-header { display: none !important; }
    .card { border: 1px solid #000 !important; margin-bottom: 20px !important; }
    .table { border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #000 !important; padding: 8px !important; }
    body { font-size: 12px !important; }
</style>

<?php require_once '../../includes/footer.php'; ?>