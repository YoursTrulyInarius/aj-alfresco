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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard - A&J Alfresco</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=5"/>
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
        <li><a class="active" href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
        <li><a href="tenants.php"><span class="icon">👥</span> Tenants</a></li>
        <li><a href="stalls.php"><span class="icon">🏬</span> Stalls</a></li>
        <li><a href="contracts.php"><span class="icon">📄</span> Contracts</a></li>
        <li><a href="payments.php"><span class="icon">💰</span> Payments</a></li>
        <li><a href="notifications.php"><span class="icon">🔔</span> Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
        <li><a href="reports.php"><span class="icon">📈</span> Reports</a></li>
        <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
      </ul>
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