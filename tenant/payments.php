<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$initial = strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1));

$searchLabel = sanitize($_GET['search'] ?? '');
$filterMonth = sanitize($_GET['month'] ?? '');

$query = "
  SELECT p.*, s.stall_number
  FROM payments p
  JOIN contracts c ON c.id = p.contract_id
  JOIN stalls s ON s.id = c.stall_id
  WHERE p.tenant_id = ?
";

if ($searchLabel) {
    $query .= " AND p.receipt_number LIKE '%$searchLabel%'";
}
if ($filterMonth) {
    $query .= " AND p.payment_for_month = '$filterMonth'";
}

$query .= " ORDER BY p.payment_date DESC, p.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$payments = $stmt->get_result();

$unread = notifUnreadCount($tenantId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Payments - Tenant</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
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
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="contract.php">My Contract</a></li>
      <li><a href="make_payment.php">Make Payment</a></li>
      <li><a class="active" href="payments.php">Payment History</a></li>
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
        <h1>Payment History</h1>
      </div>
      <div class="user-info">
        <div class="avatar"><?php echo $initial; ?></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <?php 
      // Messages handles by SweetAlert below
      ?>

      <div class="card">
        <div class="card-header" style="justify-content: space-between; align-items: center;">
          <h2>Filter Records</h2>
          <div style="display:flex; gap:15px; align-items:center;">
            <a class="btn btn-success btn-sm" href="make_payment.php" style="white-space:nowrap;">Pay Online</a>
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
              <input type="text" name="search" placeholder="Search Receipt #..." value="<?php echo htmlspecialchars($searchLabel); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px;">
              <input type="month" name="month" value="<?php echo htmlspecialchars($filterMonth); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px;">
              <button type="submit" class="btn btn-primary btn-sm" style="width:auto;">Search</button>
            </form>
          </div>
        </div>
        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Receipt #</th>
                <th>Month Covered</th>
                <th>Amount</th>
                <th>Payment Date</th>
                <th>Method</th>
                <th style="width:160px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while($p = $payments->fetch_assoc()): ?>
                <tr>
                  <td><strong>#<?php echo htmlspecialchars($p['receipt_number']); ?></strong></td>
                  <td><?php echo date('F Y', strtotime($p['payment_for_month'] . '-01')); ?></td>
                  <td><?php echo formatMoney($p['amount']); ?></td>
                  <td><?php echo formatDate($p['payment_date']); ?></td>
                  <td>
                    <span class="status-badge paid"><?php echo strtoupper($p['payment_method']); ?></span>
                  </td>
                  <td>
                    <a class="btn btn-success btn-sm" target="_blank" href="receipt.php?id=<?php echo (int)$p['id']; ?>">
                      View Receipt
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>

              <?php if ($payments->num_rows === 0): ?>
                <tr><td colspan="6">No payments match your search.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['flash_success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Payment Successful!',
            text: '<?php echo $_SESSION['flash_success']; ?>',
            confirmButtonColor: '#d63384'
        });
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Action Failed',
            text: '<?php echo $_SESSION['flash_error']; ?>',
            confirmButtonColor: '#d63384'
        });
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
});

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('show');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
</script>
</body>
</html>
