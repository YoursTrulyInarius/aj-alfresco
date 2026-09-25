<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$today = new DateTime('today');
$currentMonth = $today->format('Y-m');
$overdueTenants = [];
$overdueResult = $conn->query("SELECT c.start_date, c.monthly_rent, u.full_name, u.business_name, s.stall_number FROM contracts c JOIN users u ON u.id=c.tenant_id JOIN stalls s ON s.id=c.stall_id LEFT JOIN payments p ON p.contract_id=c.id AND p.payment_for_month='$currentMonth' AND p.status='paid' WHERE c.status='active' AND p.id IS NULL ORDER BY u.full_name");

if ($overdueResult) {
    while ($overdue = $overdueResult->fetch_assoc()) {
        $startDate = new DateTime($overdue['start_date']);
        if ($startDate > $today) continue;

        $daysInMonth = (int)$today->format('t');
        $dueDay = min((int)$startDate->format('d'), $daysInMonth);
        $dueDate = DateTime::createFromFormat('Y-m-d', $currentMonth . '-' . str_pad((string)$dueDay, 2, '0', STR_PAD_LEFT));

        if ($dueDate < $today) {
            $overdue['due_date'] = $dueDate;
            $overdue['days_overdue'] = (int)$dueDate->diff($today)->days;
            $overdueTenants[] = $overdue;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Overdue Tenants - A&amp;J Alfresco</title>
  <style>
    body { font-family: Arial, sans-serif; color: #1e293b; margin: 32px; }
    .print-actions { margin-bottom: 24px; }
    button { padding: 9px 16px; border: 0; border-radius: 5px; background: #d63384; color: #fff; cursor: pointer; }
    h1 { margin: 0 0 6px; font-size: 24px; }
    .subtitle { margin: 0 0 22px; color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: left; }
    th { background: #f1f5f9; }
    .amount, .days { text-align: right; }
    .empty { color: #64748b; }
    @media print {
      .print-actions { display: none; }
      body { margin: 0; }
    }
  </style>
</head>
<body>
  <div class="print-actions"><button type="button" onclick="window.print()">Print Overdue List</button></div>
  <h1>Tenants Past Due Date</h1>
  <p class="subtitle">Unpaid active contracts for <?php echo htmlspecialchars($today->format('F Y')); ?> | Printed <?php echo htmlspecialchars($today->format('M d, Y')); ?></p>

  <?php if (!$overdueTenants): ?>
    <p class="empty">No active tenants are past due.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Tenant</th>
          <th>Business</th>
          <th>Stall</th>
          <th>Due Date</th>
          <th>Amount</th>
          <th>Days Overdue</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($overdueTenants as $overdue): ?>
          <tr>
            <td><?php echo htmlspecialchars($overdue['full_name']); ?></td>
            <td><?php echo htmlspecialchars($overdue['business_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($overdue['stall_number']); ?></td>
            <td><?php echo $overdue['due_date']->format('M d, Y'); ?></td>
            <td class="amount"><?php echo formatMoney($overdue['monthly_rent']); ?></td>
            <td class="days"><?php echo (int)$overdue['days_overdue']; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>
