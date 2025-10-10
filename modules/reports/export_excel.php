<?php
require_once '../../config/config.php';
requireRole(['admin']);

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="EMA_Camp_Report_' . date('Y-m-d', strtotime($start_date)) . '_to_' . date('Y-m-d', strtotime($end_date)) . '.xls"');
header('Cache-Control: max-age=0');

// Get report data
$financial_data = $db->fetchAll("
    SELECT 
        p.payment_date,
        p.receipt_number,
        u.full_name as member_name,
        m.member_code,
        p.payment_type,
        p.payment_method,
        p.amount,
        p.status
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.payment_date BETWEEN ? AND ?
    ORDER BY p.payment_date DESC
", [$start_date, $end_date]);

$attendance_data = $db->fetchAll("
    SELECT 
        a.attendance_date,
        u.full_name as member_name,
        m.member_code,
        c.class_name,
        trainer.full_name as trainer_name,
        a.check_in_time,
        a.check_out_time,
        a.status
    FROM attendance a
    JOIN members m ON a.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN classes c ON a.class_id = c.id
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users trainer ON t.user_id = trainer.id
    WHERE a.attendance_date BETWEEN ? AND ?
    ORDER BY a.attendance_date DESC
", [$start_date, $end_date]);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>EMA Camp Report</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #1E459F; color: white; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .section { margin-top: 30px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN EMA CAMP</h1>
        <h2>Elite Martial Art Management System</h2>
        <p>Periode: <?= date('d F Y', strtotime($start_date)) ?> - <?= date('d F Y', strtotime($end_date)) ?></p>
    </div>

    <div class="section">
        <h3>LAPORAN KEUANGAN</h3>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Receipt</th>
                    <th>Nama Member</th>
                    <th>Kode Member</th>
                    <th>Tipe Pembayaran</th>
                    <th>Metode</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($financial_data as $payment): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                    <td><?= $payment['receipt_number'] ?></td>
                    <td><?= $payment['member_name'] ?></td>
                    <td><?= $payment['member_code'] ?></td>
                    <td><?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?></td>
                    <td><?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?></td>
                    <td><?= number_format($payment['amount'], 0, ',', '.') ?></td>
                    <td><?= ucfirst($payment['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>LAPORAN KEHADIRAN</h3>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Member</th>
                    <th>Kode Member</th>
                    <th>Kelas</th>
                    <th>Pelatih</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance_data as $attendance): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($attendance['attendance_date'])) ?></td>
                    <td><?= $attendance['member_name'] ?></td>
                    <td><?= $attendance['member_code'] ?></td>
                    <td><?= $attendance['class_name'] ?></td>
                    <td><?= $attendance['trainer_name'] ?></td>
                    <td><?= $attendance['check_in_time'] ?></td>
                    <td><?= $attendance['check_out_time'] ?></td>
                    <td><?= ucfirst($attendance['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <p><strong>Laporan digenerate pada:</strong> <?= date('d F Y H:i:s') ?></p>
        <p><strong>Digenerate oleh:</strong> <?= $_SESSION['user_name'] ?></p>
    </div>
</body>
</html>