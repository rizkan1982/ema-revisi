<?php
require_once '../../config/config.php';
requireLogin();

$page_title = "Daftar Request";
require_once '../../includes/header.php';

$user_role = getUserRole();

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on role
$where = [];
$params = [];

if (in_array($user_role, ['staff', 'member', 'trainer'])) {
    // Staff, trainers and members see only their own requests
    $where[] = "sr.requested_by = ?";
    $params[] = $_SESSION['user_id'];
}

// Add status filter
if ($status !== 'all') {
    $where[] = "sr.status = ?";
    $params[] = $status;
}

// Add search filter
if (!empty($search)) {
    $where[] = "(i.item_name LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch requests
$requests = $db->fetchAll(
    "SELECT sr.*, i.item_name, i.unit, i.current_stock,
            u.full_name as requester_name, u.role as requester_role,
            p.full_name as processor_name
     FROM inventory_requests sr
     LEFT JOIN inventory_items i ON sr.item_id = i.id
     LEFT JOIN users u ON sr.requested_by = u.id
     LEFT JOIN users p ON sr.reviewed_by = p.id
     {$where_clause}
     ORDER BY 
        CASE WHEN sr.status = 'pending' THEN 0 ELSE 1 END,
        CASE sr.priority 
            WHEN 'urgent' THEN 0
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            ELSE 3 
        END,
        sr.requested_at DESC",
    $params
);

// Get statistics
$stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

foreach ($requests as $r) {
    if (isset($stats[$r['status']])) {
        $stats[$r['status']]++;
    }
}
?>

<div class="page-header" style="margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; color: #1E459F;">
            <i class="fas fa-hand-paper"></i> Request Barang
        </h1>
        <p style="margin: 5px 0 0 0; color: #6c757d;">
            <?= in_array(getUserRole(), ['staff', 'member', 'trainer']) ? 'Daftar request Anda' : 'Kelola semua request barang' ?>
        </p>
    </div>
    <div>
        <?php if (in_array(getUserRole(), ['staff', 'member', 'trainer'])): ?>
            <a href="create_request.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Request Baru
            </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-4">
        <div class="card" style="border-left: 4px solid #ffc107;">
            <div class="card-body" style="padding: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.85rem; color: #6c757d; margin-bottom: 5px;">Pending</div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: #ffc107;">
                            <?= $stats['pending'] ?>
                        </div>
                    </div>
                    <div style="font-size: 2.5rem; color: #ffc107; opacity: 0.3;">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="border-left: 4px solid #28a745;">
            <div class="card-body" style="padding: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.85rem; color: #6c757d; margin-bottom: 5px;">Approved</div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: #28a745;">
                            <?= $stats['approved'] ?>
                        </div>
                    </div>
                    <div style="font-size: 2.5rem; color: #28a745; opacity: 0.3;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="border-left: 4px solid #dc3545;">
            <div class="card-body" style="padding: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.85rem; color: #6c757d; margin-bottom: 5px;">Rejected</div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: #dc3545;">
                            <?= $stats['rejected'] ?>
                        </div>
                    </div>
                    <div style="font-size: 2.5rem; color: #dc3545; opacity: 0.3;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-end">
            <div class="col-md-4">
                <label class="form-label"><i class="fas fa-filter"></i> Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><i class="fas fa-search"></i> Cari</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Cari nama barang, kode, atau requester..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Requests Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-hover" style="margin: 0;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th>Jumlah</th>
                        <?php if (hasPermission('manage_stock')): ?>
                        <th>Requester</th>
                        <?php endif; ?>
                        <th>Alasan</th>
                        <th>Urgensi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="<?= hasPermission('manage_stock') ? '8' : '7' ?>" style="text-align: center; padding: 50px;">
                            <i class="fas fa-inbox" style="font-size: 4rem; color: #dee2e6; margin-bottom: 15px;"></i>
                            <p style="color: #6c757d; margin: 0;">
                                <?= !empty($search) || $status !== 'all' ? 'Tidak ada request yang sesuai filter' : 'Belum ada request' ?>
                            </p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                        <?php
                        $urgency_colors = ['low' => 'info', 'medium' => 'warning', 'high' => 'danger'];
                        $urgency_labels = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];
                        $status_colors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                        $status_labels = ['pending' => 'Pending', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
                        ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <?= date('d/m/Y H:i', strtotime($r['requested_at'])) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($r['item_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($r['item_code'] ?? 'N/A') ?></small>
                            </td>
                            <td>
                                <strong><?= $r['requested_quantity'] ?></strong> <?= htmlspecialchars($r['unit']) ?>
                            </td>
                            <?php if (hasPermission('manage_stock')): ?>
                            <td>
                                <?= htmlspecialchars($r['requester_name']) ?><br>
                                <small class="text-muted"><?= ucfirst($r['requester_role']) ?></small>
                            </td>
                            <?php endif; ?>
                            <td>
                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                     title="<?= htmlspecialchars($r['reason']) ?>">
                                    <?= htmlspecialchars($r['reason']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $urgency_colors[$r['priority']] ?>">
                                    <?= $urgency_labels[$r['priority']] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $status_colors[$r['status']] ?>">
                                    <?= $status_labels[$r['status']] ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="#" onclick="viewRequest(<?= $r['id'] ?>)" class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('manage_stock') && $r['status'] === 'pending'): ?>
                                    <a href="process_request.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-success" title="Proses">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Request Modal -->
<div id="requestModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; padding: 20px; overflow: auto;">
    <div style="max-width: 600px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <div style="padding: 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0; color: #1E459F;">
                <i class="fas fa-file-alt"></i> Detail Request
            </h5>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6c757d;">
                &times;
            </button>
        </div>
        <div id="requestModalContent" style="padding: 20px;">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
function viewRequest(id) {
    const modal = document.getElementById('requestModal');
    const content = document.getElementById('requestModalContent');
    
    content.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #1E459F;"></i></div>';
    modal.style.display = 'block';
    
    // Fetch request details via AJAX (simplified version)
    fetch(`get_request.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading data</div>';
        });
}

function closeModal() {
    document.getElementById('requestModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('requestModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
