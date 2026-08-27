<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$tenantCountRes = $conn->query("SELECT COUNT(*) c FROM users WHERE role='tenant'");
$tenantCount = $tenantCountRes ? (int)$tenantCountRes->fetch_assoc()['c'] : 0;

$stallAvailableRes = $conn->query("SELECT COUNT(*) c FROM stalls WHERE status='available'");
$stallAvailable = $stallAvailableRes ? (int)$stallAvailableRes->fetch_assoc()['c'] : 0;

$activeContractsRes = $conn->query("SELECT COUNT(*) c FROM contracts WHERE status='active'");
$activeContracts = $activeContractsRes ? (int)$activeContractsRes->fetch_assoc()['c'] : 0;

$adminId = (int)$_SESSION['user_id'];
$unread = notifUnreadCount($adminId);
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$overdueTenants = [];
$today = new DateTime('today');
$currentMonth = $today->format('Y-m');
$overdueResult = $conn->query("SELECT c.id, c.start_date, c.monthly_rent, u.full_name, u.business_name, s.stall_number FROM contracts c JOIN users u ON u.id=c.tenant_id JOIN stalls s ON s.id=c.stall_id WHERE c.status='active' ORDER BY u.full_name");
if ($overdueResult) {
  while ($overdue = $overdueResult->fetch_assoc()) {
    $startDate = new DateTime($overdue['start_date']);
    $daysInMonth = (int)$today->format('t');
    $dueDay = min((int)$startDate->format('d'), $daysInMonth);
    $dueDate = DateTime::createFromFormat('Y-m-d', $currentMonth . '-' . str_pad((string)$dueDay, 2, '0', STR_PAD_LEFT));

    $paidStmt = $conn->prepare("SELECT id FROM payments WHERE contract_id=? AND payment_for_month=? AND status='paid' LIMIT 1");
    $paidStmt->bind_param("is", $overdue['id'], $currentMonth);
    $paidStmt->execute();

    if ($dueDate < $today && $paidStmt->get_result()->num_rows === 0) {
      $overdue['due_date'] = $dueDate;
      $overdue['days_overdue'] = (int)$dueDate->diff($today)->days;
      $overdueTenants[] = $overdue;
    }
  }
}

$terminationRequests = [];
$requestResult = $conn->query("SELECT id, message, created_at FROM notifications WHERE user_id=$adminId AND title='Termination Request' AND is_read=0 ORDER BY id DESC");
if ($requestResult) {
  while ($request = $requestResult->fetch_assoc()) {
    if (!preg_match('/Contract ID:\s*(\d+)/', $request['message'], $matches)) continue;

    $contractId = (int)$matches[1];
    $contractStmt = $conn->prepare("SELECT c.id, c.start_date, c.end_date, c.monthly_rent, u.full_name, u.business_name, s.stall_number FROM contracts c JOIN users u ON u.id=c.tenant_id JOIN stalls s ON s.id=c.stall_id WHERE c.id=? LIMIT 1");
    $contractStmt->bind_param("i", $contractId);
    $contractStmt->execute();
    $contract = $contractStmt->get_result()->fetch_assoc();

    if ($contract) {
      $terminationRequests[] = [
        'notification_id' => (int)$request['id'],
        'contract_id' => $contractId,
        'created_at' => $request['created_at'],
        'contract' => $contract
      ];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard - A&J Alfresco</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
</head>
<body>
  <div class="dashboard">
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <span class="sidebar-brand-name">A&J Alfresco</span>
          <span class="sidebar-brand-sub">Admin Panel</span>
        </div>
      </div>
      <p class="sidebar-nav-label">Main Menu</p>
      <ul class="sidebar-menu">
        <li><a class="active" href="dashboard.php">Dashboard</a></li>
        <li><a href="tenants.php">Tenants</a></li>
        <li><a href="stalls.php">Stalls</a></li>
        <li><a href="contracts.php">Contracts</a></li>
        <li><a href="payments.php">Payments</a></li>
        <li><a href="notifications.php">Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
        <li><a href="reports.php">Reports</a></li>
      </ul>
      <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
      </div>
    </aside>

    <main class="main-content">
      <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
      <div class="top-bar">
        <div class="header-left">
          <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
          <h1>Dashboard Overview</h1>
        </div>
        <div class="user-info">
          <div class="avatar"></div>
          <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
      </div>

      <div class="content">
        <?php if ($flash): ?>
          <div class="alert alert-success" style="margin-bottom:20px;"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon blue">👥</div>
            <div class="stat-info">
              <h3><?php echo $tenantCount; ?></h3>
              <p>Total Tenants</p>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon green">🏬</div>
            <div class="stat-info">
              <h3><?php echo $stallAvailable; ?></h3>
              <p>Available Stalls</p>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon purple">📄</div>
            <div class="stat-info">
              <h3><?php echo $activeContracts; ?></h3>
              <p>Active Contracts</p>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:24px;">
          <div class="card-header">
            <div>
              <h2>Tenants Past Due Date</h2>
              <p style="color:var(--muted); font-size:13px; margin-top:4px;">Active contracts with no paid record for the current rental month.</p>
            </div>
            <?php if ($overdueTenants): ?>
              <span class="status-badge overdue"><?php echo count($overdueTenants); ?> overdue</span>
            <?php endif; ?>
          </div>
          <div class="card-body table-responsive">
            <?php if (!$overdueTenants): ?>
              <p style="color:var(--muted); margin:0;">No active tenants are past due.</p>
            <?php else: ?>
              <table>
                <thead>
                  <tr>
                    <th>Tenant</th>
                    <th>Stall</th>
                    <th>Due date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($overdueTenants as $overdue): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($overdue['full_name']); ?></strong><?php if (!empty($overdue['business_name'])): ?><small style="display:block; margin-top:3px;"><?php echo htmlspecialchars($overdue['business_name']); ?></small><?php endif; ?></td>
                      <td><?php echo htmlspecialchars($overdue['stall_number']); ?></td>
                      <td><?php echo $overdue['due_date']->format('M d, Y'); ?></td>
                      <td><?php echo formatMoney($overdue['monthly_rent']); ?></td>
                      <td><span class="status-badge overdue"><?php echo $overdue['days_overdue']; ?> days overdue</span></td>
                      <td><a class="btn btn-primary btn-sm" href="payments.php?search=<?php echo urlencode($overdue['full_name']); ?>" style="width:auto;">View payments</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="margin-top:24px;">
          <div class="card-header">
            <div>
              <h2>Termination Requests</h2>
              <p style="color:var(--muted); font-size:13px; margin-top:4px;">Review tenant requests before changing contract status.</p>
            </div>
            <?php if (count($terminationRequests) > 0): ?>
              <span class="status-badge pending"><?php echo count($terminationRequests); ?> pending</span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if (!$terminationRequests): ?>
              <p style="color:var(--muted); margin:0;">No termination requests require review.</p>
            <?php else: ?>
              <div style="display:grid; gap:14px;">
                <?php foreach ($terminationRequests as $request): $requestContract = $request['contract']; ?>
                  <div style="border:1px solid var(--border); border-radius:10px; padding:16px; display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap;">
                    <div>
                      <strong style="color:var(--secondary); display:block; margin-bottom:5px;"><?php echo htmlspecialchars($requestContract['full_name']); ?><?php if (!empty($requestContract['business_name'])): ?> <span style="color:var(--muted); font-weight:500;">(<?php echo htmlspecialchars($requestContract['business_name']); ?>)</span><?php endif; ?></strong>
                      <span style="color:var(--muted); font-size:13px;">Contract #<?php echo $request['contract_id']; ?> | Stall <?php echo htmlspecialchars($requestContract['stall_number']); ?> | <?php echo formatDate($requestContract['start_date']); ?> to <?php echo formatDate($requestContract['end_date']); ?></span>
                      <small style="color:var(--muted); display:block; margin-top:5px;">Requested <?php echo formatDate($request['created_at']); ?></small>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                      <form method="POST" action="process_notifications.php" style="margin:0;" onsubmit="return confirm('Approve this termination request? The stall will become available.');">
                        <input type="hidden" name="action" value="approve_termination">
                        <input type="hidden" name="notification_id" value="<?php echo $request['notification_id']; ?>">
                        <button class="btn btn-success btn-sm" type="submit" style="width:auto;">Approve</button>
                      </form>
                      <form method="POST" action="process_notifications.php" style="margin:0;" onsubmit="return confirm('Reject this termination request?');">
                        <input type="hidden" name="action" value="reject_termination">
                        <input type="hidden" name="notification_id" value="<?php echo $request['notification_id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit" style="width:auto;">Reject</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
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