<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$adminId = (int)$_SESSION['user_id'];
$unread = notifUnreadCount($adminId);

$filterType = sanitize($_GET['filter_type'] ?? '');

$query = "
  SELECT n.*
  FROM notifications n
  WHERE n.user_id = ?
";
if ($filterType === 'payment') {
    $query .= " AND n.type IN ('payment', 'due_date')";
} elseif ($filterType === 'contract') {
    $query .= " AND n.type = 'contract_expiry'";
} elseif ($filterType === 'unread') {
  $query .= " AND n.is_read = 0";
}
$query .= " ORDER BY n.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$notifs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .notif-msg { cursor: pointer; transition: 0.2s; position: relative; }
    .notif-msg:hover { color: var(--accent); }
  </style>
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
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="tenants.php">Tenants</a></li>
      <li><a href="stalls.php">Stalls</a></li>
      <li><a href="contracts.php">Contracts</a></li>
      <li><a href="payments.php">Payments</a></li>
      <li><a class="active" href="notifications.php">Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
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
        <h1>Notifications</h1>
      </div>
      <div class="user-info">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="card">
        <div class="card-header" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
          <h2>Admin Alerts</h2>
          <div style="display:flex; gap:15px; align-items:center;">
            <form method="GET" style="margin:0; display:flex; align-items:center; gap:8px;">
              <label for="filter_type" style="font-size:13.5px; font-weight:600; color:#475569;">Filter:</label>
              <select name="filter_type" id="filter_type" onchange="this.form.submit()" style="padding:6px 12px; border:1px solid #ddd; border-radius:8px; font-size:13.5px;">
                <option value="">All Notifications</option>
                <option value="unread" <?php echo $filterType === 'unread' ? 'selected' : ''; ?>>Unread Notifications</option>
                <option value="payment" <?php echo $filterType === 'payment' ? 'selected' : ''; ?>>Payment Alerts</option>
                <option value="contract" <?php echo $filterType === 'contract' ? 'selected' : ''; ?>>Contract Alerts</option>
              </select>
            </form>
            <form method="POST" action="process_notifications.php" style="margin:0">
              <input type="hidden" name="action" value="mark_all_read">
              <button class="btn btn-warning btn-sm" type="submit">Mark All Read</button>
            </form>
          </div>
        </div>

        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>Message</th>
                <th>Type</th>
                <th>Status</th>
                <th>Date</th>
                <th style="width:160px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while($n = $notifs->fetch_assoc()): ?>
                <tr class="<?php echo $n['is_read'] ? '' : 'unread-row'; ?>">
                  <td><strong><?php echo htmlspecialchars($n['title']); ?></strong></td>
                  <td class="notif-msg" onclick="showNotif('<?php echo addslashes($n['title']); ?>', '<?php echo addslashes($n['message']); ?>')">
                    <?php echo htmlspecialchars($n['message']); ?>
                    <div style="font-size: 10px; color: #999; margin-top: 4px;">📂 Click to view full details</div>
                  </td>
                  <td><span class="status-badge <?php echo htmlspecialchars($n['type']); ?>"><?php echo htmlspecialchars($n['type']); ?></span></td>
                  <td>
                    <?php 
                    $type = $n['type'];
                    $isRead = (int)$n['is_read'] === 1;
                    
                    if ($type === 'contract_expiry') {
                        $badgeClass = 'overdue';
                      $labelText = $isRead ? 'read' : 'unread';
                    } elseif ($type === 'payment' || $type === 'due_date') {
                      $badgeClass = $isRead ? 'paid' : 'pending';
                      $labelText = $isRead ? 'read' : 'unread';
                    } else {
                      $badgeClass = $isRead ? 'active' : 'pending';
                      $labelText = $isRead ? 'read' : 'unread';
                    }
                    ?>
                    <span class="status-badge <?php echo $badgeClass; ?>">
                      <?php echo $labelText; ?>
                    </span>
                  </td>
                  <td><?php echo formatDate($n['created_at']); ?></td>
                  <td>
                    <?php if ((int)$n['is_read'] === 0): ?>
                      <form method="POST" action="process_notifications.php" style="display:inline-block;margin:0">
                        <input type="hidden" name="action" value="mark_one_read">
                        <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                        <button class="btn btn-success btn-sm" type="submit" style="width:auto; padding: 4px 8px;">Mark Read</button>
                      </form>
                    <?php else: ?>
                      <small>No action</small>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>

              <?php if ($notifs->num_rows === 0): ?>
                <tr><td colspan="6">No notifications found for your account.</td></tr>
              <?php endif; ?>
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

function showNotif(title, msg) {
  Swal.fire({
    title: title,
    text: msg,
    icon: 'info',
    confirmButtonText: 'Okay',
    confirmButtonColor: '#ff2d55',
    customClass: {
      popup: 'modal-pop-design'
    }
  });
}

function showNotif(title, msg) {
  Swal.fire({
    title: title,
    text: msg,
    icon: 'info',
    confirmButtonText: 'Great!',
    confirmButtonColor: '#ff2d55',
    customClass: {
      popup: 'modal-pop-design'
    }
  });
}
</script>
</body>
</html>
