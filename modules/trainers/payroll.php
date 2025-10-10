<?php
$page_title = "Payroll & Gaji Pelatih";
require_once '../../includes/header.php';
requireRole(['admin']);

$success = '';
$error = '';

// Get current month/year
$current_month = $_GET['month'] ?? date('m');
$current_year = $_GET['year'] ?? date('Y');

// FIX: Proper months array with string keys
$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Also create numeric array for compatibility
$months_numeric = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Get trainers with payroll data
$trainers_payroll = $db->fetchAll("
    SELECT t.*, u.full_name,
           COUNT(DISTINCT s.id) as total_schedules,
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

// Calculate payroll for each trainer
foreach ($trainers_payroll as &$trainer) {
    $total_hours = ($trainer['total_minutes'] ?? 0) / 60;
    $weekly_hours = $total_hours;
    $monthly_hours = $weekly_hours * 4;
    $gross_salary = $monthly_hours * $trainer['hourly_rate'];
    
    $trainer['total_hours'] = $total_hours;
    $trainer['monthly_hours'] = $monthly_hours;
    $trainer['gross_salary'] = $gross_salary;
    $trainer['deductions'] = $gross_salary * 0.05;
    $trainer['net_salary'] = $gross_salary - $trainer['deductions'];
}

// Handle payroll generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {
    try {
        $selected_trainers = $_POST['trainers'] ?? [];
        
        if (empty($selected_trainers)) {
            throw new Exception('Pilih minimal satu pelatih!');
        }
        
        $success = "Payroll berhasil digenerate untuk " . count($selected_trainers) . " pelatih!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$total_gross = array_sum(array_column($trainers_payroll, 'gross_salary'));
$total_net = array_sum(array_column($trainers_payroll, 'net_salary'));
?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Payroll & Gaji Pelatih</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>
</div>

<!-- Period Selection -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <form method="GET" action="">
            <div style="display: flex; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Bulan</label>
                    <select name="month" class="form-control form-select">
                        <?php foreach ($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $current_month == $num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Tahun</label>
                    <select name="year" class="form-control form-select">
                        <?php for ($year = date('Y') - 2; $year <= date('Y') + 1; $year++): ?>
                        <option value="<?= $year ?>" <?= $current_year == $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-calendar"></i>
                    Update Periode
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= count($trainers_payroll) ?></h3>
                <p>Total Pelatih</p>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($total_gross) ?></h3>
                <p>Total Gaji Kotor</p>
            </div>
            <div class="stat-icon red">
                <i class="fas fa-money-check-alt"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($total_gross - $total_net) ?></h3>
                <p>Total Potongan</p>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-calculator"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= formatRupiah($total_net) ?></h3>
                <p>Total Gaji Bersih</p>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= $error ?>
    </div>
<?php endif; ?>

<!-- Payroll Table -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Detail Payroll - <?= $months[$current_month] ?? $months_numeric[intval($current_month)] ?> <?= $current_year ?></h4>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-success" onclick="exportPayroll()">
                <i class="fas fa-file-excel"></i>
                Export Excel
            </button>
            <button type="button" class="btn btn-primary" onclick="selectAllTrainers()">
                <i class="fas fa-check-double"></i>
                Pilih Semua
            </button>
        </div>
    </div>
    
    <form method="POST" action="">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="50px">
                            <input type="checkbox" id="check-all" onchange="toggleAll()">
                        </th>
                        <th>Pelatih</th>
                        <th>Tarif/Jam</th>
                        <th>Total Jam</th>
                        <th>Jam/Bulan</th>
                        <th>Gaji Kotor</th>
                        <th>Potongan (5%)</th>
                        <th>Gaji Bersih</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trainers_payroll as $trainer): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="trainers[]" value="<?= $trainer['id'] ?>" class="trainer-checkbox">
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="user-avatar" style="width: 45px; height: 45px; font-size: 1.1rem; background: linear-gradient(135deg, #1E459F, #CF2A2A);">
                                    <?= strtoupper(substr($trainer['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong style="color: #1E459F;"><?= htmlspecialchars($trainer['full_name']) ?></strong><br>
                                    <small class="text-muted"><?= $trainer['trainer_code'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong style="color: #CF2A2A;">
                                <?= $trainer['hourly_rate'] > 0 ? formatRupiah($trainer['hourly_rate']) : 'Belum diset' ?>
                            </strong>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <div style="font-size: 1.3rem; font-weight: bold; color: #1E459F;">
                                    <?= number_format($trainer['total_hours'], 1) ?>
                                </div>
                                <small class="text-muted">jam/minggu</small>
                            </div>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <div style="font-size: 1.3rem; font-weight: bold; color: #FABD32;">
                                    <?= number_format($trainer['monthly_hours'], 1) ?>
                                </div>
                                <small class="text-muted">jam/bulan</small>
                            </div>
                        </td>
                        <td>
                            <strong style="color: #28a745; font-size: 1.1rem;">
                                <?= formatRupiah($trainer['gross_salary']) ?>
                            </strong>
                        </td>
                        <td>
                            <span style="color: #dc3545;">
                                -<?= formatRupiah($trainer['deductions']) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #1E459F; font-size: 1.2rem;">
                                <?= formatRupiah($trainer['net_salary']) ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($trainer['hourly_rate'] > 0): ?>
                                <span class="badge badge-success">Ready</span>
                            <?php else: ?>
                                <span class="badge badge-warning">No Rate</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td colspan="5" style="text-align: right; padding-right: 20px;">
                            <strong>TOTAL:</strong>
                        </td>
                        <td>
                            <strong style="color: #28a745;"><?= formatRupiah($total_gross) ?></strong>
                        </td>
                        <td>
                            <strong style="color: #dc3545;">-<?= formatRupiah($total_gross - $total_net) ?></strong>
                        </td>
                        <td>
                            <strong style="color: #1E459F; font-size: 1.3rem;"><?= formatRupiah($total_net) ?></strong>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div style="padding: 25px; text-align: center; border-top: 1px solid #dee2e6;">
            <button type="submit" name="generate_payroll" class="btn btn-primary btn-lg" id="generate-button" disabled>
                <i class="fas fa-money-check-alt"></i>
                Generate Payroll
            </button>
            
            <button type="button" class="btn btn-success btn-lg" onclick="printPayroll()" style="margin-left: 15px;">
                <i class="fas fa-print"></i>
                Print Slip Gaji
            </button>
        </div>
    </form>
</div>

<!-- Payroll Notes -->
<div class="card" style="margin-top: 30px;">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-info-circle"></i>
            Catatan Payroll
        </h5>
    </div>
    <div style="padding: 20px;">
        <ul style="margin: 0; color: #6c757d;">
            <li><strong>Perhitungan:</strong> Gaji = (Total Jam per Minggu × 4) × Tarif per Jam</li>
            <li><strong>Potongan:</strong> 5% dari gaji kotor untuk pajak dan administrasi</li>
            <li><strong>Jam Mengajar:</strong> Dihitung berdasarkan jadwal kelas yang aktif</li>
            <li><strong>Periode:</strong> <?= $months[$current_month] ?? $months_numeric[intval($current_month)] ?> <?= $current_year ?></li>
            <li><strong>Status "No Rate":</strong> Pelatih belum memiliki tarif per jam, perlu diset di data pelatih</li>
        </ul>
    </div>
</div>

<script>
function toggleAll() {
    const mainCheck = document.getElementById('check-all');
    document.querySelectorAll('.trainer-checkbox').forEach(cb => cb.checked = mainCheck.checked);
    updateGenerateButton();
}

function selectAllTrainers() {
    document.querySelectorAll('.trainer-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('check-all').checked = true;
    updateGenerateButton();
}

function updateGenerateButton() {
    const selectedCount = document.querySelectorAll('.trainer-checkbox:checked').length;
    document.getElementById('generate-button').disabled = selectedCount === 0;
}

function exportPayroll() {
    window.open(`export_payroll.php?month=<?= $current_month ?>&year=<?= $current_year ?>`);
}

function printPayroll() {
    const selected = Array.from(document.querySelectorAll('.trainer-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        alert('Pilih minimal satu pelatih untuk print slip gaji!');
        return;
    }
    window.open(`print_payroll.php?trainers=${selected.join(',')}&month=<?= $current_month ?>&year=<?= $current_year ?>`);
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.trainer-checkbox').forEach(cb => {
        cb.addEventListener('change', updateGenerateButton);
    });
    updateGenerateButton();
});
</script>

<?php require_once '../../includes/footer.php'; ?>