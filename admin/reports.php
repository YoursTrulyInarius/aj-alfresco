<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Totals
$todayTotal = (float)$conn->query("SELECT IFNULL(SUM(amount),0) t FROM payments WHERE payment_date = CURDATE()")->fetch_assoc()['t'];
$weekTotal  = (float)$conn->query("SELECT IFNULL(SUM(amount),0) t FROM payments WHERE YEARWEEK(payment_date, 1)=YEARWEEK(CURDATE(), 1)")->fetch_assoc()['t'];
$monthTotal = (float)$conn->query("SELECT IFNULL(SUM(amount),0) t FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())")->fetch_assoc()['t'];
$yearTotal  = (float)$conn->query("SELECT IFNULL(SUM(amount),0) t FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE())")->fetch_assoc()['t'];

// Monthly breakdown (current year)
$monthly = $conn->query("
  SELECT DATE_FORMAT(payment_date,'%Y-%m') ym, SUM(amount) total
  FROM payments
  WHERE YEAR(payment_date)=YEAR(CURDATE())
  GROUP BY ym
  ORDER BY ym DESC
");

// Recent payments
$recent = $conn->query("
  SELECT p.receipt_number, p.amount, p.payment_date, u.full_name tenant_name, s.stall_number
  FROM payments p
  JOIN users u ON u.id=p.tenant_id
  JOIN contracts c ON c.id=p.contract_id
  JOIN stalls s ON s.id=c.stall_id
  ORDER BY p.id DESC
  LIMIT 20
");

$adminId = (int)$_SESSION['user_id'];
$unread = notifUnreadCount($adminId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=6"/>
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="logo">🏪</span>
      <h2>A&J Alfresco</h2>
      <p>Admin Panel</p>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
      <li><a href="tenants.php"><span class="icon">👥</span> Tenants</a></li>
      <li><a href="stalls.php"><span class="icon">🏬</span> Stalls</a></li>
      <li><a href="contracts.php"><span class="icon">📄</span> Contracts</a></li>
      <li><a href="payments.php"><span class="icon">💰</span> Payments</a></li>
      <li><a href="notifications.php"><span class="icon">🔔</span> Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
      <li><a class="active" href="reports.php"><span class="icon">📈</span> Reports</a></li>
      <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="top-bar">
      <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
        <h1>Reports</h1>
      </div>
      <div class="user-info">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon blue">📅</div><div class="stat-info"><h3><?php echo formatMoney($todayTotal); ?></h3><p>Today</p></div></div>
        <div class="stat-card"><div class="stat-icon green">🗓️</div><div class="stat-info"><h3><?php echo formatMoney($weekTotal); ?></h3><p>This Week</p></div></div>
        <div class="stat-card"><div class="stat-icon purple">🧾</div><div class="stat-info"><h3><?php echo formatMoney($monthTotal); ?></h3><p>This Month</p></div></div>
        <div class="stat-card"><div class="stat-icon blue">🏆</div><div class="stat-info"><h3><?php echo formatMoney($yearTotal); ?></h3><p>This Year</p></div></div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Monthly Summary (This Year)</h2></div>
        <div class="card-body table-responsive">
          <table>
            <thead><tr><th>Month</th><th>Total</th></tr></thead>
            <tbody>
              <?php while($m = $monthly->fetch_assoc()): ?>
                <tr><td><?php echo htmlspecialchars($m['ym']); ?></td><td><?php echo formatMoney($m['total']); ?></td></tr>
              <?php endwhile; ?>
              <?php if ($monthly->num_rows===0): ?><tr><td colspan="2">No data yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Recent Payments</h2></div>
        <div class="card-body table-responsive">
          <table>
            <thead><tr><th>Receipt</th><th>Tenant</th><th>Stall</th><th>Date</th><th>Amount</th></tr></thead>
            <tbody>
              <?php while($r = $recent->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['receipt_number']); ?></td>
                  <td><?php echo htmlspecialchars($r['tenant_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['stall_number']); ?></td>
                  <td><?php echo formatDate($r['payment_date']); ?></td>
                  <td><?php echo formatMoney($r['amount']); ?></td>
                </tr>
              <?php endwhile; ?>
              <?php if ($recent->num_rows===0): ?><tr><td colspan="5">No payments yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('show');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
</script>
</body>
</html>