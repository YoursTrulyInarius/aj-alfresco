<?php
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['password_recovery_csrf'])) {
    $_SESSION['password_recovery_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$notice = $_SESSION['password_recovery_notice'] ?? '';
unset($_SESSION['password_recovery_notice']);
$action = $_POST['action'] ?? 'request_code';
$emailValue = $_POST['email'] ?? '';
$emailValue = is_string($emailValue) ? strtolower(trim($emailValue)) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['password_recovery_csrf'], $csrfToken)) {
        $error = 'Your request could not be verified. Refresh the page and try again.';
    } elseif (!in_array($action, ['request_code', 'resend_code'], true)) {
        $error = 'This request is not valid. Please try again.';
    } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        unset($_SESSION['password_reset_grant']);
        $_SESSION['password_recovery_email'] = $emailValue;

        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email=? AND role='tenant' AND status='active' LIMIT 1");
        $stmt->bind_param("s", $emailValue);
        $stmt->execute();
        $tenant = $stmt->get_result()->fetch_assoc();

        if ($tenant) {
            $cleanup = $conn->prepare("DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $cleanup->execute();

            $limitStmt = $conn->prepare("SELECT COUNT(*) AS request_count FROM password_resets WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $limitStmt->bind_param("i", $tenant['id']);
            $limitStmt->execute();
            $requestCount = (int)$limitStmt->get_result()->fetch_assoc()['request_count'];

            if ($requestCount < 3) {
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $codeHash = password_hash($code, PASSWORD_DEFAULT);

                $conn->begin_transaction();
                try {
                    $invalidate = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL");
                    $invalidate->bind_param("i", $tenant['id']);
                    $invalidate->execute();

                    $insert = $conn->prepare("INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                    $insert->bind_param("is", $tenant['id'], $codeHash);
                    $insert->execute();
                    $conn->commit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    throw $exception;
                }

                $safeName = htmlspecialchars($tenant['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html = "
                    <p>Hello $safeName,</p>
                    <p>Your A&amp;J Alfresco password verification code is:</p>
                    <p style=\"font-size:28px;font-weight:bold;letter-spacing:6px;\">$code</p>
                    <p>This code expires in 10 minutes and can only be used once. If you did not request this, you can ignore this email.</p>
                ";

                if (!sendMail($tenant['email'], $tenant['full_name'], 'Your A&J Alfresco verification code', $html)) {
                    $invalidate = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE token_hash=? AND used_at IS NULL");
                    $invalidate->bind_param("s", $codeHash);
                    $invalidate->execute();
                    error_log("Password verification code email could not be sent for tenant ID " . (int)$tenant['id']);
                }
            }
        }

        $_SESSION['password_recovery_notice'] = $action === 'resend_code'
            ? 'Use the newest code sent to your email.'
            : 'Enter the 6-digit code sent to your email.';
        header('Location: verify_password_code.php', true, 303);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password - A&J Alfresco</title>
  <link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body class="login-page">
  <div class="login-box">
    <div class="logo">🏪</div>
    <h1>A&J Alfresco</h1>
    <p class="subtitle">Reset your tenant account password</p>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="alert alert-info" role="status"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_recovery_csrf']); ?>"/>
      <input type="hidden" name="action" value="request_code"/>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input id="email" type="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($emailValue); ?>" placeholder="Enter your tenant account email"/>
      </div>
      <button class="btn btn-primary" type="submit" style="width:100%; padding:12px; margin-top:10px;">Send Verification Code</button>
    </form>

    <div style="text-align:center; margin-top:16px;">
      <a href="index.php" style="color:#d63384; font-size:13px; font-weight:600;">Back to login</a>
    </div>
    <div class="login-footer">&copy; <?php echo date('Y'); ?> A&J Alfresco</div>
  </div>
</body>
</html>
