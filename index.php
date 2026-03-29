<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) redirect('admin/dashboard.php');
    if (isTenant()) redirect('tenant/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, full_name, email, password, role, status FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = "Account not found.";
    } elseif ($user['status'] !== 'active') {
        $error = "Account is inactive.";
    } elseif (!password_verify($password, $user['password'])) {
        $error = "Wrong password.";
    } else {
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
</head>
<body>
  <div class="login-page">
    <div class="login-box">
      <div class="logo">🏪</div>
      <h1>A&J Alfresco</h1>
      <p class="subtitle">Rental Management System</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required placeholder="admin@ajalfresco.com" />
        </div>

        <div class="form-group">
  <label>Password</label>

  <div class="password-wrap">
    <input id="passwordInput" type="password" name="password" required placeholder="Enter password" />
    <button class="pw-toggle" type="button" onclick="togglePassword()">Show</button>
  </div>
</div>

<script>
function togglePassword(){
  const input = document.getElementById('passwordInput');
  const btn = document.querySelector('.pw-toggle');
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.textContent = isHidden ? 'Hide' : 'Show';
}
</script>

        <button class="btn btn-primary" type="submit">Login</button>
      </form>

    </div>
  </div>
</body>
</html>