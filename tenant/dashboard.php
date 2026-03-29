<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
tenantAutoReminders($tenantId); // auto-create due/expiry notifications
$toastItems = getUnreadNotifications($tenantId, 3); // for popup toasts

$initial = strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1));

// Tenant Details
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$tenantInfo = $stmt->get_result()->fetch_assoc();

// Latest contract (active first)
$stmt = $conn->prepare("
  SELECT c.*, s.stall_number, s.location_description
  FROM contracts c
  JOIN stalls s ON s.id = c.stall_id
  WHERE c.tenant_id=?
  ORDER BY (c.status='active') DESC, c.id DESC
  LIMIT 1
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

// Recent payments
$stmt2 = $conn->prepare("
  SELECT p.*
  FROM payments p
  WHERE p.tenant_id=?
  ORDER BY p.payment_date DESC, p.id DESC
  LIMIT 5
");
$stmt2->bind_param("i", $tenantId);
$stmt2->execute();
$recentPayments = $stmt2->get_result();

// Notifications (latest 5)
$stmt3 = $conn->prepare("
  SELECT *
  FROM notifications
  WHERE user_id=?
  ORDER BY id DESC
  LIMIT 5
");
$stmt3->bind_param("i", $tenantId);
$stmt3->execute();
$notifs = $stmt3->get_result();

$unread = notifUnreadCount($tenantId);

// Due date logic (1st of month)
$today = new DateTime();
$nextDue = new DateTime($today->format('Y-m') . '-01');
if ((int)$today->format('d') > 1) $nextDue->modify('+1 month');
$daysToDue = (int)$today->diff($nextDue)->days;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tenant Dashboard - A&J Alfresco</title>
  <link rel="stylesheet" href="../assets/css/style.css"/>
  <script defer src="../assets/js/main.js"></script>
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="logo">🏪</span>
      <h2>A&J Alfresco</h2>
      <p>Tenant Panel</p>
    </div>
    <ul class="sidebar-menu">
      <li><a class="active" href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
      <li><a href="contract.php"><span class="icon">📄</span> My Contract</a></li>
      <li><a href="payments.php"><span class="icon">💰</span> My Payments</a></li>
      <li><a href="notifications.php"><span class="icon">🔔</span> Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
      <li><a href="change_password.php"><span class="icon">🔑</span> Change Password</a></li>
      <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="top-bar">
      <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
        <h1>Welcome, <?php echo htmlspecialchars($tenantInfo['full_name']); ?>!</h1>
      </div>
      <div class="user-info">
        <div style="text-align: right; margin-right: 20px; line-height: 1.2;">
          <div style="font-size: 11px; color: #666; font-weight: 600;">Security Deposit: <?php echo $contract ? formatMoney($contract['deposit_amount']) : '₱0.00'; ?></div>
          <div style="font-size: 14px; color: #d63384; font-weight: 700;">Rent: <?php echo $contract ? formatMoney($contract['monthly_rent']) : '₱0.00'; ?></div>
        </div>
        <div class="avatar"></div>
        <span>Tenant</span>
      </div>
    </div>

    <div class="content">

      <!-- TENANT PROFILE SUMMARY -->
      <div class="card" style="margin-bottom: 25px;">
        <div class="card-body" style="display: flex; gap: 40px; align-items: center; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 250px;">
            <h2 style="color: #d63384; margin-bottom: 5px;"><?php echo htmlspecialchars($tenantInfo['business_name']); ?></h2>
            <p style="color: #666; margin: 0;"><?php echo htmlspecialchars($tenantInfo['business_type']); ?> — Serving Quality Food</p>
          </div>
          <div style="flex: 1; min-width: 250px;">
            <p style="margin: 0;"><strong>📧 Email:</strong> <?php echo htmlspecialchars($tenantInfo['email']); ?></p>
            <p style="margin: 0;"><strong>📞 Phone:</strong> <?php echo htmlspecialchars($tenantInfo['phone']); ?></p>
          </div>
          <div style="flex: 1; min-width: 250px;">
            <p style="margin: 0;"><strong>📍 Address:</strong></p>
            <p style="margin: 0; color: #666;"><?php echo htmlspecialchars($tenantInfo['address']); ?></p>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">⏰</div>
          <div class="stat-info">
            <h3><?php echo $daysToDue; ?></h3>
            <p>Days until rent due (1st)</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon purple">💰</div>
          <div class="stat-info">
            <h3><?php echo $contract ? formatMoney($contract['deposit_amount']) : '₱0.00'; ?></h3>
            <p>Your Security Deposit</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">🏪</div>
          <div class="stat-info">
            <h3><?php echo $contract ? htmlspecialchars($contract['stall_number']) : 'No Stall'; ?></h3>
            <p>Assigned Stall #</p>
          </div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
        <!-- CONTRACT SUMMARY -->
        <div class="card">
          <div class="card-header">
            <h2>Current Contract</h2>
            <?php if ($contract): ?>
              <a class="btn btn-primary btn-sm" href="../admin/print_contract.php?id=<?php echo (int)$contract['id']; ?>" target="_blank">Print Copy</a>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if (!$contract): ?>
              <div class="alert alert-danger">No active contract found. Please visit our office.</div>
            <?php else: ?>
              <div style="line-height:2">
                <div><b>Stall Number:</b> <?php echo htmlspecialchars($contract['stall_number']); ?></div>
                <div style="font-size: 0.9em; color: #666; margin-left: 20px;"><?php echo htmlspecialchars($contract['location_description']); ?></div>
                <hr style="border:none; border-top:1px solid #eee;">
                <div><b>Period:</b> <?php echo formatDate($contract['start_date']); ?> to <?php echo formatDate($contract['end_date']); ?></div>
                <div><b>Monthly Rent:</b> <?php echo formatMoney($contract['monthly_rent']); ?></div>
                <div><b>Status:</b>
                  <span class="status-badge <?php echo htmlspecialchars($contract['status']); ?>">
                    <?php echo htmlspecialchars($contract['status']); ?>
                  </span>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RECENT PAYMENTS -->
        <div class="card">
          <div class="card-header">
            <h2>Recent Payments</h2>
            <a class="btn btn-success btn-sm" href="payments.php">View All</a>
          </div>
          <div class="card-body table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Amount</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php while($p = $recentPayments->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo date('M Y', strtotime($p['payment_for_month'].'-01')); ?></td>
                    <td><?php echo formatMoney($p['amount']); ?></td>
                    <td><?php echo formatDate($p['payment_date']); ?></td>
                    <td>
                      <a class="btn btn-success btn-sm" target="_blank" href="receipt.php?id=<?php echo (int)$p['id']; ?>">Receipt</a>
                    </td>
                  </tr>
                <?php endwhile; ?>
                <?php if ($recentPayments->num_rows === 0): ?>
                  <tr><td colspan="4">No payments found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

</script>
</body>
</html>