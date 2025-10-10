<?php
$page_title = "Jadwal & Kegiatan";
require_once '../../includes/header.php';

// Get current week dates
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

// Get week parameter from URL
$week_offset = intval($_GET['week'] ?? 0);
$week_start = date('Y-m-d', strtotime("monday this week +$week_offset weeks"));
$week_end = date('Y-m-d', strtotime("sunday this week +$week_offset weeks"));

// Get all schedules for the week
$schedules = $db->fetchAll("
    SELECT s.*, c.class_name, c.martial_art_type, c.class_type, c.max_participants,
           t.trainer_code, u.full_name as trainer_name,
           COUNT(mc.member_id) as enrolled_count,
           GROUP_CONCAT(DISTINCT CONCAT(mu.full_name, ' (', m.member_code, ')') SEPARATOR '; ') as participants
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    LEFT JOIN members m ON mc.member_id = m.id
    LEFT JOIN users mu ON m.user_id = mu.id
    WHERE s.is_active = 1 AND c.is_active = 1
    GROUP BY s.id, c.id, t.id, u.id
    ORDER BY s.day_of_week, s.start_time
");

// Get events for the week
$events = $db->fetchAll("
    SELECT e.*, COUNT(er.member_id) as registered_count
    FROM events e
    LEFT JOIN event_registrations er ON e.id = er.event_id
    WHERE e.event_date BETWEEN ? AND ? AND e.is_active = 1
    GROUP BY e.id
    ORDER BY e.event_date, e.start_time
", [$week_start, $week_end]);

// Days of week
$days = [
    'monday' => 'Senin',
    'tuesday' => 'Selasa', 
    'wednesday' => 'Rabu',
    'thursday' => 'Kamis',
    'friday' => 'Jumat',
    'saturday' => 'Sabtu',
    'sunday' => 'Minggu'
];

// Organize schedules by day
$schedule_by_day = [];
foreach ($days as $day_en => $day_id) {
    $schedule_by_day[$day_en] = array_filter($schedules, function($s) use ($day_en) {
        return $s['day_of_week'] === $day_en;
    });
}

// Get statistics
$stats = [
    'total_classes' => count($schedules),
    'total_events' => count($events),
    'total_participants' => array_sum(array_column($schedules, 'enrolled_count')),
    'capacity_utilization' => count($schedules) > 0 ? round((array_sum(array_column($schedules, 'enrolled_count')) / array_sum(array_column($schedules, 'max_participants'))) * 100) : 0
];
?>

<!-- Week Navigation -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="color: #1E459F; margin: 0;">
                    <i class="fas fa-calendar-week"></i>
                    Minggu <?= date('d M', strtotime($week_start)) ?> - <?= date('d M Y', strtotime($week_end)) ?>
                </h3>
                <?php if ($week_offset === 0): ?>
                    <small class="badge badge-success">Minggu Ini</small>
                <?php elseif ($week_offset > 0): ?>
                    <small class="badge badge-info"><?= $week_offset ?> Minggu Kedepan</small>
                <?php else: ?>
                    <small class="badge badge-warning"><?= abs($week_offset) ?> Minggu Lalu</small>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <a href="?week=<?= $week_offset - 1 ?>" class="btn btn-outline-primary">
                    <i class="fas fa-chevron-left"></i>
                    Minggu Sebelumnya
                </a>
                
                <?php if ($week_offset !== 0): ?>
                <a href="?week=0" class="btn btn-primary">
                    <i class="fas fa-calendar-day"></i>
                    Minggu Ini
                </a>
                <?php endif; ?>
                
                <a href="?week=<?= $week_offset + 1 ?>" class="btn btn-outline-primary">
                    Minggu Selanjutnya
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card blue">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_classes'] ?></h3>
                <p>Kelas Terjadwal</p>
            </div>
            <div class="stat-icon blue">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_events'] ?></h3>
                <p>Event Minggu Ini</p>
            </div>
            <div class="stat-icon red">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['total_participants'] ?></h3>
                <p>Total Peserta</p>
            </div>
            <div class="stat-icon yellow">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card cream">
        <div class="stat-content">
            <div class="stat-info">
                <h3><?= $stats['capacity_utilization'] ?>%</h3>
                <p>Kapasitas Terpakai</p>
            </div>
            <div class="stat-icon cream">
                <i class="fas fa-chart-pie"></i>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<?php if (in_array(getUserRole(), ['admin', 'trainer'])): ?>
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <?php if (getUserRole() === 'admin'): ?>
            <a href="add_schedule.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Tambah Jadwal
            </a>
            <a href="add_event.php" class="btn btn-success">
                <i class="fas fa-calendar-plus"></i>
                Tambah Event
            </a>
            <a href="class_management.php" class="btn btn-warning">
                <i class="fas fa-cog"></i>
                Kelola Kelas
            </a>
            <?php endif; ?>
            <a href="calendar_view.php" class="btn btn-info">
                <i class="fas fa-calendar"></i>
                Tampilan Kalender
            </a>
            <a href="attendance_today.php" class="btn btn-danger">
                <i class="fas fa-check-square"></i>
                Absensi Hari Ini
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Weekly Schedule Grid -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Jadwal Mingguan</h3>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-sm btn-outline-primary" onclick="toggleView('grid')">
                <i class="fas fa-th"></i>
                Grid View
            </button>
            <button class="btn btn-sm btn-outline-primary" onclick="toggleView('list')">
                <i class="fas fa-list"></i>
                List View
            </button>
        </div>
    </div>
    
    <!-- Grid View -->
    <div id="gridView" class="schedule-grid">
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #dee2e6;">
            <?php foreach ($days as $day_en => $day_id): ?>
                <div style="background: white; padding: 20px; min-height: 400px;">
                    <div style="text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #1E459F;">
                        <h5 style="color: #1E459F; margin: 0;"><?= $day_id ?></h5>
                        <small class="text-muted">
                            <?= date('d/m', strtotime($week_start . ' +' . array_search($day_en, array_keys($days)) . ' days')) ?>
                        </small>
                    </div>
                    
                    <!-- Regular Schedules -->
                    <?php foreach ($schedule_by_day[$day_en] as $schedule): ?>
                        <div class="schedule-item" style="margin-bottom: 15px; padding: 12px; border-radius: 8px; border-left: 4px solid <?= $schedule['martial_art_type'] === 'kickboxing' ? '#1E459F' : '#CF2A2A' ?>; background: <?= $schedule['martial_art_type'] === 'kickboxing' ? 'rgba(30, 69, 159, 0.05)' : 'rgba(207, 42, 42, 0.05)' ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div>
                                    <strong style="color: #1E459F; font-size: 0.9rem;">
                                        <?= htmlspecialchars($schedule['class_name']) ?>
                                    </strong>
                                    <div style="font-size: 0.8rem; color: #6c757d;">
                                        <?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?>
                                    </div>
                                </div>
                                <span class="badge <?= $schedule['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>" style="font-size: 0.7rem;">
                                    <?= strtoupper($schedule['martial_art_type']) ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                <i class="fas fa-user-tie" style="color: #FABD32;"></i>
                                <?= htmlspecialchars($schedule['trainer_name']) ?>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="font-size: 0.8rem;">
                                    <i class="fas fa-users" style="color: #28a745;"></i>
                                    <span class="<?= $schedule['enrolled_count'] >= $schedule['max_participants'] ? 'text-danger' : 'text-success' ?>">
                                        <?= $schedule['enrolled_count'] ?>/<?= $schedule['max_participants'] ?>
                                    </span>
                                </div>
                                
                                <div style="display: flex; gap: 3px;">
                                    <a href="view_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm btn-outline-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (getUserRole() === 'admin'): ?>
                                    <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Participants tooltip on hover -->
                            <?php if ($schedule['participants']): ?>
                            <div class="participants-tooltip" style="display: none;">
                                <strong>Peserta:</strong><br>
                                <?= htmlspecialchars($schedule['participants']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Events for this day -->
                    <?php
                    $day_date = date('Y-m-d', strtotime($week_start . ' +' . array_search($day_en, array_keys($days)) . ' days'));
                    $day_events = array_filter($events, function($e) use ($day_date) {
                        return $e['event_date'] === $day_date;
                    });
                    ?>
                    
                    <?php foreach ($day_events as $event): ?>
                        <div class="event-item" style="margin-bottom: 10px; padding: 10px; border-radius: 8px; background: linear-gradient(45deg, #FABD32, #E1DCCA); border: 1px solid #FABD32;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong style="color: #1E459F; font-size: 0.85rem;">
                                        <i class="fas fa-star"></i>
                                        <?= htmlspecialchars($event['event_name']) ?>
                                    </strong>
                                    <div style="font-size: 0.75rem; color: #6c757d;">
                                        <?= $event['start_time'] ? date('H:i', strtotime($event['start_time'])) : 'All Day' ?>
                                        <?= $event['end_time'] ? ' - ' . date('H:i', strtotime($event['end_time'])) : '' ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #6c757d;">
                                        <i class="fas fa-users"></i>
                                        <?= $event['registered_count'] ?> pendaftar
                                    </div>
                                </div>
                                
                                <span class="badge badge-warning" style="font-size: 0.7rem;">
                                    <?= strtoupper($event['event_type']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Empty state -->
                    <?php if (empty($schedule_by_day[$day_en]) && empty($day_events)): ?>
                        <div style="text-align: center; color: #6c757d; margin-top: 50px;">
                            <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                            <div style="font-size: 0.9rem;">Tidak ada jadwal</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- List View -->
    <div id="listView" style="display: none;">
        <div style="padding: 20px;">
            <?php foreach ($days as $day_en => $day_id): ?>
                <?php if (!empty($schedule_by_day[$day_en]) || !empty(array_filter($events, function($e) use ($week_start, $day_en, $days) {
                    $day_date = date('Y-m-d', strtotime($week_start . ' +' . array_search($day_en, array_keys($days)) . ' days'));
                    return $e['event_date'] === $day_date;
                }))): ?>
                    <div class="day-section" style="margin-bottom: 30px;">
                        <h4 style="color: #1E459F; border-bottom: 2px solid #1E459F; padding-bottom: 10px; margin-bottom: 20px;">
                            <?= $day_id ?> - <?= date('d M Y', strtotime($week_start . ' +' . array_search($day_en, array_keys($days)) . ' days')) ?>
                        </h4>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                            <!-- Schedules -->
                            <?php foreach ($schedule_by_day[$day_en] as $schedule): ?>
                                <div class="schedule-card" style="border: 1px solid #dee2e6; border-radius: 10px; padding: 20px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                        <div>
                                            <h5 style="color: #1E459F; margin: 0;">
                                                <?= htmlspecialchars($schedule['class_name']) ?>
                                            </h5>
                                            <div style="color: #6c757d; font-size: 0.9rem; margin-top: 5px;">
                                                <i class="fas fa-clock"></i>
                                                <?= date('H:i', strtotime($schedule['start_time'])) ?> - <?= date('H:i', strtotime($schedule['end_time'])) ?>
                                            </div>
                                        </div>
                                        
                                        <span class="badge <?= $schedule['martial_art_type'] === 'kickboxing' ? 'badge-info' : 'badge-danger' ?>">
                                            <?= strtoupper($schedule['martial_art_type']) ?>
                                        </span>
                                    </div>
                                    
                                    <div style="margin-bottom: 15px;">
                                        <div style="margin-bottom: 8px;">
                                            <i class="fas fa-user-tie" style="color: #FABD32; margin-right: 8px;"></i>
                                            <strong>Pelatih:</strong> <?= htmlspecialchars($schedule['trainer_name']) ?>
                                        </div>
                                        
                                        <div style="margin-bottom: 8px;">
                                            <i class="fas fa-users" style="color: #28a745; margin-right: 8px;"></i>
                                            <strong>Peserta:</strong> 
                                            <span class="<?= $schedule['enrolled_count'] >= $schedule['max_participants'] ? 'text-danger' : 'text-success' ?>">
                                                <?= $schedule['enrolled_count'] ?>/<?= $schedule['max_participants'] ?>
                                            </span>
                                        </div>
                                        
                                        <div>
                                            <i class="fas fa-layer-group" style="color: #17a2b8; margin-right: 8px;"></i>
                                            <strong>Tipe:</strong> 
                                            <span class="badge <?= $schedule['class_type'] === 'regular' ? 'badge-success' : 'badge-warning' ?>">
                                                <?= ucfirst($schedule['class_type']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <a href="view_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                            Detail
                                        </a>
                                        
                                        <?php if (getUserRole() === 'member'): ?>
                                            <?php
                                            $is_enrolled = $db->fetch("
                                                SELECT mc.id FROM member_classes mc 
                                                JOIN members m ON mc.member_id = m.id 
                                                WHERE m.user_id = ? AND mc.class_id = ? AND mc.status = 'active'
                                            ", [$_SESSION['user_id'], $schedule['class_id']]);
                                            ?>
                                            
                                            <?php if (!$is_enrolled && $schedule['enrolled_count'] < $schedule['max_participants']): ?>
                                                <a href="enroll_class.php?id=<?= $schedule['class_id'] ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-user-plus"></i>
                                                    Daftar
                                                </a>
                                            <?php elseif ($is_enrolled): ?>
                                                <span class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-check"></i>
                                                    Terdaftar
                                                </span>
                                            <?php else: ?>
                                                <span class="btn btn-sm btn-warning" disabled>
                                                    <i class="fas fa-users"></i>
                                                    Penuh
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array(getUserRole(), ['admin', 'trainer'])): ?>
                                            <a href="attendance.php?schedule_id=<?= $schedule['id'] ?>&date=<?= date('Y-m-d', strtotime($week_start . ' +' . array_search($day_en, array_keys($days)) . ' days')) ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-check"></i>
                                                Absensi
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (getUserRole() === 'admin'): ?>
                                            <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                                Edit
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Events -->
                            <?php
                            $day_date = date('Y-m-d', strtotime($week_start . ' +' . array_search($day_en, array_keys($days)) . ' days'));
                            $day_events = array_filter($events, function($e) use ($day_date) {
                                return $e['event_date'] === $day_date;
                            });
                            ?>
                            
                            <?php foreach ($day_events as $event): ?>
                                <div class="event-card" style="border: 2px solid #FABD32; border-radius: 10px; padding: 20px; background: linear-gradient(135deg, #FABD32, #E1DCCA); box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                        <div>
                                            <h5 style="color: #1E459F; margin: 0;">
                                                <i class="fas fa-star"></i>
                                                <?= htmlspecialchars($event['event_name']) ?>
                                            </h5>
                                            <div style="color: #6c757d; font-size: 0.9rem; margin-top: 5px;">
                                                <i class="fas fa-clock"></i>
                                                <?= $event['start_time'] ? date('H:i', strtotime($event['start_time'])) : 'All Day' ?>
                                                <?= $event['end_time'] ? ' - ' . date('H:i', strtotime($event['end_time'])) : '' ?>
                                            </div>
                                        </div>
                                        
                                        <span class="badge badge-warning">
                                            <?= strtoupper($event['event_type']) ?>
                                        </span>
                                    </div>
                                    
                                    <div style="margin-bottom: 15px; color: #1E459F;">
                                        <?php if ($event['location']): ?>
                                            <div style="margin-bottom: 8px;">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($event['location']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div style="margin-bottom: 8px;">
                                            <i class="fas fa-users"></i>
                                            <?= $event['registered_count'] ?> peserta terdaftar
                                        </div>
                                        
                                        <?php if ($event['registration_fee'] > 0): ?>
                                            <div>
                                                <i class="fas fa-money-bill-wave"></i>
                                                Biaya: <?= formatRupiah($event['registration_fee']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                            Detail Event
                                        </a>
                                        
                                        <?php if (getUserRole() === 'member'): ?>
                                            <?php
                                            $is_registered = $db->fetch("
                                                SELECT er.id FROM event_registrations er 
                                                JOIN members m ON er.member_id = m.id 
                                                WHERE m.user_id = ? AND er.event_id = ?
                                            ", [$_SESSION['user_id'], $event['id']]);
                                            ?>
                                            
                                            <?php if (!$is_registered && (!$event['registration_deadline'] || $event['registration_deadline'] >= date('Y-m-d'))): ?>
                                                <a href="register_event.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-calendar-plus"></i>
                                                    Daftar Event
                                                </a>
                                            <?php elseif ($is_registered): ?>
                                                <span class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-check"></i>
                                                    Terdaftar
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if (getUserRole() === 'admin'): ?>
                                            <a href="event_participants.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-list"></i>
                                                Peserta
                                            </a>
                                            <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                                Edit
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Actions Float Button -->
<?php if (in_array(getUserRole(), ['admin', 'trainer'])): ?>
<div style="position: fixed; bottom: 30px; right: 30px; z-index: 1000;">
    <div class="dropdown dropup">
        <button class="btn btn-primary btn-lg" style="border-radius: 50%; width: 60px; height: 60px; box-shadow: 0 4px 12px rgba(30, 69, 159, 0.3);" data-toggle="dropdown">
            <i class="fas fa-plus"></i>
        </button>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="add_schedule.php">
                <i class="fas fa-clock"></i>
                Tambah Jadwal
            </a>
            <a class="dropdown-item" href="add_event.php">
                <i class="fas fa-calendar-plus"></i>
                Tambah Event
            </a>
            <a class="dropdown-item" href="attendance_today.php">
                <i class="fas fa-check"></i>
                Absensi Hari Ini
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// View Toggle
function toggleView(view) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    
    if (view === 'grid') {
        gridView.style.display = 'block';
        listView.style.display = 'none';
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
    }
    
    // Update button states
    document.querySelectorAll('[onclick^="toggleView"]').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    
    event.target.classList.remove('btn-outline-primary');
    event.target.classList.add('btn-primary');
}

// Schedule item hover effects
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to schedule items
    document.querySelectorAll('.schedule-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Add hover effects to event items
    document.querySelectorAll('.event-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});

// Auto-refresh every 5 minutes
setInterval(function() {
    location.reload();
}, 300000);
</script>

<style>
.schedule-item, .event-item, .schedule-card, .event-card {
    transition: all 0.3s ease;
}

.dropdown-menu {
    background: white;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 10px;
    overflow: hidden;
}

.dropdown-item {
    padding: 12px 20px;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background: #1E459F;
    color: white;
}

.dropdown-item i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .schedule-grid {
        overflow-x: auto;
    }
    
    .schedule-grid > div {
        grid-template-columns: repeat(7, 300px);
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>