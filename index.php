<?php
require_once __DIR__ . '/includes/functions.php';

// If user is already logged in, we let them know instead of just silently bouncing them.
$alreadyIn = isLoggedIn();
$currentUser = $alreadyIn ? $_SESSION['full_name'] : '';
$currentRole = $alreadyIn ? $_SESSION['role'] : '';

$flash_type = '';
$flash_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_unset();
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, full_name, email, password, role, status FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $flash_type = "error";
        $flash_msg = "Account not found.";
    } elseif ($user['status'] !== 'active') {
        $flash_type = "warning";
        $flash_msg = "Your account is currently inactive. Please contact Admin.";
    } elseif (!password_verify($password, $user['password'])) {
        $flash_type = "error";
        $flash_msg = "The password you entered is incorrect.";
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') redirect('admin/dashboard.php');
        redirect('tenant/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>A&J Alfresco - Login</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <!-- SweetAlert2 CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="login-page">
  <div class="login-box">
    <div class="logo">🏪</div>
    <h1>A&J Alfresco</h1>
    <p class="subtitle">Rental Management System</p>

    <?php if ($alreadyIn): ?>
      <div class="alert alert-info" style="border-left: 4px solid #17a2b8; background: #e1f5fe; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
          <strong>Already logged in:</strong> <?php echo htmlspecialchars($currentUser); ?> 
          <a href="<?php echo htmlspecialchars($currentRole); ?>/logout.php" style="display:inline-block; margin-top:10px; color:#d63384; font-weight:bold;">Logout First?</a>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required placeholder="" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
      </div>

      <div class="form-group">
        <label>Secret Password</label>
        <div class="password-wrap">
          <input id="passwordInput" type="password" name="password" required placeholder="Enter your password" />
          <button class="pw-toggle" type="button" onclick="togglePassword()">Show</button>
        </div>
      </div>

      <button class="btn btn-primary" type="submit" style="width:100%; padding:12px; margin-top:10px;">Login Now</button>
    </form>
    
    <div class="login-footer">
        &copy; <?php echo date('Y'); ?> A&J Alfresco
    </div>
  </div>

  <script>
  function togglePassword(){
    const input = document.getElementById('passwordInput');
    const btn = document.querySelector('.pw-toggle');
    input.type = (input.type === 'password') ? 'text' : 'password';
    btn.textContent = (input.type === 'password') ? 'Show' : 'Hide';
  }

  // --- SWEETALERT HANDLER ---
  <?php if ($flash_msg): ?>
    Swal.fire({
      icon: '<?php echo $flash_type; ?>',
      title: '<?php echo ($flash_type === "error") ? "Oops!" : "Note"; ?>',
      text: '<?php echo $flash_msg; ?>',
      confirmButtonColor: '#d63384'
    });
  <?php endif; ?>
  </script>
</body>
</html>