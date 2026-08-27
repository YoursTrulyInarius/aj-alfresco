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
$terminationPending = false;

if ($contract) {
  $admin = $conn->query("SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1")->fetch_assoc();
  if ($admin) {
    $likeMessage = "%Contract ID: " . (int)$contract['id'] . "%";
    $pendingStmt = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND title='Termination Request' AND message LIKE ? AND is_read=0 LIMIT 1");
    $pendingStmt->bind_param("is", $admin['id'], $likeMessage);
    $pendingStmt->execute();
    $terminationPending = $pendingStmt->get_result()->num_rows > 0;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Contract - Tenant</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .contract-card { max-width: 1080px; margin: 0 auto; }
    .contract-card .card-header { padding: 22px 26px; }
    .contract-card .card-body { padding: 26px; }
    .contract-header-copy h2 { margin-bottom: 4px; }
    .contract-header-copy p { color: var(--muted); font-size: 13px; margin: 0; }
    .contract-actions .btn { width: auto !important; white-space: nowrap; }
    .contract-details-grid {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 18px;
      margin-bottom: 26px;
    }
    .contract-detail { min-width: 0; }
    .contract-detail-label {
      display: block;
      color: var(--muted);
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 6px;
    }
    .contract-detail-value { color: var(--secondary); font-size: 15px; font-weight: 700; }
    .contract-detail-note { color: var(--muted); font-size: 13px; margin-top: 4px; }
    .contract-section { border-top: 1px solid var(--border); padding-top: 20px; }
    .contract-section h3 { color: var(--secondary); font-size: 15px; margin: 0 0 8px; }
    .contract-terms { color: var(--muted); line-height: 1.7; white-space: pre-line; margin: 0; }
    .contract-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 18px;
      border-top: 1px solid var(--border);
      margin-top: 22px;
      padding-top: 20px;
    }
    .contract-action-copy { display: flex; flex-direction: column; gap: 3px; }
    .contract-action-copy strong { color: var(--secondary); font-size: 14px; }
    .contract-action-copy span { color: var(--muted); font-size: 12px; }
    .contract-action-buttons { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 10px; }
    .contract-action-buttons form { margin: 0; }
    .contract-action-note { color: var(--muted); font-size: 13px; }
    @media (max-width: 900px) {
      .contract-details-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 560px) {
      .contract-card .card-header,
      .contract-card .card-body { padding: 18px; }
      .contract-details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 12px; }
      .contract-actions { align-items: stretch; }
      .contract-action-buttons { justify-content: stretch; }
      .contract-action-buttons .btn,
      .contract-action-buttons form { flex: 1 1 100%; }
      .contract-action-buttons .btn { width: 100% !important; }
    }
  </style>
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
      <li><a class="active" href="contract.php">My Contract</a></li>
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
        <h1>My Contract</h1>
      </div>
      <div class="user-info">
        <div class="avatar"><?php echo $initial; ?></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <?php if ($flash): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
      <?php endif; ?>

      <div class="card contract-card">
        <div class="card-header" style="justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
          <div class="contract-header-copy">
            <h2>Current Contract</h2>
            <p>Review your rental details and manage the agreement.</p>
          </div>
        </div>
        <div class="card-body">
          <?php if (!$contract): ?>
            <div class="alert alert-danger">No contract found. Please contact admin.</div>
          <?php else: ?>
            <div class="contract-details-grid">
              <div class="contract-detail"><span class="contract-detail-label">Stall</span><strong class="contract-detail-value"><?php echo htmlspecialchars($contract['stall_number']); ?></strong><div class="contract-detail-note"><?php echo htmlspecialchars($contract['location_description']); ?></div></div>
              <div class="contract-detail"><span class="contract-detail-label">Contract status</span><span class="status-badge <?php echo htmlspecialchars($contract['status']); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $contract['status']))); ?></span></div>
              <div class="contract-detail"><span class="contract-detail-label">Rental period</span><strong class="contract-detail-value"><?php echo formatDate($contract['start_date']); ?></strong><div class="contract-detail-note">to <?php echo formatDate($contract['end_date']); ?></div></div>
              <div class="contract-detail"><span class="contract-detail-label">Monthly rent</span><strong class="contract-detail-value"><?php echo formatMoney($contract['monthly_rent']); ?></strong><div class="contract-detail-note">Due every <?php echo date('jS', strtotime($contract['start_date'])); ?></div></div>
              <div class="contract-detail"><span class="contract-detail-label">Security deposit</span><strong class="contract-detail-value"><?php echo formatMoney($contract['deposit_amount']); ?></strong></div>
            </div>

            <div class="contract-section">
              <h3>Contract terms</h3>
              <p class="contract-terms"><?php echo htmlspecialchars($contract['terms'] ?? 'No additional terms recorded.'); ?></p>
            </div>

            <div class="contract-actions">
              <div class="contract-action-copy">
                <strong>Contract actions</strong>
                <?php if ($contract['status'] === 'active' && !$terminationPending): ?>
                  <span>View the agreement or update its status.</span>
                <?php elseif ($contract['status'] === 'active' && $terminationPending): ?>
                  <span>Termination request pending admin approval.</span>
                <?php elseif ($contract['status'] === 'pending_renewal'): ?>
                  <span>Renewal request pending admin review.</span>
                <?php else: ?>
                  <span>No further actions are available for this contract.</span>
                <?php endif; ?>
              </div>
              <div class="contract-action-buttons">
                <a class="btn btn-primary btn-sm" href="../admin/print_contract.php?id=<?php echo (int)$contract['id']; ?>" target="_blank" rel="noopener">View PDF</a>
                <?php if ($contract['status'] === 'active' && !$terminationPending): ?>
                  <form method="POST" action="process_contract.php" class="contract-confirm-form" data-action="renewal">
                    <input type="hidden" name="action" value="request_renewal">
                    <input type="hidden" name="id" value="<?php echo (int)$contract['id']; ?>">
                    <button class="btn btn-success btn-sm" type="submit">Request renewal</button>
                  </form>
                <?php endif; ?>
                <?php if ($contract['status'] === 'active' && !$terminationPending): ?>
                  <form method="POST" action="process_contract.php" class="contract-confirm-form" data-action="termination">
                    <input type="hidden" name="action" value="terminate">
                    <input type="hidden" name="id" value="<?php echo (int)$contract['id']; ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Request termination</button>
                  </form>
                <?php endif; ?>
              </div>
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

document.querySelectorAll('.contract-confirm-form').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    event.preventDefault();

    const isRenewal = form.dataset.action === 'renewal';
    Swal.fire({
      title: isRenewal ? 'Request contract renewal?' : 'Request contract termination?',
      text: isRenewal
        ? 'Your renewal request will be sent to the admin for review.'
        : 'The contract will remain active until the admin approves the request.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: isRenewal ? 'Send request' : 'Send termination request',
      cancelButtonText: 'Cancel',
      confirmButtonColor: isRenewal ? '#059669' : '#dc2626',
      cancelButtonColor: '#64748b',
      reverseButtons: false
    }).then(function (result) {
      if (result.isConfirmed) form.submit();
    });
  });
});
</script>
</body>
</html>