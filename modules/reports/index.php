<?php
require_once '../../config/config.php';
requireLogin();
requireRole(['admin']);

$page_title = "Laporan & Analitik";
require_once '../../includes/header.php';

// Get date range from URL parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Financial Reports
$financial_data = [
    'total_income' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ? AND status = 'paid'", [$start_date, $end_date])['total'],
    'pending_payments' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE due_date BETWEEN ? AND ? AND status = 'pending'", [$start_date, $end_date])['total'],
    'overdue_payments' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE due_date < CURRENT_DATE AND status = 'pending'")['total'],
    'payment_count' => $db->fetch("SELECT COUNT(*) as count FROM payments WHERE payment_date BETWEEN ? AND ? AND status = 'paid'", [$start_date, $end_date])['count']
];

// Member Reports
$member_data = [
    'total_members' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE u.is_active = 1")['count'],
    'new_members' => $db->fetch("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ?", [$start_date, $end_date])['count'],
    'kickboxing_members' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.martial_art_type = 'kickboxing' AND u.is_active = 1")['count'],
    'boxing_members' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE m.martial_art_type = 'boxing' AND u.is_active = 1")['count']
];

// Attendance Reports
$attendance_data = [
    'total_attendances' => $db->fetch("SELECT COUNT(*) as count FROM attendances WHERE created_at BETWEEN ? AND ? AND status = 'present'", [$start_date, $end_date])['count'],
    'avg_attendance_rate' => $db->fetch("
        SELECT 
            ROUND(
                (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0) / 
                NULLIF(COUNT(*), 0), 2
            ) as rate 
        FROM attendances 
        WHERE created_at BETWEEN ? AND ?
    ", [$start_date, $end_date])['rate'] ?? 0
];

// Class Performance
$class_performance = $db->fetchAll("
    SELECT c.class_name, c.martial_art_type,
           COUNT(DISTINCT mc.member_id) as enrolled_members,
           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendances,
           ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 2) as attendance_rate
    FROM classes c
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN attendances a ON mc.class_id = a.class_id AND mc.member_id = a.member_id 
                              AND a.created_at BETWEEN ? AND ?
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY enrolled_members DESC
", [$start_date, $end_date]);

// Revenue by payment type
$revenue_by_type = $db->fetchAll("
    SELECT payment_type, 
           SUM(amount) as total_amount,
           COUNT(*) as payment_count
    FROM payments 
    WHERE payment_date BETWEEN ? AND ? AND status = 'paid'
    GROUP BY payment_type
    ORDER BY total_amount DESC
", [$start_date, $end_date]);

// Daily revenue trend
$daily_revenue = $db->fetchAll("
    SELECT 
        DATE(payment_date) as date,
        SUM(amount) as daily_income,
        COUNT(*) as payment_count
    FROM payments 
    WHERE payment_date BETWEEN ? AND ? AND status = 'paid'
    GROUP BY DATE(payment_date)
    ORDER BY date ASC
", [$start_date, $end_date]);

// Trainer performance
$trainer_performance = $db->fetchAll("
    SELECT u.full_name as trainer_name, t.trainer_code,
           COUNT(DISTINCT c.id) as total_classes,
           COUNT(DISTINCT mc.member_id) as total_students,
           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendances,
           COALESCE(AVG(tr.rating), 0) as avg_rating
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN classes c ON t.id = c.trainer_id AND c.is_active = 1
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN attendances a ON c.id = a.class_id AND a.created_at BETWEEN ? AND ?
    LEFT JOIN trainer_ratings tr ON t.id = tr.trainer_id
    WHERE u.is_active = 1
    GROUP BY t.id
    ORDER BY total_students DESC
", [$start_date, $end_date]);
?>

<!-- Date Range Selector -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <form method="GET" action="" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Tanggal Selesai</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>
                Filter Laporan
            </button>
            
            <div style="display: flex; gap: 5px;">
                <a href="?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" class="btn btn-outline-primary btn-sm">Bulan Ini</a>
                <a href="?start_date=<?= date('Y-01-01') ?>&end_date=<?= date('Y-12-31') ?>" class="btn btn-outline-primary btn-sm">Tahun Ini</a>
                <a href="?start_date=<?= date('Y-m-01', strtotime('-1 month')) ?>&end_date=<?= date('Y-m-t', strtotime('-1 month')) ?>" class="btn btn-outline-secondary btn-sm">Bulan Lalu</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($financial_data['total_income']) ?></h3>
                <p>Total Pendapatan</p>
                <small class="text-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $financial_data['payment_count'] ?> pembayaran
                </small>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $member_data['new_members'] ?></h3>
                <p>Member Baru</p>
                <small class="text-info">
                    <i class="fas fa-users"></i>
                    Total: <?= $member_data['total_members'] ?> member
                </small>
            </div>
            <div class="stat-icon red">
                <i class="fas fa-user-plus"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $attendance_data['total_attendances'] ?></h3>
                <p>Total Kehadiran</p>
                <small class="text-success">
                    <i class="fas fa-percentage"></i>
                    <?= $attendance_data['avg_attendance_rate'] ?>% tingkat kehadiran
                </small>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($financial_data['overdue_payments']) ?></h3>
                <p>Tunggakan</p>
                <small class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Perlu tindak lanjut
                </small>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <button onclick="exportToExcel()" class="btn btn-success">
                <i class="fas fa-file-excel"></i>
                Export Excel
            </button>
            <button onclick="exportToPDF()" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i>
                Export PDF
            </button>
            <button onclick="printReport()" class="btn btn-info">
                <i class="fas fa-print"></i>
                Print Laporan
            </button>
            <a href="detailed_report.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i>
                Laporan Detail
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Revenue Trend Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Trend Pendapatan Harian</h3>
        </div>
        <div style="padding: 20px;">
            <canvas id="revenueChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Revenue by Type Pie Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pendapatan per Tipe</h3>
        </div>
        <div style="padding: 20px;">
            <canvas id="revenueTypeChart" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Class Performance -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Performa Kelas</h3>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Kelas</th>
                    <th>Tipe</th>
                    <th>Member Terdaftar</th>
                    <th>Total Kehadiran</th>
                    <th>Tingkat Kehadiran</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_performance as $class): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($class['class_name']) ?></strong>
                    </td>
                    <td>
                        <span class="badge <?= $class['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                            <?= ucfirst($class['martial_art_type']) ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= $class['enrolled_members'] ?></strong> member
                    </td>
                    <td>
                        <?= $class['total_attendances'] ?> kehadiran
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="progress" style="flex: 1; height: 8px; background: #e9ecef; border-radius: 4px;">
                                <div class="progress-bar" style="width: <?= $class['attendance_rate'] ?>%; background: <?= $class['attendance_rate'] >= 80 ? '#28a745' : ($class['attendance_rate'] >= 60 ? '#ffc107' : '#dc3545') ?>; height: 100%; border-radius: 4px;"></div>
                            </div>
                            <span style="font-weight: bold; color: <?= $class['attendance_rate'] >= 80 ? '#28a745' : ($class['attendance_rate'] >= 60 ? '#ffc107' : '#dc3545') ?>;">
                                <?= $class['attendance_rate'] ?>%
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php if ($class['attendance_rate'] >= 80): ?>
                            <span class="badge badge-success">Excellent</span>
                        <?php elseif ($class['attendance_rate'] >= 60): ?>
                            <span class="badge badge-warning">Good</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Needs Attention</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Trainer Performance -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Performa Pelatih</h3>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Pelatih</th>
                    <th>Total Kelas</th>
                    <th>Total Murid</th>
                    <th>Kehadiran</th>
                    <th>Rating</th>
                    <th>Performa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trainer_performance as $trainer): ?>
                <tr>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($trainer['trainer_name']) ?></strong>
                            <br>
                            <small class="text-muted"><?= $trainer['trainer_code'] ?></small>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <?= $trainer['total_classes'] ?> Kelas
                        </span>
                    </td>
                    <td>
                        <strong><?= $trainer['total_students'] ?></strong> murid
                    </td>
                    <td>
                        <?= $trainer['total_attendances'] ?> kehadiran
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
                            <span style="font-size: 0.85rem; color: #6c757d;">
                                (<?= number_format($trainer['avg_rating'], 1) ?>)
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php
                        $performance_score = ($trainer['total_students'] * 0.4) + ($trainer['avg_rating'] * 0.6 * 20);
                        ?>
                        <?php if ($performance_score >= 80): ?>
                            <span class="badge badge-success">Outstanding</span>
                        <?php elseif ($performance_score >= 60): ?>
                            <span class="badge badge-warning">Good</span>
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

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Trend Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($daily_revenue, 'date')) ?>,
        datasets: [{
            label: 'Pendapatan Harian',
            data: <?= json_encode(array_column($daily_revenue, 'daily_income')) ?>,
            borderColor: '#1E459F',
            backgroundColor: 'rgba(30, 69, 159, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#1E459F',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Revenue by Type Chart
const typeCtx = document.getElementById('revenueTypeChart').getContext('2d');
const revenueTypeChart = new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(function($item) { return ucwords(str_replace('_', ' ', $item['payment_type'])); }, $revenue_by_type)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($revenue_by_type, 'total_amount')) ?>,
            backgroundColor: [
                '#1E459F',
                '#CF2A2A', 
                '#FABD32',
                '#E1DCCA',
                '#28a745',
                '#17a2b8'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': Rp ' + context.parsed.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Export Functions
function exportToExcel() {
    window.open(`export_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>`);
}

function exportToPDF() {
    window.open(`export_pdf.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>`);
}

function printReport() {
    window.print();
}

// Print styles
const printStyle = `
    <style media="print">
        .btn, .card-header, .sidebar, .header { display: none !important; }
        .card { border: 1px solid #000 !important; margin-bottom: 20px !important; }
        .table { border-collapse: collapse !important; }
        .table th, .table td { border: 1px solid #000 !important; padding: 8px !important; }
        body { font-size: 12px !important; }
    </style>
`;
document.head.insertAdjacentHTML('beforeend', printStyle);
</script>

<?php require_once '../../includes/footer.php'; ?>