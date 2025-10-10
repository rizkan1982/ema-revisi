<?php
require_once '../../config/config.php';
requireRole(['admin']);

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Get payroll data
$trainers = $db->fetchAll("
    SELECT t.*, u.full_name,
           SUM(TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time)) as total_minutes,
           COALESCE(t.hourly_rate, 0) as hourly_rate
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN classes c ON t.id = c.trainer_id AND c.is_active = 1
    LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
    WHERE u.is_active = 1
    GROUP BY t.id
    ORDER BY u.full_name ASC
");

// Calculate payroll
foreach ($trainers as &$trainer) {
    $total_hours = $trainer['total_minutes'] / 60;
    $monthly_hours = $total_hours * 4;
    $gross_salary = $monthly_hours * $trainer['hourly_rate'];
    $deductions = $gross_salary * 0.05;
    $net_salary = $gross_salary - $deductions;
    
    $trainer['total_hours'] = $total_hours;
    $trainer['monthly_hours'] = $monthly_hours;
    $trainer['gross_salary'] = $gross_salary;
    $trainer['deductions'] = $deductions;
    $trainer['net_salary'] = $net_salary;
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="EMA_Camp_Payroll_' . $months[$month] . '_' . $year . '.xls"');
header('Cache-Control: max-age=0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>EMA Camp Payroll</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #1E459F; color: white; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .total-row { background-color: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PAYROLL REPORT - <?= $months[$month] ?> <?= $year ?></h1>
        <h2>EMA Camp - Elite Martial Art</h2>
        <p>Generated on: <?= date('d F Y H:i:s') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Trainer Code</th>
                <th>Nama Lengkap</th>
                <th>Tarif per Jam</th>
                <th>Total Jam/Minggu</th>
                <th>Total Jam/Bulan</th>
                <th>Gaji Kotor</th>
                <th>Potongan (5%)</th>
                <th>Gaji Bersih</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; 
            $total_gross = 0; 
            $total_net = 0; 
            foreach ($trainers as $trainer): 
                $total_gross += $trainer['gross_salary'];
                $total_net += $trainer['net_salary'];
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $trainer['trainer_code'] ?></td>
                <td><?= htmlspecialchars($trainer['full_name']) ?></td>
                <td><?= formatRupiah($trainer['hourly_rate']) ?></td>
                <td><?= number_format($trainer['total_hours'], 1) ?></td>
                <td><?= number_format($trainer['monthly_hours'], 1) ?></td>
                <td><?= formatRupiah($trainer['gross_salary']) ?></td>
                <td><?= formatRupiah($trainer['deductions']) ?></td>
                <td><?= formatRupiah($trainer['net_salary']) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="total-row">
                <td colspan="6"><strong>TOTAL</strong></td>
                <td><strong><?= formatRupiah($total_gross) ?></strong></td>
                <td><strong><?= formatRupiah($total_gross - $total_net) ?></strong></td>
                <td><strong><?= formatRupiah($total_net) ?></strong></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <h3>RINGKASAN PAYROLL</h3>
        <table style="width: 50%;">
            <tr>
                <td><strong>Bulan/Tahun:</strong></td>
                <td><?= $months[$month] ?> <?= $year ?></td>
            </tr>
            <tr>
                <td><strong>Total Pelatih:</strong></td>
                <td><?= count($trainers) ?> orang</td>
            </tr>
            <tr>
                <td><strong>Total Gaji Kotor:</strong></td>
                <td><?= formatRupiah($total_gross) ?></td>
            </tr>
            <tr>
                <td><strong>Total Potongan:</strong></td>
                <td><?= formatRupiah($total_gross - $total_net) ?></td>
            </tr>
            <tr>
                <td><strong>Total Gaji Bersih:</strong></td>
                <td><strong><?= formatRupiah($total_net) ?></strong></td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px;">
        <p><strong>Generated by:</strong> <?= $_SESSION['user_name'] ?></p>
        <p><strong>EMA Camp Management System</strong></p>
    </div>
</body>
</html>