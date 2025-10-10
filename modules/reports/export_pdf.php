<?php
require_once '../../config/config.php';
requireRole(['admin']);

// Get date parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get report data (same as financial_report.php but for PDF)
$financial_data = [
    'total_income' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ? AND status = 'paid'", [$start_date, $end_date])['total'],
    'pending_payments' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE due_date BETWEEN ? AND ? AND status = 'pending'", [$start_date, $end_date])['total'],
    'payment_count' => $db->fetch("SELECT COUNT(*) as count FROM payments WHERE payment_date BETWEEN ? AND ? AND status = 'paid'", [$start_date, $end_date])['count']
];

$member_data = [
    'total_members' => $db->fetch("SELECT COUNT(*) as count FROM members m JOIN users u ON m.user_id = u.id WHERE u.is_active = 1")['count'],
    'new_members' => $db->fetch("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ?", [$start_date, $end_date])['count']
];

$top_payments = $db->fetchAll("
    SELECT p.*, u.full_name, m.member_code 
    FROM payments p 
    JOIN members m ON p.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    WHERE p.payment_date BETWEEN ? AND ? AND p.status = 'paid'
    ORDER BY p.amount DESC 
    LIMIT 10
", [$start_date, $end_date]);

// Set PDF headers
header('Content-Type: application/pdf');
header('Content-Disposition: attachment;filename="EMA_Camp_Financial_Report_' . $start_date . '_to_' . $end_date . '.pdf"');

// Simple HTML to PDF conversion (in real app, use libraries like TCPDF or DomPDF)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>EMA Camp Financial Report</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #1E459F;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1E459F;
            margin: 0;
            font-size: 28px;
        }
        .header h2 {
            color: #CF2A2A;
            margin: 10px 0;
            font-size: 18px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #1E459F;
            text-align: center;
        }
        .summary-card h3 {
            color: #1E459F;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .summary-card p {
            color: #6c757d;
            margin: 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #1E459F;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .footer {
            border-top: 2px solid #1E459F;
            padding-top: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
        .period {
            background: linear-gradient(135deg, #1E459F, #CF2A2A);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🥋 EMA CAMP FINANCIAL REPORT</h1>
        <h2>Elite Martial Art Management System</h2>
        <div class="period">
            <strong>Periode: <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?></strong>
        </div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <h3><?= formatRupiah($financial_data['total_income']) ?></h3>
            <p>Total Pendapatan</p>
        </div>
        <div class="summary-card">
            <h3><?= $financial_data['payment_count'] ?></h3>
            <p>Total Transaksi</p>
        </div>
        <div class="summary-card">
            <h3><?= $member_data['new_members'] ?></h3>
            <p>Member Baru</p>
        </div>
    </div>

    <h3 style="color: #1E459F; border-bottom: 2px solid #1E459F; padding-bottom: 5px;">Top 10 Pembayaran Terbesar</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Member</th>
                <th>Kode</th>
                <th>Tipe</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($top_payments as $payment): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= formatDate($payment['payment_date']) ?></td>
                <td><?= htmlspecialchars($payment['full_name']) ?></td>
                <td><?= $payment['member_code'] ?></td>
                <td><?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?></td>
                <td><strong><?= formatRupiah($payment['amount']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
        <div>
            <h4 style="color: #1E459F;">Ringkasan Keuangan</h4>
            <table style="margin: 0;">
                <tr>
                    <td><strong>Total Pendapatan</strong></td>
                    <td><strong><?= formatRupiah($financial_data['total_income']) ?></strong></td>
                </tr>
                <tr>
                    <td>Pembayaran Pending</td>
                    <td><?= formatRupiah($financial_data['pending_payments']) ?></td>
                </tr>
                <tr>
                    <td>Total Transaksi</td>
                    <td><?= $financial_data['payment_count'] ?></td>
                </tr>
                <tr>
                    <td>Rata-rata per Transaksi</td>
                    <td><?= $financial_data['payment_count'] > 0 ? formatRupiah($financial_data['total_income'] / $financial_data['payment_count']) : 'Rp 0' ?></td>
                </tr>
            </table>
        </div>
        
        <div>
            <h4 style="color: #1E459F;">Data Member</h4>
            <table style="margin: 0;">
                <tr>
                    <td><strong>Total Member Aktif</strong></td>
                    <td><strong><?= $member_data['total_members'] ?></strong></td>
                </tr>
                <tr>
                    <td>Member Baru Periode Ini</td>
                    <td><?= $member_data['new_members'] ?></td>
                </tr>
                <tr>
                    <td>Growth Rate</td>
                    <td>
                        <?php
                        $growth = $member_data['total_members'] > 0 ? 
                            round(($member_data['new_members'] / $member_data['total_members']) * 100, 1) : 0;
                        echo $growth . '%';
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="footer">
        <p><strong>EMA Camp Management System - Financial Report</strong></p>
        <p>Generated on: <?= date('d F Y H:i:s') ?> | Generated by: <?= $_SESSION['user_name'] ?></p>
        <p>Report Period: <?= formatDate($start_date) ?> to <?= formatDate($end_date) ?></p>
        <p>&copy; <?= date('Y') ?> EMA Camp - Elite Martial Art. All rights reserved.</p>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>