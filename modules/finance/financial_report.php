<?php
$page_title = "Laporan Keuangan Detail";
require_once '../../includes/header.php';
requireRole(['admin']);

// Get date parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Income Summary
$income_summary = $db->fetchAll("
    SELECT 
        payment_type,
        SUM(amount) as total_amount,
        COUNT(*) as transaction_count,
        AVG(amount) as average_amount
    FROM payments 
    WHERE payment_date BETWEEN ? AND ? AND status = 'paid'
    GROUP BY payment_type
    ORDER BY total_amount DESC
", [$start_date, $end_date]);

$total_income = array_sum(array_column($income_summary, 'total_amount'));

// Monthly comparison
$prev_month_start = date('Y-m-01', strtotime($start_date . ' -1 month'));
$prev_month_end = date('Y-m-t', strtotime($start_date . ' -1 month'));

$prev_month_income = $db->fetch("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE payment_date BETWEEN ? AND ? AND status = 'paid'
", [$prev_month_start, $prev_month_end])['total'];

$growth_rate = $prev_month_income > 0 ? 
    round((($total_income - $prev_month_income) / $prev_month_income) * 100, 1) : 0;

// Payment methods
$payment_methods = $db->fetchAll("
    SELECT 
        payment_method,
        SUM(amount) as total_amount,
        COUNT(*) as transaction_count
    FROM payments 
    WHERE payment_date BETWEEN ? AND ? AND status = 'paid'
    GROUP BY payment_method
    ORDER BY total_amount DESC
", [$start_date, $end_date]);

// Top paying members
$top_members = $db->fetchAll("
    SELECT 
        u.full_name, m.member_code,
        SUM(p.amount) as total_paid,
        COUNT(p.id) as payment_count
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.payment_date BETWEEN ? AND ? AND p.status = 'paid'
    GROUP BY p.member_id
    ORDER BY total_paid DESC
    LIMIT 10
", [$start_date, $end_date]);

// Outstanding payments
$outstanding = $db->fetchAll("
    SELECT 
        u.full_name, m.member_code,
        p.amount, p.due_date, p.payment_type,
        DATEDIFF(CURRENT_DATE, p.due_date) as days_overdue
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.due_date ASC
");

$total_outstanding = array_sum(array_column($outstanding, 'amount'));

// Daily income trend
$daily_income = $db->fetchAll("
    SELECT 
        DATE(payment_date) as date,
        SUM(amount) as daily_total,
        COUNT(*) as daily_count
    FROM payments 
    WHERE payment_date BETWEEN ? AND ? AND status = 'paid'
    GROUP BY DATE(payment_date)
    ORDER BY date ASC
", [$start_date, $end_date]);
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Laporan Keuangan Detail</h3>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i>
                Print
            </button>
        </div>
    </div>
</div>

<!-- Period Info -->
<div style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
    <h2 style="margin: 0; margin-bottom: 10px;">Periode: <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?></h2>
    <div style="font-size: 3rem; font-weight: bold; margin: 20px 0;">
        <?= formatRupiah($total_income) ?>
    </div>
    <div style="display: flex; justify-content: center; gap: 30px; margin-top: 20px;">
        <div>
            <div style="font-size: 1.5rem; font-weight: bold;"><?= array_sum(array_column($income_summary, 'transaction_count')) ?></div>
            <div style="opacity: 0.9;">Total Transaksi</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: bold; color: <?= $growth_rate >= 0 ? '#28a745' : '#dc3545' ?>;">
                <?= $growth_rate >= 0 ? '+' : '' ?><?= $growth_rate ?>%
            </div>
            <div style="opacity: 0.9;">vs Bulan Lalu</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: bold;"><?= formatRupiah($total_outstanding) ?></div>
            <div style="opacity: 0.9;">Outstanding</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Daily Income Chart -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Trend Pendapatan Harian</h4>
        </div>
        <div style="padding: 20px;">
            <canvas id="dailyIncomeChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Payment Methods Pie Chart -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Metode Pembayaran</h4>
        </div>
        <div style="padding: 20px;">
            <canvas id="paymentMethodChart" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Income Summary -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h4 class="card-title">Ringkasan Pendapatan per Tipe</h4>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tipe Pembayaran</th>
                    <th>Total Amount</th>
                    <th>Transaksi</th>
                    <th>Rata-rata</th>
                    <th>Persentase</th>
                    <th>Visualisasi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($income_summary as $income): ?>
                <tr>
                    <td>
                        <strong><?= ucwords(str_replace('_', ' ', $income['payment_type'])) ?></strong>
                    </td>
                    <td>
                        <strong style="color: #1E459F;"><?= formatRupiah($income['total_amount']) ?></strong>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= $income['transaction_count'] ?> transaksi</span>
                    </td>
                    <td>
                        <?= formatRupiah($income['average_amount']) ?>
                    </td>
                    <td>
                        <?= round(($income['total_amount'] / $total_income) * 100, 1) ?>%
                    </td>
                    <td>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" style="width: <?= ($income['total_amount'] / $total_income) * 100 ?>%; background: #1E459F;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Two Column Layout -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Top Paying Members -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Top 10 Member Pembayaran</h4>
        </div>
        
        <?php if (empty($top_members)): ?>
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                <p>Belum ada data pembayaran</p>
            </div>
        <?php else: ?>
            <div style="padding: 0;">
                <?php foreach ($top_members as $index => $member): ?>
                <div style="padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 30px; height: 30px; border-radius: 50%; background: <?= $index < 3 ? '#FABD32' : '#e9ecef' ?>; color: <?= $index < 3 ? 'white' : '#6c757d' ?>; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?= $index + 1 ?>
                        </div>
                        <div>
                            <strong style="color: #1E459F;"><?= htmlspecialchars($member['full_name']) ?></strong>
                            <br>
                            <small class="text-muted"><?= $member['member_code'] ?></small>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <strong style="color: #28a745;"><?= formatRupiah($member['total_paid']) ?></strong>
                        <br>
                        <small class="text-muted"><?= $member['payment_count'] ?> pembayaran</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment Methods -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Breakdown Metode Pembayaran</h4>
        </div>
        
        <div style="padding: 20px;">
            <?php foreach ($payment_methods as $method): ?>
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <div>
                        <i class="fas <?= $method['payment_method'] === 'cash' ? 'fa-money-bill' : ($method['payment_method'] === 'transfer' ? 'fa-university' : 'fa-credit-card') ?>"></i>
                        <strong><?= ucwords(str_replace('_', ' ', $method['payment_method'])) ?></strong>
                    </div>
                    <div style="text-align: right;">
                        <strong><?= formatRupiah($method['total_amount']) ?></strong>
                        <br>
                        <small class="text-muted"><?= $method['transaction_count'] ?> transaksi</small>
                    </div>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar" style="width: <?= ($method['total_amount'] / $total_income) * 100 ?>%; background: #1E459F;"></div>
                </div>
                <small class="text-muted"><?= round(($method['total_amount'] / $total_income) * 100, 1) ?>% dari total</small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Outstanding Payments -->
<?php if (!empty($outstanding)): ?>
<div class="card">
    <div class="card-header" style="background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
        <h4 class="card-title" style="color: #dc3545;">
            <i class="fas fa-exclamation-triangle"></i>
            Outstanding Payments (<?= count($outstanding) ?>)
        </h4>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Tipe Pembayaran</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($outstanding, 0, 10) as $payment): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($payment['full_name']) ?></strong>
                        <br>
                        <small class="text-muted"><?= $payment['member_code'] ?></small>
                    </td>
                    <td><?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?></td>
                    <td><strong><?= formatRupiah($payment['amount']) ?></strong></td>
                    <td><?= formatDate($payment['due_date']) ?></td>
                    <td>
                        <?php if ($payment['days_overdue'] > 0): ?>
                            <span class="badge badge-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?= $payment['days_overdue'] ?> hari terlambat
                            </span>
                        <?php else: ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-clock"></i>
                                <?= abs($payment['days_overdue']) ?> hari lagi
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (count($outstanding) > 10): ?>
        <div style="padding: 15px; text-align: center; border-top: 1px solid #dee2e6;">
            <small class="text-muted">Menampilkan 10 dari <?= count($outstanding) ?> outstanding payments</small>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Summary Footer -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; margin-top: 30px;">
    <p style="color: #6c757d; margin: 0;">
        <strong>Laporan digenerate pada:</strong> <?= date('d F Y H:i:s') ?><br>
        <strong>Periode:</strong> <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?><br>
        <strong>Total Hari:</strong> <?= (new DateTime($end_date))->diff(new DateTime($start_date))->days + 1 ?> hari
    </p>
</div>

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Income Chart
const dailyCtx = document.getElementById('dailyIncomeChart').getContext('2d');
const dailyIncomeChart = new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($daily_income, 'date')) ?>,
        datasets: [{
            label: 'Pendapatan Harian',
            data: <?= json_encode(array_column($daily_income, 'daily_total')) ?>,
            borderColor: '#1E459F',
            backgroundColor: 'rgba(30, 69, 159, 0.1)',
            tension: 0.4,
            fill: true
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
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Payment Method Chart
const methodCtx = document.getElementById('paymentMethodChart').getContext('2d');
const paymentMethodChart = new Chart(methodCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(function($method) { return ucwords(str_replace('_', ' ', $method['payment_method'])); }, $payment_methods)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($payment_methods, 'total_amount')) ?>,
            backgroundColor: ['#1E459F', '#CF2A2A', '#FABD32', '#E1DCCA', '#28a745'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
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