<?php
$page_title = "Tampilan Kalender";
require_once '../../includes/header.php';

// Get current month/year or from parameters
$current_month = $_GET['month'] ?? date('m');
$current_year = $_GET['year'] ?? date('Y');

// Validate month/year
$current_month = max(1, min(12, intval($current_month)));
$current_year = max(2020, min(2030, intval($current_year)));

// Get first day of month and number of days
$first_day = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
$last_day = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));
$first_day_of_week = date('w', strtotime($first_day)); // 0 = Sunday
$days_in_month = date('t', mktime(0, 0, 0, $current_month, 1, $current_year));

// Get all schedules for the month
$schedules = $db->fetchAll("
    SELECT s.*, c.class_name, c.martial_art_type, c.class_type,
           t.trainer_code, u.full_name as trainer_name,
           COUNT(mc.member_id) as enrolled_count
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    JOIN trainers t ON c.trainer_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN member_classes mc ON c.id = mc.class_id AND mc.status = 'active'
    WHERE s.is_active = 1 AND c.is_active = 1
    GROUP BY s.id
    ORDER BY s.start_time
");

// Get events for the month
$events = $db->fetchAll("
    SELECT e.*, COUNT(er.member_id) as registered_count
    FROM events e
    LEFT JOIN event_registrations er ON e.id = er.event_id
    WHERE e.event_date BETWEEN ? AND ? AND e.is_active = 1
    GROUP BY e.id
    ORDER BY e.event_date, e.start_time
", [$first_day, $last_day]);

// Month names
$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Day names
$day_names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$day_names_en = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

// Organize events by date
$events_by_date = [];
foreach ($events as $event) {
    $events_by_date[$event['event_date']][] = $event;
}

// Get previous/next month
$prev_month = $current_month == 1 ? 12 : $current_month - 1;
$prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
$next_month = $current_month == 12 ? 1 : $current_month + 1;
$next_year = $current_month == 12 ? $current_year + 1 : $current_year;
?>

<!-- Calendar Header -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="color: #1E459F; margin: 0;">
                    <i class="fas fa-calendar-alt"></i>
                    <?= $month_names[$current_month] ?> <?= $current_year ?>
                </h2>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="btn btn-outline-primary">
                    <i class="fas fa-chevron-left"></i>
                    <?= $month_names[$prev_month] ?>
                </a>
                
                <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-day"></i>
                    Bulan Ini
                </a>
                
                <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" class="btn btn-outline-primary">
                    <?= $month_names[$next_month] ?>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
        
        <!-- View Options -->
        <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
            <button class="btn btn-sm btn-outline-info" onclick="showAll()">Semua</button>
            <button class="btn btn-sm btn-outline-primary" onclick="filterByType('kickboxing')">Kickboxing</button>
            <button class="btn btn-sm btn-outline-danger" onclick="filterByType('boxing')">Boxing</button>
            <button class="btn btn-sm btn-outline-warning" onclick="filterByType('event')">Events</button>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="card">
    <div style="padding: 0;">
        <!-- Calendar -->
        <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #dee2e6;">
            <!-- Day Headers -->
            <?php foreach ($day_names as $day_name): ?>
                <div style="background: #1E459F; color: white; padding: 15px; text-align: center; font-weight: bold;">
                    <?= $day_name ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Empty cells for days before first day of month -->
            <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                <div style="background: #f8f9fa; min-height: 120px; padding: 8px; opacity: 0.5;">
                    <div style="color: #6c757d; font-size: 0.9rem;">
                        <?= date('j', strtotime('-' . ($first_day_of_week - $i) . ' days', strtotime($first_day))) ?>
                    </div>
                </div>
            <?php endfor; ?>
            
            <!-- Calendar days -->
            <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                <?php
                $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                $day_of_week = date('w', strtotime($current_date));
                $day_name_en = $day_names_en[$day_of_week];
                $is_today = $current_date === date('Y-m-d');
                $is_weekend = in_array($day_of_week, [0, 6]); // Sunday or Saturday
                
                // Get schedules for this day
                $day_schedules = array_filter($schedules, function($s) use ($day_name_en) {
                    return $s['day_of_week'] === $day_name_en;
                });
                
                // Get events for this day
                $day_events = $events_by_date[$current_date] ?? [];
                ?>
                
                <div class="calendar-day <?= $is_today ? 'today' : '' ?> <?= $is_weekend ? 'weekend' : '' ?>" 
                     style="background: white; min-height: 120px; padding: 8px; border: <?= $is_today ? '2px solid #1E459F' : 'none' ?>;">
                    
                    <!-- Day Number -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="font-weight: bold; color: <?= $is_today ? '#1E459F' : ($is_weekend ? '#CF2A2A' : '#495057') ?>; font-size: 1.1rem;">
                            <?= $day ?>
                        </span>
                        
                        <?php if ($is_today): ?>
                            <span class="badge badge-primary" style="font-size: 0.7rem;">HARI INI</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Schedules -->
                    <?php foreach ($day_schedules as $schedule): ?>
                        <div class="schedule-mini kickboxing-<?= $schedule['martial_art_type'] === 'kickboxing' ? '1' : '0' ?> boxing-<?= $schedule['martial_art_type'] === 'boxing' ? '1' : '0' ?>" 
                             style="background: <?= $schedule['martial_art_type'] === 'kickboxing' ? 'rgba(30, 69, 159, 0.1)' : 'rgba(207, 42, 42, 0.1)' ?>; 
                                    border-left: 3px solid <?= $schedule['martial_art_type'] === 'kickboxing' ? '#1E459F' : '#CF2A2A' ?>; 
                                    padding: 3px 6px; margin-bottom: 3px; border-radius: 3px; cursor: pointer;"
                             onclick="showScheduleDetail(<?= $schedule['id'] ?>)">
                            <div style="font-size: 0.7rem; font-weight: bold; color: <?= $schedule['martial_art_type'] === 'kickboxing' ? '#1E459F' : '#CF2A2A' ?>;">
                                <?= date('H:i', strtotime($schedule['start_time'])) ?> 
                                <?= substr($schedule['class_name'], 0, 8) ?><?= strlen($schedule['class_name']) > 8 ? '...' : '' ?>
                            </div>
                            <div style="font-size: 0.6rem; color: #6c757d;">
                                <?= $schedule['enrolled_count'] ?> peserta
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Events -->
                    <?php foreach ($day_events as $event): ?>
                        <div class="event-mini event-1" 
                             style="background: linear-gradient(90deg, #FABD32, #E1DCCA); 
                                    padding: 3px 6px; margin-bottom: 3px; border-radius: 3px; cursor: pointer; border: 1px solid #FABD32;"
                             onclick="showEventDetail(<?= $event['id'] ?>)">
                            <div style="font-size: 0.7rem; font-weight: bold; color: #1E459F;">
                                <i class="fas fa-star"></i>
                                <?= substr($event['event_name'], 0, 8) ?><?= strlen($event['event_name']) > 8 ? '...' : '' ?>
                            </div>
                            <div style="font-size: 0.6rem; color: #6c757d;">
                                <?= $event['start_time'] ? date('H:i', strtotime($event['start_time'])) : 'All Day' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Add button for admins -->
                    <?php if (getUserRole() === 'admin'): ?>
                        <div style="margin-top: 5px;">
                            <button class="btn btn-sm btn-outline-primary" style="font-size: 0.7rem; padding: 2px 6px;" onclick="quickAdd('<?= $current_date ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
            
            <!-- Fill remaining cells -->
            <?php
            $total_cells = 42; // 6 rows × 7 days
            $cells_used = $first_day_of_week + $days_in_month;
            for ($i = $cells_used; $i < $total_cells; $i++):
                $next_month_day = $i - $cells_used + 1;
            ?>
                <div style="background: #f8f9fa; min-height: 120px; padding: 8px; opacity: 0.5;">
                    <div style="color: #6c757d; font-size: 0.9rem;">
                        <?= $next_month_day ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px;">
        <h5 style="color: #1E459F; margin-bottom: 15px;">Keterangan:</h5>
        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 15px; background: rgba(30, 69, 159, 0.1); border-left: 3px solid #1E459F;"></div>
                <span>Kelas Kickboxing</span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 15px; background: rgba(207, 42, 42, 0.1); border-left: 3px solid #CF2A2A;"></div>
                <span>Kelas Boxing</span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 15px; background: linear-gradient(90deg, #FABD32, #E1DCCA); border: 1px solid #FABD32;"></div>
                <span>Event Khusus</span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 15px; border: 2px solid #1E459F;"></div>
                <span>Hari Ini</span>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Detail Modal -->
<div id="scheduleModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 10px; padding: 30px; max-width: 500px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="color: #1E459F; margin: 0;">Detail Jadwal</h4>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div id="scheduleModalContent"></div>
    </div>
</div>

<!-- Event Detail Modal -->
<div id="eventModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 10px; padding: 30px; max-width: 500px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="color: #1E459F; margin: 0;">Detail Event</h4>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div id="eventModalContent"></div>
    </div>
</div>

<script>
// Filter functions
function showAll() {
    document.querySelectorAll('.schedule-mini, .event-mini').forEach(item => {
        item.style.display = 'block';
    });
    updateButtonStates(event.target);
}

function filterByType(type) {
    document.querySelectorAll('.schedule-mini, .event-mini').forEach(item => {
        item.style.display = 'none';
    });
    
    if (type === 'event') {
        document.querySelectorAll('.event-mini').forEach(item => {
            item.style.display = 'block';
        });
    } else {
        document.querySelectorAll(`.${type}-1`).forEach(item => {
            item.style.display = 'block';
        });
    }
    
    updateButtonStates(event.target);
}

function updateButtonStates(activeButton) {
    document.querySelectorAll('[onclick^="showAll"], [onclick^="filterByType"]').forEach(btn => {
        btn.classList.remove('btn-primary', 'btn-danger', 'btn-warning', 'btn-info');
        btn.classList.add('btn-outline-primary', 'btn-outline-danger', 'btn-outline-warning', 'btn-outline-info');
    });
    
    activeButton.classList.remove('btn-outline-primary', 'btn-outline-danger', 'btn-outline-warning', 'btn-outline-info');
    if (activeButton.onclick.toString().includes('showAll')) {
        activeButton.classList.add('btn-info');
    } else if (activeButton.onclick.toString().includes('kickboxing')) {
        activeButton.classList.add('btn-primary');
    } else if (activeButton.onclick.toString().includes('boxing')) {
        activeButton.classList.add('btn-danger');
    } else if (activeButton.onclick.toString().includes('event')) {
        activeButton.classList.add('btn-warning');
    }
}

// Modal functions
function showScheduleDetail(scheduleId) {
    // Fetch schedule details via AJAX
    fetch(`ajax_schedule_detail.php?id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('scheduleModalContent').innerHTML = `
                <div style="margin-bottom: 15px;">
                    <strong>Kelas:</strong> ${data.class_name}<br>
                    <strong>Waktu:</strong> ${data.start_time} - ${data.end_time}<br>
                    <strong>Pelatih:</strong> ${data.trainer_name}<br>
                    <strong>Tipe:</strong> <span class="badge badge-${data.martial_art_type === 'kickboxing' ? 'info' : 'danger'}">${data.martial_art_type.toUpperCase()}</span>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong>Peserta:</strong> ${data.enrolled_count}/${data.max_participants}<br>
                    <strong>Kelas:</strong> <span class="badge badge-${data.class_type === 'regular' ? 'success' : 'warning'}">${data.class_type}</span>
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <a href="view_schedule.php?id=${scheduleId}" class="btn btn-primary">Detail Lengkap</a>
                    ${data.can_enroll ? `<a href="enroll_class.php?id=${data.class_id}" class="btn btn-success">Daftar Kelas</a>` : ''}
                </div>
            `;
            document.getElementById('scheduleModal').style.display = 'block';
        });
}

function showEventDetail(eventId) {
    // Fetch event details via AJAX
    fetch(`ajax_event_detail.php?id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('eventModalContent').innerHTML = `
                <div style="margin-bottom: 15px;">
                    <strong>Event:</strong> ${data.event_name}<br>
                    <strong>Tanggal:</strong> ${new Date(data.event_date).toLocaleDateString('id-ID')}<br>
                    <strong>Waktu:</strong> ${data.start_time || 'All Day'}${data.end_time ? ' - ' + data.end_time : ''}<br>
                    <strong>Lokasi:</strong> ${data.location || '-'}
                </div>
                <div style="margin-bottom: 15px;">
                    <strong>Tipe:</strong> <span class="badge badge-warning">${data.event_type.toUpperCase()}</span><br>
                    <strong>Peserta:</strong> ${data.registered_count} orang<br>
                    ${data.registration_fee > 0 ? `<strong>Biaya:</strong> Rp ${data.registration_fee.toLocaleString('id-ID')}` : '<strong>Gratis</strong>'}
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <a href="view_event.php?id=${eventId}" class="btn btn-primary">Detail Event</a>
                    ${data.can_register ? `<a href="register_event.php?id=${eventId}" class="btn btn-success">Daftar Event</a>` : ''}
                </div>
            `;
            document.getElementById('eventModal').style.display = 'block';
        });
}

function closeModal() {
    document.getElementById('scheduleModal').style.display = 'none';
    document.getElementById('eventModal').style.display = 'none';
}

function quickAdd(date) {
    const options = [
        { text: 'Tambah Jadwal', url: `add_schedule.php?date=${date}` },
        { text: 'Tambah Event', url: `add_event.php?date=${date}` }
    ];
    
    if (confirm('Pilih aksi:\n1. Tambah Jadwal\n2. Tambah Event\n\nKlik OK untuk Jadwal, Cancel untuk Event')) {
        window.location.href = options[0].url;
    } else {
        window.location.href = options[1].url;
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<style>
.calendar-day {
    transition: all 0.2s ease;
}

.calendar-day:hover {
    background: #f8f9fa !important;
    transform: scale(1.02);
}

.schedule-mini, .event-mini {
    transition: all 0.2s ease;
}

.schedule-mini:hover, .event-mini:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.today {
    position: relative;
}

.today::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(30, 69, 159, 0.05);
    pointer-events: none;
}

.weekend {
    background: #fafafa !important;
}

@media (max-width: 768px) {
    .calendar-grid {
        font-size: 0.8rem;
    }
    
    .calendar-day {
        min-height: 80px !important;
        padding: 4px !important;
    }
    
    .schedule-mini, .event-mini {
        font-size: 0.6rem !important;
        padding: 2px 4px !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>