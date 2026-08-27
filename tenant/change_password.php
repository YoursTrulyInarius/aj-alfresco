<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$initial = strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1));

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($newPass !== $confirm) {
        $error = "New password and confirm password do not match.";
    } else {
        // Get current password hash from DB
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=? AND role='tenant' LIMIT 1");
        $stmt->bind_param("i", $tenantId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current, $row['password'])) {
            $error = "Current password is incorrect.";
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE users SET password=? WHERE id=? AND role='tenant'");
            $up->bind_param("si", $hash, $tenantId);
            $up->execute();

            $success = "Password updated successfully!";
        }
    }
}

$unread = notifUnreadCount($tenantId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Change Password - Tenant</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
  <script defer src="../assets/js/main.js"></script>
  <style>
    .password-card { max-width: 820px; margin: 0 auto; }
    .password-card .card-header { padding: 24px 28px; }
    .password-card .card-body { padding: 28px; }
    .password-header-copy h2 { margin-bottom: 5px; }
    .password-header-copy p { color: var(--muted); font-size: 13px; margin: 0; line-height: 1.5; }
    .password-form { display: grid; gap: 20px; }
    .password-section { display: grid; gap: 14px; }
    .password-section-title { color: var(--secondary); font-size: 13px; font-weight: 700; margin: 0; }
    .password-section-subtitle { color: var(--muted); font-size: 12px; margin: -7px 0 2px; }
    .password-new-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .password-form .form-group { margin: 0; }
    .password-form label { color: var(--secondary-mid); font-size: 12px; font-weight: 700; margin-bottom: 7px; }
    .password-actions { border-top: 1px solid var(--border); padding-top: 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .password-tip { color: var(--muted); font-size: 12px; line-height: 1.5; max-width: 330px; }
    .password-submit { width: auto !important; min-width: 180px; }
    @media (max-width: 600px) {
      .password-card .card-header, .password-card .card-body { padding: 18px; }
      .password-new-grid { grid-template-columns: 1fr; }
      .password-actions { align-items: stretch; flex-direction: column; }
      .password-tip { max-width: none; }
      .password-submit { width: 100% !important; }
    }
    .password-field {
      position: relative;
    }
    .password-field input {
      padding-right: 42px;
    }
    .toggle-password-btn {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      cursor: pointer;
      font-size: 16px;
      line-height: 1;
      padding: 0;
      color: #6b7280;
    }
    .toggle-password-btn:hover {
      color: #111827;
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
      <li><a href="contract.php">My Contract</a></li>
      <li><a href="make_payment.php">Make Payment</a></li>
      <li><a href="payments.php">Payment History</a></li>
      <li><a href="notifications.php">Notifications
          <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?>
        </a>
      </li>
      <li><a class="active" href="change_password.php">Change Password</a></li>
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
        <h1>Change Password</h1>
      </div>
      <div class="user-info">
        <div class="avatar"><?php echo $initial; ?></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="card password-card">
        <div class="card-header">
          <div class="password-header-copy">
            <h2>Update Your Password</h2>
            <p>Use a password you do not reuse on other accounts.</p>
          </div>
        </div>
        <div class="card-body">
          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>

          <form method="POST" class="password-form">
            <div class="password-section">
              <h3 class="password-section-title">Verify your identity</h3>
              <p class="password-section-subtitle">Enter your current password to continue.</p>
              <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="password-field">
                  <input type="password" id="current_password" name="current_password" required>
                  <button type="button" class="toggle-password-btn" data-target="current_password" aria-label="Show current password" title="Show password">👁</button>
                </div>
              </div>
            </div>

            <div class="password-section">
              <h3 class="password-section-title">Create a new password</h3>
              <p class="password-section-subtitle">Your password must contain at least 6 characters.</p>
              <div class="password-new-grid">
                <div class="form-group">
                  <label for="new_password">New Password</label>
                  <div class="password-field">
                    <input type="password" id="new_password" name="new_password" required>
                    <button type="button" class="toggle-password-btn" data-target="new_password" aria-label="Show new password" title="Show password">👁</button>
                  </div>
                </div>

                <div class="form-group">
                  <label for="confirm_password">Confirm New Password</label>
                  <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="toggle-password-btn" data-target="confirm_password" aria-label="Show confirm password" title="Show password">👁</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="password-actions">
              <p class="password-tip">After changing your password, use the new password the next time you sign in.</p>
              <button class="btn btn-primary password-submit" type="submit">Save New Password</button>
            </div>
          </form>
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

document.addEventListener('DOMContentLoaded', function () {
  const toggleButtons = document.querySelectorAll('.toggle-password-btn');

  toggleButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      this.textContent = isHidden ? '︶' : '👁';
      this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      this.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
    });
  });
});
</script>
</body>
</html>
