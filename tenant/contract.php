<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$initial = strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1));
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$stmt = $conn->prepare("
  SELECT c.*, s.stall_number, s.location_description
  FROM contracts c
  JOIN stalls s ON s.id = c.stall_id
  WHERE c.tenant_id=?
  ORDER BY c.id DESC
  LIMIT 1
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$unread = notifUnreadCount($tenantId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Contract - Tenant</title>
  <link rel="stylesheet" href="../assets/css/style.css"/>
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
      <li><a href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
      <li><a class="active" href="contract.php"><span class="icon">📄</span> My Contract</a></li>
      <li><a href="make_payment.php"><span class="icon">🧾</span> Make Payment</a></li>
      <li><a href="payments.php"><span class="icon">💰</span> Payment History</a></li>
      <li>
        <a href="notifications.php"><span class="icon">🔔</span> Notifications
          <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?>
        </a>
      </li>
      <li><a href="change_password.php"><span class="icon">🔑</span> Change Password</a></li>
      <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <div class="top-bar">
      <h1>My Contract</h1>
      <div class="user-info">
        <div class="avatar"><?php echo $initial; ?></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <?php if ($flash): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h2>Contract Details</h2>
        </div>
        <div class="card-body" style="line-height:2">
          <?php if (!$contract): ?>
            <div class="alert alert-danger">No contract found. Please contact admin.</div>
          <?php else: ?>
            <div><b>Stall:</b> <?php echo htmlspecialchars($contract['stall_number']); ?></div>
            <div><b>Start Date:</b> <?php echo formatDate($contract['start_date']); ?></div>
            <div><b>End Date:</b> <?php echo formatDate($contract['end_date']); ?></div>
            <div><b>Monthly Rent:</b> <?php echo formatMoney($contract['monthly_rent']); ?></div>
            <div><b>Deposit:</b> <?php echo formatMoney($contract['deposit_amount']); ?></div>
            <div><b>Status:</b>
              <span class="status-badge <?php echo htmlspecialchars($contract['status']); ?>">
                <?php echo htmlspecialchars($contract['status']); ?>
              </span>
            </div>
            <div><b>Terms:</b> <?php echo nl2br(htmlspecialchars($contract['terms'] ?? '')); ?></div>

            <div style="margin-top:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
              <a class="btn btn-primary" href="../admin/print_contract.php?id=<?php echo (int)$contract['id']; ?>" target="_blank" style="text-decoration:none; display:inline-block; padding:10px 20px;">📄 Download PDF Contract</a>
              <?php if ($contract['status'] === 'active'): ?>
                <form method="POST" action="process_contract.php" onsubmit="return confirm('Send renewal request to admin?');" style="margin:0;">
                  <input type="hidden" name="action" value="request_renewal">
                  <input type="hidden" name="id" value="<?php echo (int)$contract['id']; ?>">
                  <button class="btn btn-primary" type="submit" style="padding:10px 20px;">Request Renewal</button>
                </form>
              <?php else: ?>
                <small>Renewal request is only available for active contracts.</small>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>
</body>
</html>