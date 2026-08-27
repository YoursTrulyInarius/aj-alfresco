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

$unread = notifUnreadCount($tenantId);

// Due date calculation (Realtime Target)
$dueTargetJS = "";
if ($contract) {
    $today = new DateTime('today');
    $nextDue = new DateTime($contract['start_date']);
    while ($nextDue <= $today) {
        $nextDue->modify('+1 month');
    }
    $dueTargetJS = $nextDue->format('Y-m-d H:i:s');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tenant Dashboard - A&J Alfresco</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <span class="sidebar-brand-name">A&J Alfresco</span>
        <span class="sidebar-brand-sub">Tenant Panel</span>
      </div>
    </div>
    <p class="sidebar-nav-label">Main Menu</p>
    <ul class="sidebar-menu">
      <li><a class="active" href="dashboard.php">Dashboard</a></li>
      <li><a href="contract.php">My Contract</a></li>
      <li><a href="make_payment.php">Make Payment</a></li>
      <li><a href="payments.php">Payment History</a></li>
      <li>
        <a href="notifications.php">Notifications
          <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?>
        </a>
      </li>
      <li><a href="change_password.php">Change Password</a></li>
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
        <h1>Welcome, <?php echo htmlspecialchars($tenantInfo['full_name']); ?>!</h1>
      </div>
      <div class="user-info">
        <div class="header-stats" style="text-align: right; margin-right: 20px; line-height: 1.2;">
          <div style="font-size: 11px; color: #666; font-weight: 600;">Security Deposit: <?php echo $contract ? formatMoney($contract['deposit_amount']) : '₱0.00'; ?></div>
          <div style="font-size: 14px; color: #d63384; font-weight: 700;">Rent: <?php echo $contract ? formatMoney($contract['monthly_rent']) : '₱0.00'; ?></div>
        </div>
        <div class="avatar"></div>
        <span>Tenant</span>
      </div>
    </div>

    <div class="content">

      <div class="stats-grid">
        <div class="stat-card" style="position: relative; overflow: hidden;">
          <div class="stat-icon blue">⏰</div>
          <div class="stat-info">
            <h3 id="countdownTimer">--:--:--</h3>
            <p>Real-time until rent due</p>
          </div>
          <div style="position:absolute; bottom:0; left:0; height:4px; background:#007bff; width:100%; opacity:0.3;"></div>
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

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-top: 25px;">
        <!-- CONTRACT SUMMARY -->
        <div class="card">
          <div class="card-header">
            <h2>Current Contract</h2>
            <div style="display:flex; gap:10px;">
              <a class="btn btn-success btn-sm" href="make_payment.php" style="width:auto;">Pay Online</a>
              <?php if ($contract): ?>
                <a class="btn btn-primary btn-sm" href="../admin/print_contract.php?id=<?php echo (int)$contract['id']; ?>" target="_blank" style="width:auto;">Print PDF</a>
              <?php endif; ?>
            </div>
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
                <div><b>Due Day:</b> Every <?php echo date('jS', strtotime($contract['start_date'])); ?> of the month</div>
                <div><b>Monthly Rent:</b> <?php echo formatMoney($contract['monthly_rent']); ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RECENT PAYMENTS -->
        <div class="card">
          <div class="card-header">
            <h2>Recent Payments</h2>
            <a class="btn btn-primary btn-sm" href="payments.php" style="width:auto;">History</a>
          </div>
          <div class="card-body table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Amount</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php while($p = $recentPayments->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo date('M Y', strtotime($p['payment_for_month'].'-01')); ?></td>
                    <td><?php echo formatMoney($p['amount']); ?></td>
                    <td><?php echo formatDate($p['payment_date']); ?></td>
                  </tr>
                <?php endwhile; ?>
                <?php if ($recentPayments->num_rows === 0): ?>
                  <tr><td colspan="3">No payments recorded.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
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

// REAL-TIME COUNTDOWN LOGIC
const targetDateStr = "<?php echo $dueTargetJS; ?>";
if (targetDateStr) {
    const targetDate = new Date(targetDateStr).getTime();
    
    const x = setInterval(function() {
        const now = new Date().getTime();
        const distance = targetDate - now;

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        if (distance < 0) {
            clearInterval(x);
            document.getElementById("countdownTimer").innerHTML = "PAYMENT DUE";
            document.getElementById("countdownTimer").style.color = "red";
        } else {
            document.getElementById("countdownTimer").innerHTML = days + "d " + hours + "h " + minutes + "m " + seconds + "s";
        }
    }, 1000);
} else {
    document.getElementById("countdownTimer").innerHTML = "N/A";
}

// TOAST NOTIFICATIONS FOR UNREAD
<?php if ($unread > 0): ?>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: 'You have <?php echo $unread; ?> unread notifications!',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
<?php endif; ?>
</script>
</body>
</html>