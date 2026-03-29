<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$initial = strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1));

$unread = notifUnreadCount($tenantId);

$stmt = $conn->prepare("
  SELECT *
  FROM notifications
  WHERE user_id=?
  ORDER BY id DESC
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$notifs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications - Tenant</title>
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
      <li><a href="contract.php"><span class="icon">📄</span> My Contract</a></li>
      <li><a href="payments.php"><span class="icon">💰</span> My Payments</a></li>
      <li>
        <a class="active" href="notifications.php"><span class="icon">🔔</span> Notifications
          <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?>
        </a>
      </li>
      <li><a href="change_password.php"><span class="icon">🔑</span> Change Password</a></li>
      <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <div class="top-bar">
      <h1>Notifications</h1>
      <div class="user-info">
        <div class="avatar"><?php echo $initial; ?></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="card">
        <div class="card-header">
          <h2>All Notifications</h2>
          <form method="POST" action="process_notifications.php" style="margin:0">
            <input type="hidden" name="action" value="mark_all_read">
            <button class="btn btn-warning btn-sm" type="submit">Mark All as Read</button>
          </form>
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
                <tr>
                  <td><?php echo htmlspecialchars($n['title']); ?></td>
                  <td><?php echo htmlspecialchars($n['message']); ?></td>
                  <td><?php echo htmlspecialchars($n['type']); ?></td>
                  <td>
                    <?php if ((int)$n['is_read'] === 1): ?>
                      <span class="status-badge active">read</span>
                    <?php else: ?>
                      <span class="status-badge pending">unread</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo formatDate($n['created_at']); ?></td>
                  <td>
                    <?php if ((int)$n['is_read'] === 0): ?>
                      <form method="POST" action="process_notifications.php" style="display:inline-block;margin:0">
                        <input type="hidden" name="action" value="mark_one_read">
                        <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                        <button class="btn btn-success btn-sm" type="submit">Mark Read</button>
                      </form>
                    <?php else: ?>
                      <small>No action</small>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>

              <?php if ($notifs->num_rows === 0): ?>
                <tr><td colspan="6">No notifications yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
</body>
</html>