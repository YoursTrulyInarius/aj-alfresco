<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("
    SELECT p.*, u.full_name, u.business_name, s.stall_number
    FROM payments p
    JOIN users u ON u.id = p.tenant_id
    JOIN contracts c ON c.id = p.contract_id
    JOIN stalls s ON s.id = c.stall_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if (!$p) die("Receipt not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?php echo htmlspecialchars($p['receipt_number']); ?></title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 40px; }
        .receipt-card { background: #fff; max-width: 400px; margin: auto; padding: 20px; border: 1px solid #ddd; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .header { text-align: center; border-bottom: 2px dashed #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #ffb6c1; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .row .label { color: #666; }
        .row .value { font-weight: bold; }
        .total { border-top: 2px dashed #eee; padding-top: 10px; margin-top: 10px; font-size: 18px; color: #d63384; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #999; }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-card { box-shadow: none; border: none; }
            .no-print { display: none; }
        }
        .btn-print {
            display: block; width: 100%; border: none; background: #ffb6c1; color: #fff;
            padding: 10px; font-weight: bold; border-radius: 5px; cursor: pointer;
            margin-bottom: 10px;
        }
        .btn-back {
            position: fixed; top: 20px; right: 20px;
            background: #333; color: #fff; padding: 10px 20px;
            border-radius: 8px; font-weight: bold; text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .btn-back:hover { background: #000; }
    </style>
</head>
<body>
    <a href="payments.php" class="btn-back no-print">← Back to Payments</a>
    <div class="receipt-card">
        <button class="btn-print no-print" onclick="window.print()">Print Receipt</button>
        <div class="header">
            <h2>A&J Alfresco</h2>
            <p>Official Rental Receipt</p>
        </div>
        <div class="row">
            <span class="label">Receipt #:</span>
            <span class="value"><?php echo htmlspecialchars($p['receipt_number']); ?></span>
        </div>
        <div class="row">
            <span class="label">Date:</span>
            <span class="value"><?php echo formatDate($p['payment_date']); ?></span>
        </div>
        <hr style="border:none; border-top: 1px solid #eee;">
        <div class="row">
            <span class="label">Tenant:</span>
            <span class="value"><?php echo htmlspecialchars($p['full_name']); ?></span>
        </div>
        <div class="row">
            <span class="label">Business:</span>
            <span class="value"><?php echo htmlspecialchars($p['business_name']); ?></span>
        </div>
        <div class="row">
            <span class="label">Stall:</span>
            <span class="value"><?php echo htmlspecialchars($p['stall_number']); ?></span>
        </div>
        <div class="row">
            <span class="label">Month for:</span>
            <span class="value"><?php echo date('M Y', strtotime($p['payment_for_month'].'-01')); ?></span>
        </div>
        <div class="row">
            <span class="label">Method:</span>
            <span class="value"><?php echo strtoupper($p['payment_method']); ?></span>
        </div>
        <?php if($p['reference_number']): ?>
        <div class="row">
            <span class="label">Ref ID:</span>
            <span class="value"><?php echo htmlspecialchars($p['reference_number']); ?></span>
        </div>
        <?php endif; ?>
        <div class="row total">
            <span class="label">TOTAL PAID:</span>
            <span class="value"><?php echo formatMoney($p['amount']); ?></span>
        </div>
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>Admin: <?php echo htmlspecialchars($p['operator']); ?></p>
        </div>
    </div>
</body>
</html>