<?php
$page_title = "Notifikasi & Pengumuman";
require_once '../../includes/header.php';

// Get user notifications
$notifications = $db->fetchAll("
    SELECT n.*, 
           CASE 
               WHEN n.sent_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'new'
               WHEN n.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'recent'
               ELSE 'old'
           END as age_category
    FROM notifications n 
    WHERE n.recipient_id = ? 
    ORDER BY n.sent_at DESC
    LIMIT 50
", [$_SESSION['user_id']]);

// Mark as read if requested - FIXED
if (isset($_GET['mark_read']) && getUserRole() !== 'member') {
    $notification_id = intval($_GET['mark_read']);
    $db->query("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ?", [$notification_id, $_SESSION['user_id']]);
    redirect('modules/notifications/');
}

// Mark all as read - FIXED
if (isset($_GET['mark_all_read'])) {
    $db->query("UPDATE notifications SET is_read = 1 WHERE recipient_id = ?", [$_SESSION['user_id']]);
    redirect('modules/notifications/');
}

// Get notification counts
$unread_count = $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND is_read = 0", [$_SESSION['user_id']])['count'];

// Get system notifications (for admins) - FIXED
if (getUserRole() === 'admin') {
    $overdue_payments = $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'pending' AND due_date < CURRENT_DATE")['count'];
    $new_members_today = $db->fetch("SELECT COUNT(*) as count FROM members WHERE join_date = CURRENT_DATE")['count'];
    $full_classes = $db->fetch("
        SELECT COUNT(*) as count
        FROM classes c
        JOIN (
            SELECT class_id, COUNT(*) as enrolled
            FROM member_classes 
            WHERE status = 'active'
            GROUP BY class_id
        ) mc ON c.id = mc.class_id
        WHERE mc.enrolled >= c.max_participants
    ")['count'];
    
    $system_notifications = [];
    
    if ($overdue_payments > 0) {
        $system_notifications[] = [
            'type' => 'system',
            'title' => 'Pembayaran Overdue',
            'message' => "$overdue_payments pembayaran telah melewati jatuh tempo",
            'sent_at' => date('Y-m-d H:i:s'),
            'notification_type' => 'payment_overdue'
        ];
    }
    
    if ($new_members_today > 0) {
        $system_notifications[] = [
            'type' => 'system',
            'title' => 'Member Baru',
            'message' => "$new_members_today member baru bergabung hari ini",
            'sent_at' => date('Y-m-d H:i:s'),
            'notification_type' => 'new_members'
        ];
    }
    
    if ($full_classes > 0) {
        $system_notifications[] = [
            'type' => 'system',
            'title' => 'Kelas Penuh',
            'message' => "$full_classes kelas mencapai kapasitas maksimal",
            'sent_at' => date('Y-m-d H:i:s'),
            'notification_type' => 'class_full'
        ];
    }
}
?>

<!-- Rest of the file remains the same as before -->
<!-- Notification Header -->
<div class="card" style="margin-bottom: 30px;">
    <div style="padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="color: #1E459F; margin: 0;">
                    <i class="fas fa-bell"></i>
                    Notifikasi
                </h3>
                <?php if ($unread_count > 0): ?>
                    <span class="badge badge-danger" style="margin-left: 10px;">
                        <?= $unread_count ?> belum dibaca
                    </span>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-success btn-sm">
                        <i class="fas fa-check-double"></i>
                        Tandai Semua Dibaca
                    </a>
                <?php endif; ?>
                
                <?php if (getUserRole() === 'admin'): ?>
                    <a href="send_notification.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-paper-plane"></i>
                        Kirim Notifikasi
                    </a>
                    <a href="notification_settings.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-cog"></i>
                        Pengaturan
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- System Notifications (Admin Only) -->
<?php if (getUserRole() === 'admin' && !empty($system_notifications)): ?>
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h4 class="card-title" style="color: #CF2A2A;">
            <i class="fas fa-exclamation-triangle"></i>
            Notifikasi Sistem
        </h4>
    </div>
    
    <div style="padding: 0;">
        <?php foreach ($system_notifications as $sys_notif): ?>
            <div style="padding: 15px; border-bottom: 1px solid #dee2e6; background: <?= $sys_notif['notification_type'] === 'payment_overdue' ? 'rgba(220, 53, 69, 0.05)' : 'rgba(40, 167, 69, 0.05)' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                            <i class="fas <?= $sys_notif['notification_type'] === 'payment_overdue' ? 'fa-exclamation-circle' : ($sys_notif['notification_type'] === 'new_members' ? 'fa-user-plus' : 'fa-users') ?>" 
                               style="color: <?= $sys_notif['notification_type'] === 'payment_overdue' ? '#CF2A2A' : '#1E459F' ?>;"></i>
                            <strong style="color: <?= $sys_notif['notification_type'] === 'payment_overdue' ? '#CF2A2A' : '#1E459F' ?>;">
                                <?= htmlspecialchars($sys_notif['title']) ?>
                            </strong>
                        </div>
                        <div style="color: #6c757d; margin-bottom: 8px;">
                            <?= htmlspecialchars($sys_notif['message']) ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 5px;">
                        <?php if ($sys_notif['notification_type'] === 'payment_overdue'): ?>
                            <a href="<?= BASE_URL ?>modules/finance/?filter=overdue" class="btn btn-sm btn-danger">
                                <i class="fas fa-eye"></i>
                                Lihat
                            </a>
                        <?php elseif ($sys_notif['notification_type'] === 'new_members'): ?>
                            <a href="<?= BASE_URL ?>modules/members/?filter_date=today" class="btn btn-sm btn-success">
                                <i class="fas fa-eye"></i>
                                Lihat
                            </a>
                        <?php elseif ($sys_notif['notification_type'] === 'class_full'): ?>
                            <a href="<?= BASE_URL ?>modules/schedule/class_management.php" class="btn btn-sm btn-warning">
                                <i class="fas fa-eye"></i>
                                Lihat
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- User Notifications -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Riwayat Notifikasi</h4>
    </div>
    
    <?php if (empty($notifications)): ?>
        <div style="padding: 60px; text-align: center; color: #6c757d;">
            <i class="fas fa-bell-slash" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <h5>Belum Ada Notifikasi</h5>
            <p>Notifikasi akan muncul di sini ketika ada update terbaru</p>
        </div>
    <?php else: ?>
        <div style="padding: 0;">
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item" style="padding: 20px; border-bottom: 1px solid #dee2e6; <?= !$notification['is_read'] ? 'background: rgba(30, 69, 159, 0.03); border-left: 4px solid #1E459F;' : '' ?> transition: all 0.3s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <!-- Notification Icon -->
                                <?php
                                $icon_class = '';
                                $icon_color = '';
                                switch ($notification['type']) {
                                    case 'payment_reminder':
                                        $icon_class = 'fa-money-bill-wave';
                                        $icon_color = '#FABD32';
                                        break;
                                    case 'schedule_change':
                                        $icon_class = 'fa-calendar-alt';
                                        $icon_color = '#1E459F';
                                        break;
                                    case 'event_announcement':
                                        $icon_class = 'fa-bullhorn';
                                        $icon_color = '#CF2A2A';
                                        break;
                                    default:
                                        $icon_class = 'fa-info-circle';
                                        $icon_color = '#17a2b8';
                                }
                                ?>
                                <i class="fas <?= $icon_class ?>" style="color: <?= $icon_color ?>; font-size: 1.2rem;"></i>
                                
                                <div>
                                    <strong style="color: #1E459F; font-size: 1.1rem;">
                                        <?= htmlspecialchars($notification['title']) ?>
                                    </strong>
                                    
                                    <div style="display: flex; gap: 10px; margin-top: 3px;">
                                        <span class="badge <?= $notification['type'] === 'payment_reminder' ? 'badge-warning' : ($notification['type'] === 'schedule_change' ? 'badge-info' : 'badge-danger') ?>">
                                            <?= ucwords(str_replace('_', ' ', $notification['type'])) ?>
                                        </span>
                                        
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge badge-primary">BARU</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($notification['age_category'] === 'new'): ?>
                                            <span class="badge badge-success">Hari Ini</span>
                                        <?php elseif ($notification['age_category'] === 'recent'): ?>
                                            <span class="badge badge-secondary">Minggu Ini</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-left: 35px; color: #495057; line-height: 1.5;">
                                <?= htmlspecialchars($notification['message']) ?>
                            </div>
                            
                            <div style="margin-left: 35px; margin-top: 10px; font-size: 0.85rem; color: #6c757d;">
                                <i class="fas fa-clock"></i>
                                <?= formatDateTime($notification['sent_at']) ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 5px;">
                            <?php if (!$notification['is_read']): ?>
                                <a href="?mark_read=<?= $notification['id'] ?>" class="btn btn-sm btn-outline-primary" title="Tandai dibaca">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>