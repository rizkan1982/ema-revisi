<?php
require_once '../../config/config.php';
requireRole(['admin']);

$trainer_id = intval($_GET['trainer_id']);

// Get trainer info
$trainer = $db->fetch("
    SELECT t.*, u.full_name
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
", [$trainer_id]);

if (!$trainer) {
    die('Pelatih tidak ditemukan');
}

// Get schedules
$schedules = $db->fetchAll("
    SELECT s.*, c.class_name, c.martial_art_type, c.max_participants,
           COUNT(mc.member_id) as enrolled_count
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    WHERE c.trainer_id = ? AND s.is_active = 1
    GROUP BY s.id
    ORDER BY 
        CASE s.day_of_week
            WHEN 'monday' THEN 1
            WHEN 'tuesday' THEN 2
            WHEN 'wednesday' THEN 3
            WHEN 'thursday' THEN 4
            WHEN 'friday' THEN 5
            WHEN 'saturday' THEN 6
            WHEN 'sunday' THEN 7
        END,
        s.start_time
", [$trainer_id]);

$days = [
    'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu', 'thursday' => 'Kamis',
    'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pelatih - <?= htmlspecialchars($trainer['full_name']) ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .schedule-sheet {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
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
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #dee2e6;
            margin: 20px 0;
        }
        .day-cell {
            background: white;
            padding: 15px;
            min-height: 120px;
        }
        .day-header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #1E459F;
            font-weight: bold;
            color: #1E459F;
        }
        .schedule-item {
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            border-left: 3px solid #1E459F;
            background: rgba(30, 69, 159, 0.05);
        }
        .no-print { display: block; }
        @media print {
            body { margin: 0; padding: 10px; background: white; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 30px; padding: 20px; background: white; border-radius: 10px;">
        <h4 style="color: #1E459F; margin-bottom: 20px;">Jadwal Pelatih - Print Preview</h4>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Jadwal
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>
    </div>

    <div class="schedule-sheet">
        <div class="header">
            <h1 style="margin: 0; font-size: 2rem;">🥋 EMA CAMP</h1>
            <h2 style="margin: 10px 0; font-size: 1.3rem; color: #FABD32;">JADWAL PELATIH</h2>
            <h3 style="margin: 0; font-size: 1.1rem;"><?= htmlspecialchars($trainer['full_name']) ?></h3>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">Kode: <?= $trainer['trainer_code'] ?></p>
        </div>
        
        <div class="content">
            <?php if (empty($schedules)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <h5>Belum Ada Jadwal</h5>
                    <p>Pelatih ini belum memiliki jadwal mengajar</p>
                </div>
            <?php else: ?>
                <!-- Weekly Grid -->
                <div class="schedule-grid">
                    <?php foreach ($days as $day_en => $day_id): ?>
                        <div class="day-cell">
                            <div class="day-header"><?= $day_id ?></div>
                            
                            <?php
                            $day_schedules = array_filter($schedules, function($s) use ($day_en) {
                                return $s['day_of_week'] === $day_en;
                            });
                            ?>
                            
                            <?php if (empty($day_schedules)): ?>
                                <div style="text-align: center; color: #6c757d; margin-top: 30px; opacity: 0.5;">
                                    <small>Libur</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($day_schedules as $schedule): ?>
                                    <div class="schedule-item">
                                        <div style="font-weight: bold; margin-bottom: 3px;">
                                            <?= htmlspecialchars($schedule['class_name']) ?>
                                        </div>
                                        <div style="margin-bottom: 3px;">
                                            <?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?>
                                        </div>
                                        <div style="font-size: 0.7rem; color: #28a745;">
                                            <?= $schedule['enrolled_count'] ?>/<?= $schedule['max_participants'] ?> peserta
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary Table -->
                <div style="margin-top: 30px;">
                    <h4 style="color: #1E459F; margin-bottom: 15px;">Ringkasan Jadwal</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="border: 1px solid #dee2e6; padding: 10px; text-align: left;">Hari</th>
                                <th style="border: 1px solid #dee2e6; padding: 10px; text-align: left;">Waktu</th>
                                <th style="border: 1px solid #dee2e6; padding: 10px; text-align: left;">Kelas</th>
                                <th style="border: 1px solid #dee2e6; padding: 10px; text-align: center;">Peserta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td style="border: 1px solid #dee2e6; padding: 10px;"><?= $days[$schedule['day_of_week']] ?></td>
                                <td style="border: 1px solid #dee2e6; padding: 10px;"><?= $schedule['start_time'] ?> - <?= $schedule['end_time'] ?></td>
                                <td style="border: 1px solid #dee2e6; padding: 10px;"><?= htmlspecialchars($schedule['class_name']) ?></td>
                                <td style="border: 1px solid #dee2e6; padding: 10px; text-align: center;"><?= $schedule['enrolled_count'] ?>/<?= $schedule['max_participants'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Footer Info -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f1f3f4; font-size: 0.9rem; color: #6c757d;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <strong>Total Jadwal:</strong> <?= count($schedules) ?><br>
                        <strong>Total Peserta:</strong> <?= array_sum(array_column($schedules, 'enrolled_count')) ?> orang<br>
                        <strong>Tanggal Print:</strong> <?= date('d F Y H:i:s') ?>
                    </div>
                    <div>
                        <strong>Pelatih:</strong> <?= htmlspecialchars($trainer['full_name']) ?><br>
                        <strong>Kode:</strong> <?= $trainer['trainer_code'] ?><br>
                        <strong>Dicetak oleh:</strong> <?= $_SESSION['user_name'] ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d;">
            <p style="margin: 0;"><strong>EMA Camp - Elite Martial Art</strong></p>
            <p style="margin: 5px 0 0 0;">Sistem Management Professional untuk Camp Bela Diri</p>
        </div>
    </div>

    <script>
        // Auto print when ready
        window.onload = function() {
            setTimeout(() => {
                // window.print(); // Uncomment untuk auto print
            }, 1000);
        }
    </script>
</body>
</html>