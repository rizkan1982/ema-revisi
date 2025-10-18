<?php
require_once '../../config/config.php';
requireLogin();

// Check permission - only admins
if (!hasPermission('manage_stock')) {
    header("Location: requests.php?error=unauthorized");
    exit;
}

$error = '';
$success = '';

// Get request ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: requests.php?error=invalid_id");
    exit;
}

// Fetch request data
$request = $db->fetch(
    "SELECT r.*, i.item_name, i.item_code, i.unit, i.current_stock, i.min_stock,
            u.full_name as requester_name, u.email as requester_email, u.role as requester_role
     FROM inventory_requests r
     LEFT JOIN inventory_items i ON r.item_id = i.id
     LEFT JOIN users u ON r.requested_by = u.id
     WHERE r.id = ?",
    [$id]
);

if (!$request) {
    header("Location: requests.php?error=not_found");
    exit;
}

if ($request['status'] !== 'pending') {
    header("Location: requests.php?error=already_processed");
    exit;
}

// PROCESS POST REQUEST BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action']; // approve or reject
    $review_notes = trim($_POST['admin_notes']); // Keep POST name but use correct DB field
    
    if (!in_array($action, ['approve', 'reject'])) {
        $error = 'Aksi tidak valid!';
    } else {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        try {
            $db->beginTransaction();
            
            // Update request status
            $db->query(
                "UPDATE inventory_requests 
                SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
                WHERE id = ?",
                [$status, $review_notes, $_SESSION['user_id'], $id]
            );
            
            // If approved, reduce stock
            if ($action === 'approve') {
                $new_stock = $request['current_stock'] - $request['requested_quantity'];
                
                if ($new_stock < 0) {
                    throw new Exception('Stok tidak mencukupi!');
                }
                
                // Update stock
                $db->query(
                    "UPDATE inventory_items SET current_stock = ?, updated_at = NOW() WHERE id = ?",
                    [$new_stock, $request['item_id']]
                );
                
                // Create history
                $db->query(
                    "INSERT INTO inventory_history 
                    (item_id, transaction_type, quantity, stock_before, stock_after, notes, performed_by) 
                    VALUES (?, 'out', ?, ?, ?, ?, ?)",
                    [
                        $request['item_id'],
                        $request['requested_quantity'],
                        $request['current_stock'],
                        $new_stock,
                        "Request disetujui untuk {$request['requester_name']}: {$request['reason']}",
                        $_SESSION['user_id']
                    ]
                );
                
                // Check low stock
                if ($new_stock < $request['min_stock']) {
                    $admins = $db->fetchAll("SELECT id FROM users WHERE role IN ('super_admin', 'admin') AND is_active = 1");
                    foreach ($admins as $admin) {
                        sendNotification(
                            $admin['id'],
                            'Stok Barang Menipis',
                            "Stok '{$request['item_name']}' sekarang $new_stock (di bawah minimum {$request['min_stock']})",
                            'stock_alert',
                            'inventory_items',
                            $request['item_id'],
                            BASE_URL . 'modules/inventory/view.php?id=' . $request['item_id']
                        );
                    }
                }
            }
            
            // Log activity
            logActivity('process_request', 'inventory_requests', $id, 
                ['status' => 'pending'], 
                ['status' => $status, 'action' => $action]
            );
            
            // Notify requester
            $notification_title = $action === 'approve' ? 'Request Disetujui' : 'Request Ditolak';
            $notification_message = $action === 'approve' 
                ? "Request Anda untuk {$request['requested_quantity']} {$request['unit']} '{$request['item_name']}' telah disetujui"
                : "Request Anda untuk {$request['requested_quantity']} {$request['unit']} '{$request['item_name']}' ditolak";
            
            if (!empty($review_notes)) {
                $notification_message .= ": " . $review_notes;
            }
            
            sendNotification(
                $request['requested_by'],
                $notification_title,
                $notification_message,
                'request_processed',
                'inventory_requests',
                $id,
                BASE_URL . 'modules/inventory/requests.php'
            );
            
            $db->commit();
            
            $success = $action === 'approve' 
                ? "Request berhasil disetujui dan stok telah dikurangi!" 
                : "Request berhasil ditolak!";
            
            header("Location: requests.php?success=" . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Now include header after all processing
$page_title = "Proses Request";
require_once '../../includes/header.php';
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-clipboard-check"></i> Proses Request
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">Review dan proses permintaan barang</p>
    </div>
    <div>
        <a href="requests.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?= $success ?>
</div>
<?php endif; ?>

<?php if (!$success): ?>
<div class="row">
    <div class="col-lg-8">
        <!-- Request Details -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: linear-gradient(135deg, #1E459F, #CF2A2A); color: white;">
                <h5 style="margin: 0;">
                    <i class="fas fa-file-alt"></i> Detail Request
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td style="width: 40%; font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-user"></i> Requester
                                </td>
                                <td>
                                    <?= htmlspecialchars($request['requester_name']) ?><br>
                                    <small class="text-muted"><?= ucfirst($request['requester_role']) ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-clock"></i> Tanggal Request
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($request['requested_at'])) ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-exclamation-circle"></i> Urgensi
                                </td>
                                <td>
                                    <?php
                                    $urgency_colors = ['low' => 'info', 'medium' => 'warning', 'high' => 'danger'];
                                    $urgency_labels = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];
                                    ?>
                                    <span class="badge badge-<?= $urgency_colors[$request['priority']] ?>">
                                        <?= $urgency_labels[$request['priority']] ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td style="width: 40%; font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-box"></i> Barang
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($request['item_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($request['item_code']) ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-hashtag"></i> Jumlah Diminta
                                </td>
                                <td style="font-size: 1.2rem; font-weight: 700; color: #1E459F;">
                                    <?= $request['requested_quantity'] ?> <?= htmlspecialchars($request['unit']) ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;">
                                    <i class="fas fa-warehouse"></i> Stok Tersedia
                                </td>
                                <td style="font-weight: 700; <?= $request['current_stock'] < $request['requested_quantity'] ? 'color: #dc3545;' : 'color: #28a745;' ?>">
                                    <?= $request['current_stock'] ?> <?= htmlspecialchars($request['unit']) ?>
                                    <?php if ($request['current_stock'] < $request['requested_quantity']): ?>
                                        <br><small style="color: #dc3545;">⚠️ Stok tidak mencukupi!</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <hr>
                
                <div>
                    <strong style="color: #6c757d;">
                        <i class="fas fa-comment-alt"></i> Alasan Permintaan:
                    </strong>
                    <p style="margin: 10px 0 0 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <?= nl2br(htmlspecialchars($request['reason'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Process Form -->
        <div class="card">
            <div class="card-header" style="background: #f8f9fa;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-tasks"></i> Proses Request
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="processForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clipboard-check"></i> Keputusan *
                        </label>
                        <div style="display: flex; gap: 15px;">
                            <label class="btn btn-outline-success" style="flex: 1; padding: 15px; cursor: pointer;">
                                <input type="radio" name="action" value="approve" required style="margin-right: 10px;">
                                <i class="fas fa-check-circle"></i> Setujui Request
                            </label>
                            <label class="btn btn-outline-danger" style="flex: 1; padding: 15px; cursor: pointer;">
                                <input type="radio" name="action" value="reject" required style="margin-right: 10px;">
                                <i class="fas fa-times-circle"></i> Tolak Request
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-sticky-note"></i> Catatan Admin
                        </label>
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Opsional: Tambahkan catatan untuk requester (alasan penolakan, instruksi pengambilan, dll)"></textarea>
                    </div>
                    
                    <hr style="margin: 30px 0;">
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <a href="requests.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Proses Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Stock Impact Preview -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: #1E459F; color: white;">
                <h5 style="margin: 0;">
                    <i class="fas fa-calculator"></i> Dampak Stok
                </h5>
            </div>
            <div class="card-body" style="text-align: center;">
                <div style="margin-bottom: 20px;">
                    <small style="color: #6c757d;">Stok Sekarang</small>
                    <div style="font-size: 2rem; font-weight: 700; color: #6c757d;">
                        <?= $request['current_stock'] ?>
                    </div>
                    <small><?= htmlspecialchars($request['unit']) ?></small>
                </div>
                
                <div style="font-size: 1.5rem; color: #dc3545; margin: 20px 0;">
                    <i class="fas fa-arrow-down"></i>
                    <strong>- <?= $request['requested_quantity'] ?></strong>
                </div>
                
                <div style="padding: 15px; background: #f8f9fa; border-radius: 10px;">
                    <small style="color: #6c757d;">Stok Jika Disetujui</small>
                    <div style="font-size: 2rem; font-weight: 700; color: <?= ($request['current_stock'] - $request['requested_quantity']) < $request['min_stock'] ? '#ffc107' : '#28a745' ?>;">
                        <?= max(0, $request['current_stock'] - $request['requested_quantity']) ?>
                    </div>
                    <small><?= htmlspecialchars($request['unit']) ?></small>
                </div>
                
                <?php if (($request['current_stock'] - $request['requested_quantity']) < $request['min_stock']): ?>
                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 0.85rem; color: #856404;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Peringatan:</strong> Stok akan di bawah minimum (<?= $request['min_stock'] ?>)
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Card -->
        <div class="card">
            <div class="card-header" style="background: #f8f9fa;">
                <h5 style="margin: 0; color: #1E459F;">
                    <i class="fas fa-info-circle"></i> Panduan
                </h5>
            </div>
            <div class="card-body" style="font-size: 0.9rem;">
                <p><strong>Setujui Request Jika:</strong></p>
                <ul style="margin-bottom: 15px;">
                    <li>Stok tersedia cukup</li>
                    <li>Alasan jelas dan valid</li>
                    <li>Urgensi sesuai kebutuhan</li>
                </ul>
                
                <p><strong>Tolak Request Jika:</strong></p>
                <ul>
                    <li>Stok tidak mencukupi</li>
                    <li>Alasan tidak jelas</li>
                    <li>Duplikasi request</li>
                    <li>Tidak sesuai prosedur</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.btn-outline-success input:checked ~ i,
.btn-outline-danger input:checked ~ i {
    font-weight: 900;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
