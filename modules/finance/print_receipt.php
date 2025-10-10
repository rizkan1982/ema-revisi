<?php
require_once '../../config/config.php';
requireRole(['admin', 'trainer', 'member']);

$payment_id = intval($_GET['id']);

$payment = $db->fetch("
    SELECT p.*, m.member_code, u.full_name, u.email, u.phone
    FROM payments p
    JOIN members m ON p.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE p.id = ?
", [$payment_id]);

if (!$payment) {
    die('Payment not found');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= $payment['receipt_number'] ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        .receipt {
            border: 2px dashed #1E459F;
            padding: 20px;
            background: white;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1E459F;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1E459F;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 12px;
            color: #666;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 5px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 2px solid #1E459F;
            border-bottom: 2px solid #1E459F;
            font-weight: bold;
            font-size: 16px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 11px;
            color: #666;
        }
        .status {
            text-align: center;
            padding: 8px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .paid { background: #d4edda; color: #155724; }
        .pending { background: #fff3cd; color: #856404; }
        .overdue { background: #f8d7da; color: #721c24; }
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="background: #1E459F; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <div class="receipt">
        <div class="header">
            <div class="logo">🥋 EMA CAMP</div>
            <div class="subtitle">Elite Martial Art</div>
            <div class="subtitle">Management System</div>
        </div>

        <div class="row">
            <span>Receipt No:</span>
            <span><?= $payment['receipt_number'] ?: 'N/A' ?></span>
        </div>

        <div class="row">
            <span>Date:</span>
            <span><?= formatDate($payment['payment_date']) ?></span>
        </div>

        <div class="row">
            <span>Time:</span>
            <span><?= date('H:i:s') ?></span>
        </div>

        <div style="margin: 20px 0; text-align: center; font-size: 14px; font-weight: bold; color: #1E459F;">
            PAYMENT RECEIPT
        </div>

        <div class="row">
            <span>Member:</span>
            <span><?= htmlspecialchars($payment['full_name']) ?></span>
        </div>

        <div class="row">
            <span>Member Code:</span>
            <span><?= $payment['member_code'] ?></span>
        </div>

        <div class="row">
            <span>Payment Type:</span>
            <span><?= ucwords(str_replace('_', ' ', $payment['payment_type'])) ?></span>
        </div>

        <div class="row">
            <span>Method:</span>
            <span><?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?></span>
        </div>

        <?php if ($payment['due_date']): ?>
        <div class="row">
            <span>Due Date:</span>
            <span><?= formatDate($payment['due_date']) ?></span>
        </div>
        <?php endif; ?>

        <div class="total-row">
            <span>TOTAL AMOUNT:</span>
            <span><?= formatRupiah($payment['amount']) ?></span>
        </div>

        <div class="status <?= $payment['status'] ?>">
            STATUS: <?= strtoupper($payment['status']) ?>
        </div>

        <?php if ($payment['description']): ?>
        <div style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;">
            <strong>Notes:</strong><br>
            <?= htmlspecialchars($payment['description']) ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div>Thank you for your payment!</div>
            <div style="margin-top: 10px;">
                EMA Camp - Elite Martial Art<br>
                Phone: +62-XXX-XXXX-XXXX<br>
                Email: info@emacamp.com
            </div>
            <div style="margin-top: 15px; font-size: 10px;">
                Generated on: <?= date('d F Y H:i:s') ?><br>
                System: EMA Camp Management v1.0
            </div>
        </div>
    </div>

    <script>
        // Auto print when loaded
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>