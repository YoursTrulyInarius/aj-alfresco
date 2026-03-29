<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("
  SELECT p.*, u.full_name tenant_name, u.email tenant_email, u.phone tenant_phone,
         s.stall_number
  FROM payments p
  JOIN users u ON u.id = p.tenant_id
  JOIN contracts c ON c.id = p.contract_id
  JOIN stalls s ON s.id = c.stall_id
  WHERE p.id=? AND p.tenant_id=?
  LIMIT 1
");
$stmt->bind_param("ii", $id, $tenantId);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();

if (!$pay) {
    die("Receipt not found or not allowed.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Receipt <?php echo htmlspecialchars($pay['receipt_number']); ?></title>
  <link rel="stylesheet" href="../assets/css/style.css"/>
  <style>
    .btn-back {
      position: fixed; top: 20px; right: 20px;
      background: var(--accent); color: #fff; padding: 10px 20px;
      border-radius: 8px; font-weight: bold; text-decoration: none;
      box-shadow: var(--shadow);
      z-index: 1000;
    }
    .btn-back:hover { background: var(--accent-dark); }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
<a href="payments.php" class="btn-back no-print">← My Payments</a>

<div style="text-align:center;margin-top:60px">
  <button class="btn btn-primary" onclick="window.print()" style="max-width:420px;">Print Receipt</button>
</div>

<div class="receipt">
  <div class="receipt-header">
    <h2>A&J Alfresco</h2>
    <p>Gatas, Pagadian City</p>
    <p><b>OFFICIAL RECEIPT</b></p>
  </div>

  <div class="receipt-row"><span>Receipt #</span><span><?php echo htmlspecialchars($pay['receipt_number']); ?></span></div>
  <div class="receipt-row"><span>Date</span><span><?php echo formatDate($pay['payment_date']); ?></span></div>
  <div class="receipt-row"><span>Tenant</span><span><?php echo htmlspecialchars($pay['tenant_name']); ?></span></div>
  <div class="receipt-row"><span>Stall</span><span><?php echo htmlspecialchars($pay['stall_number']); ?></span></div>
  <div class="receipt-row"><span>Payment For</span><span><?php echo htmlspecialchars($pay['payment_for_month']); ?></span></div>
  <div class="receipt-row"><span>Method</span><span><?php echo htmlspecialchars($pay['payment_method']); ?></span></div>

  <?php if (!empty($pay['reference_number'])): ?>
    <div class="receipt-row"><span>Reference</span><span><?php echo htmlspecialchars($pay['reference_number']); ?></span></div>
  <?php endif; ?>

  <div class="receipt-row total"><span>Total Paid</span><span><?php echo formatMoney($pay['amount']); ?></span></div>

  <div class="receipt-footer">
    <p>Processed by: <?php echo htmlspecialchars($pay['operator'] ?? 'Admin'); ?></p>
    <p>Thank you for your payment!</p>
  </div>
</div>

</body>
</html>