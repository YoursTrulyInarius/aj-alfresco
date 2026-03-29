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
  <link rel="stylesheet" href="../assets/css/style.css"/>
  <script defer src="../assets/js/main.js"></script>
  <style>
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
      <span class="logo">🏪</span>
      <h2>A&J Alfresco</h2>
      <p>Tenant Panel</p>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
      <li><a href="contract.php"><span class="icon">📄</span> My Contract</a></li>
      <li><a href="payments.php"><span class="icon">💰</span> My Payments</a></li>
      <li>
        <a href="notifications.php"><span class="icon">🔔</span> Notifications
          <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?>
        </a>
      </li>
      <li><a class="active" href="change_password.php"><span class="icon">🔑</span> Change Password</a></li>
      <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <div class="top-bar">
      <h1>Change Password</h1>
      <div class="user-info">
        <div class="avatar"><?php echo $initial; ?></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="card">
        <div class="card-header">
          <h2>Update Your Password</h2>
        </div>
        <div class="card-body">
          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>

          <form method="POST">
            <div class="form-group">
              <label>Current Password</label>
              <div class="password-field">
                <input type="password" id="current_password" name="current_password" required>
                <button type="button" class="toggle-password-btn" data-target="current_password" aria-label="Show current password" title="Show password">👁</button>
              </div>
            </div>

            <div class="form-group">
              <label>New Password</label>
              <div class="password-field">
                <input type="password" id="new_password" name="new_password" required>
                <button type="button" class="toggle-password-btn" data-target="new_password" aria-label="Show new password" title="Show password">👁</button>
              </div>
            </div>

            <div class="form-group">
              <label>Confirm New Password</label>
              <div class="password-field">
                <input type="password" id="confirm_password" name="confirm_password" required>
                <button type="button" class="toggle-password-btn" data-target="confirm_password" aria-label="Show confirm password" title="Show password">👁</button>
              </div>
            </div>

            <button class="btn btn-primary" type="submit">Save New Password</button>
          </form>

          <div style="margin-top:12px;">
            <small>Tip: After changing, use the new password next time you login.</small>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script>
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
