<?php
require_once '../../config/config.php';
requireRole(['admin']);

$trainer_ids = explode(',', $_GET['trainers']);
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// FIX: Proper months array with string keys
$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Also numeric for compatibility
$months_numeric = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Function to get month name safely
function getMonthName($month, $months, $months_numeric) {
    if (isset($months[$month])) {
        return $months[$month];
    } elseif (isset($months_numeric[intval($month)])) {
        return $months_numeric[intval($month)];
    } else {
        return 'Unknown Month';
    }
}

// Get selected trainers
$trainers = [];
foreach ($trainer_ids as $trainer_id) {
    $trainer = $db->fetch("
        SELECT t.*, u.full_name, u.email,
               COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time)), 0) as total_minutes
        FROM trainers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN classes c ON t.id = c.trainer_id AND c.is_active = 1
        LEFT JOIN schedules s ON c.id = s.class_id AND s.is_active = 1
        WHERE t.id = ?
        GROUP BY t.id
    ", [$trainer_id]);
    
    if ($trainer) {
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
        
        $trainers[] = $trainer;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - <?= getMonthName($month, $months, $months_numeric) ?> <?= $year ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .payslip {
            background: white;
            max-width: 600px;
            margin: 0 auto 40px auto;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            page-break-after: always;
        }
        .header {
            background: linear-gradient(135deg, #1E459F, #CF2A2A);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .total-row {
            background: #f8f9fa;
            margin: 20px -30px;
            padding: 20px 30px;
            border-top: 3px solid #1E459F;
            border-bottom: 3px solid #1E459F;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
        }
        .no-print { display: block; }
        @media print {
            body { margin: 0; padding: 10px; background: white; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 30px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h4 style="color: #1E459F; margin-bottom: 20px;">Kontrol Print</h4>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <button onclick="window.print()" class="btn btn-primary" style="padding: 12px 25px; background: #1E459F; color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-print"></i> Print All Payslips
            </button>
            <button onclick="window.close()" class="btn btn-secondary" style="padding: 12px 25px; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-times"></i> Close Window
            </button>
            <button onclick="exportToPDF()" class="btn btn-danger" style="padding: 12px 25px; background: #dc3545; color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <?php foreach ($trainers as $trainer): ?>
    <div class="payslip">
        <div class="header">
            <h1 style="margin: 0; font-size: 2rem;">🥋 EMA CAMP</h1>
            <h2 style="margin: 10px 0; font-size: 1.2rem; color: #FABD32;">SLIP GAJI PELATIH</h2>
            <p style="margin: 0; opacity: 0.9;"><?= getMonthName($month, $months, $months_numeric) ?> <?= $year ?></p>
        </div>
        
        <div class="content">
            <!-- Trainer Info -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="color: #1E459F; margin: 0;"><?= htmlspecialchars($trainer['full_name']) ?></h3>
                        <div style="color: #6c757d; margin-top: 5px;">
                            <strong>Trainer ID:</strong> <?= $trainer['trainer_code'] ?><br>
                            <strong>Email:</strong> <?= htmlspecialchars($trainer['email']) ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="color: #6c757d; font-size: 0.9rem;">
                            <strong>Periode Gaji</strong><br>
                            <?= getMonthName($month, $months, $months_numeric) ?> <?= $year ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Salary Details -->
            <div class="row">
                <strong>Tarif per Jam:</strong>
                <span><?= formatRupiah($trainer['hourly_rate']) ?></span>
            </div>
            
            <div class="row">
                <strong>Total Jam per Minggu:</strong>
                <span><?= number_format($trainer['total_hours'], 1) ?> jam</span>
            </div>
            
            <div class="row">
                <strong>Total Jam per Bulan:</strong>
                <span><?= number_format($trainer['monthly_hours'], 1) ?> jam</span>
            </div>
            
            <div class="row">
                <strong>Gaji Kotor:</strong>
                <span style="color: #28a745; font-weight: bold;"><?= formatRupiah($trainer['gross_salary']) ?></span>
            </div>
            
            <div class="row">
                <strong>Potongan (5%):</strong>
                <span style="color: #dc3545;">-<?= formatRupiah($trainer['deductions']) ?></span>
            </div>
            
            <div class="total-row">
                <div class="row" style="border: none; font-size: 1.2rem; font-weight: bold;">
                    <span style="color: #1E459F;">TOTAL GAJI BERSIH:</span>
                    <span style="color: #1E459F;"><?= formatRupiah($trainer['net_salary']) ?></span>
                </div>
            </div>
            
            <!-- Additional Info -->
            <div style="margin-top: 25px; font-size: 0.9rem; color: #6c757d;">
                <div class="row" style="border: none;">
                    <span>Tanggal Cetak:</span>
                    <span><?= date('d F Y H:i:s') ?></span>
                </div>
                <div class="row" style="border: none;">
                    <span>Dicetak oleh:</span>
                    <span><?= $_SESSION['user_name'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin: 0;"><strong>EMA Camp - Elite Martial Art</strong></p>
            <p style="margin: 5px 0;">Sistem Management Professional untuk Camp Bela Diri</p>
            <p style="margin: 0;">Dokumen ini digenerate secara otomatis oleh sistem</p>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        function exportToPDF() {
            // Simple print to PDF (browser native)
            window.print();
        }
    </script>
</body>
</html>