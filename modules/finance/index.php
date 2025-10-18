<?php
require_once '../../config/config.php';
requireLogin();
requireRole(['admin']);

$page_title = "Manajemen Keuangan";
require_once '../../includes/header.php';

// Get financial statistics
$current_month = date('Y-m');
$current_year = date('Y');

$stats = [
    'monthly_income' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ? AND status = 'paid'", [$current_month])['total'],
    'monthly_pending' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE_FORMAT(due_date, '%Y-%m') = ? AND status = 'pending'", [$current_month])['total'],
    'monthly_overdue' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE due_date < CURRENT_DATE AND status = 'pending'")['total'],
    'total_members_paid' => $db->fetch("SELECT COUNT(DISTINCT member_id) as count FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ? AND status = 'paid'", [$current_month])['count']
];

// Recent payments
$recent_payments = $db->fetchAll("
    SELECT p.*, u.full_name, m.member_code 
    FROM payments p 
    JOIN members m ON p.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 10
");

// Monthly income chart data
$monthly_data = $db->fetchAll("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(amount) as total_income,
        COUNT(*) as payment_count
    FROM payments 
    WHERE payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH) 
    AND status = 'paid'
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month ASC
");

// Payment type breakdown
$payment_types = $db->fetchAll("
    SELECT 
        payment_type,
        SUM(amount) as total_amount,
        COUNT(*) as count
    FROM payments 
    WHERE DATE_FORMAT(payment_date, '%Y-%m') = ? AND status = 'paid'
    GROUP BY payment_type
", [$current_month]);
?>

<!-- Enhanced Financial Dashboard -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($stats['monthly_income']) ?></h3>
                <p>Pendapatan Bulan Ini</p>
                <small class="text-success">
                    <i class="fas fa-arrow-up"></i>
                    <?= $stats['total_members_paid'] ?> pembayaran
                </small>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($stats['monthly_pending']) ?></h3>
                <p>Pending Bulan Ini</p>
                <small class="text-warning">
                    <i class="fas fa-clock"></i>
                    Menunggu pembayaran
                </small>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-hourglass-half"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($stats['monthly_overdue']) ?></h3>
                <p>Overdue</p>
                <small class="text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Perlu tindakan
                </small>
            </div>
            <div class="stat-icon red">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($stats['monthly_income'] + $stats['monthly_pending']) ?></h3>
                <p>Total Proyeksi</p>
                <small class="text-info">
                    <i class="fas fa-chart-line"></i>
                    Termasuk pending
                </small>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-calculator"></i>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <a href="add_payment.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i>
                Tambah Pembayaran
            </a>
            <a href="bulk_invoice.php" class="btn btn-warning">
                <i class="fas fa-file-invoice-dollar"></i>
                Buat Invoice Massal
            </a>
            <a href="payment_reminder.php" class="btn btn-danger">
                <i class="fas fa-bell"></i>
                Kirim Reminder
            </a>
            <a href="financial_report.php" class="btn btn-success">
                <i class="fas fa-chart-bar"></i>
                Laporan Keuangan
            </a>
            <a href="export_payments.php" class="btn btn-info">
                <i class="fas fa-file-export"></i>
                Export Data
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
    <!-- Income Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Trend Pendapatan (12 Bulan)</h3>
            <div class="dropdown">
                <select id="chartPeriod" class="form-control form-select" style="width: auto;">
                    <option value="12">12 Bulan</option>
                    <option value="6">6 Bulan</option>
                    <option value="3">3 Bulan</option>
                </select>
            </div>
        </div>
        <div style="padding: 20px;">
            <canvas id="incomeChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Payment Types Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tipe Pembayaran</h3>
        </div>
        <div style="padding: 20px;">
            <canvas id="paymentTypeChart" height="300"></canvas>
        </div>
        
        <div style="padding: 0 20px 20px;">
            <?php foreach ($payment_types as $type): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-top: 1px solid #eee;">
                <div>
                    <strong><?= ucwords(str_replace('_', ' ', $type['payment_type'])) ?></strong>
                    <br>
                    <small class="text-muted"><?= $type['count'] ?> transaksi</small>
                </div>
                <div style="text-align: right;">
                    <strong><?= formatRupiah($type['total_amount']) ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card" style="margin-top: 30px;">
    <div class="card-header">
        <h3 class="card-title">Transaksi Terbaru</h3>
        <a href="all_payments.php" class="btn btn-primary btn-sm">
            Lihat Semua
        </a>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Receipt</th>
                    <th>Member</th>
                    <th>Tipe</th>
                    <th>Metode</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_payments as $payment): ?>
                <tr>
                    <td><?= formatDate($payment['payment_date']) ?></td>
                    <td>
                        <strong><?= $payment['receipt_number'] ?: '-' ?></strong>
                    </td>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($payment['full_name']) ?></strong>
                            <br>
                            <small class="text-muted"><?= $payment['member_code'] ?></small>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $method_icons = [
                            'cash' => 'fas fa-money-bill',
                            'transfer' => 'fas fa-university',
                            'e_wallet' => 'fas fa-mobile-alt',
                            'credit_card' => 'fas fa-credit-card'
                        ];
                        ?>
                        <i class="<?= $method_icons[$payment['payment_method']] ?? 'fas fa-question' ?>"></i>
                        <?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?>
                    </td>
                    <td>
                        <strong><?= formatRupiah($payment['amount']) ?></strong>
                    </td>
                    <td>
                        <?php
                        $badge_class = '';
                        $icon = '';
                        switch ($payment['status']) {
                            case 'paid': 
                                $badge_class = 'badge-success'; 
                                $icon = 'fas fa-check-circle';
                                break;
                            case 'pending': 
                                $badge_class = 'badge-warning'; 
                                $icon = 'fas fa-clock';
                                break;
                            case 'overdue': 
                                $badge_class = 'badge-danger'; 
                                $icon = 'fas fa-exclamation-triangle';
                                break;
                            case 'cancelled': 
                                $badge_class = 'badge-secondary'; 
                                $icon = 'fas fa-times';
                                break;
                        }
                        ?>
                        <span class="badge <?= $badge_class ?>">
                            <i class="<?= $icon ?>"></i>
                            <?= ucfirst($payment['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="view_payment.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-info" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_payment.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="print_receipt.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-success" title="Print" target="_blank">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Charts JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Income Chart
const incomeCtx = document.getElementById('incomeChart').getContext('2d');
const incomeChart = new Chart(incomeCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
        datasets: [{
            label: 'Pendapatan',
            data: <?= json_encode(array_column($monthly_data, 'total_income')) ?>,
            borderColor: '#1E459F',
            backgroundColor: 'rgba(30, 69, 159, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#1E459F',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
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
                },
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                }
            },
            x: {
                grid: {
                    color: 'rgba(0,0,0,0.1)'
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
                        return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        elements: {
            line: {
                borderWidth: 3
            }
        }
    }
});

// Payment Type Chart
const typeCtx = document.getElementById('paymentTypeChart').getContext('2d');
const paymentTypeChart = new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(function($type) { return ucwords(str_replace('_', ' ', $type['payment_type'])); }, $payment_types)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($payment_types, 'total_amount')) ?>,
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
                    padding: 20,
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
</script>

<?php require_once '../../includes/footer.php'; ?>