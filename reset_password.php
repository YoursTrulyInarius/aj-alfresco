<?php
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['password_recovery_csrf'])) {
    $_SESSION['password_recovery_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$passwordReminder = '';
$success = $_SESSION['password_reset_success'] ?? '';
unset($_SESSION['password_reset_success']);
$resetGrant = $_SESSION['password_reset_grant'] ?? null;
$grantValid = is_array($resetGrant)
    && isset($resetGrant['reset_id'], $resetGrant['user_id'], $resetGrant['expires_at'])
    && (int)$resetGrant['expires_at'] > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $resetGrant = $_SESSION['password_reset_grant'] ?? null;
    $grantValid = is_array($resetGrant)
        && isset($resetGrant['reset_id'], $resetGrant['user_id'], $resetGrant['expires_at'])
        && (int)$resetGrant['expires_at'] > time();

    if (!is_string($csrfToken) || !hash_equals($_SESSION['password_recovery_csrf'], $csrfToken)) {
        $error = 'Your request could not be verified. Refresh the page and try again.';
    } elseif (!$grantValid) {
        unset($_SESSION['password_reset_grant']);
        $error = 'Your verification has expired. Request a new code.';
    } else {
        $strongPassword = is_string($newPassword)
            && strlen($newPassword) >= 8
            && preg_match('/[a-z]/', $newPassword)
            && preg_match('/[A-Z]/', $newPassword)
            && preg_match('/\d/', $newPassword)
            && preg_match('/[\W_]/', $newPassword);

        if (!$strongPassword) {
            $missingRequirements = [
                'length' => strlen($newPassword) < 8,
                'lowercase' => !preg_match('/[a-z]/', $newPassword),
                'uppercase' => !preg_match('/[A-Z]/', $newPassword),
                'number' => !preg_match('/\d/', $newPassword),
                'special' => !preg_match('/[\W_]/', $newPassword)
            ];
            $reminders = [
                'length' => 'Use 8 or more characters.',
                'lowercase' => 'Add a lowercase letter.',
                'uppercase' => 'Add an uppercase letter.',
                'number' => 'Add a number.',
                'special' => 'Add a special character.'
            ];
            foreach ($missingRequirements as $requirement => $isMissing) {
                if ($isMissing) {
                    $passwordReminder = $reminders[$requirement];
                    break;
                }
            }
        } elseif (!is_string($confirmPassword) || $newPassword !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            $conn->begin_transaction();
            try {
                $resetId = (int)$resetGrant['reset_id'];
                $userId = (int)$resetGrant['user_id'];
                $lookup = $conn->prepare("
                    SELECT pr.id
                    FROM password_resets pr
                    JOIN users u ON u.id=pr.user_id
                    WHERE pr.id=? AND pr.user_id=? AND pr.used_at IS NULL
                      AND pr.verified_at IS NOT NULL AND pr.expires_at > NOW()
                      AND u.role='tenant' AND u.status='active'
                    LIMIT 1
                    FOR UPDATE
                ");
                $lookup->bind_param("ii", $resetId, $userId);
                $lookup->execute();

                if (!$lookup->get_result()->fetch_assoc()) {
                    $conn->rollback();
                    unset($_SESSION['password_reset_grant']);
                    $grantValid = false;
                    $error = 'Your verification has expired. Request a new code.';
                } else {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password=? WHERE id=? AND role='tenant' AND status='active'");
                    $update->bind_param("si", $passwordHash, $userId);
                    $update->execute();

                    $markUsed = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL");
                    $markUsed->bind_param("i", $userId);
                    $markUsed->execute();

                    auditLog('password_reset', 'tenant', $userId);
                    $conn->commit();
                    unset($_SESSION['password_reset_grant']);
                    $_SESSION['password_reset_success'] = 'Your password has been reset. You can now log in with your new password.';
                    header('Location: reset_password.php', true, 303);
                    exit();
                }
            } catch (Throwable $exception) {
                $conn->rollback();
                throw $exception;
            }
        }
    }
}

if (!$grantValid && !$success && !$error) {
    $error = 'Your verification is missing or expired. Request a new code.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Set New Password - A&J Alfresco</title>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <style>
    .recovery-alert { margin: 16px 0; padding: 12px 14px; border-radius: 10px; text-align: left; line-height: 1.45; }
    .recovery-alert-error { color: #842029; background: #f8d7da; border: 1px solid #f5c2c7; }
    .password-reminder { margin: -8px 0 18px; color: #be185d; font-size: 12px; text-align: left; }
  </style>
</head>
<body class="login-page">
  <div class="login-box">
    <div class="logo">🏪</div>
    <h1>A&J Alfresco</h1>
    <p class="subtitle">Choose a new tenant account password</p>

    <?php if ($success): ?>
      <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div>
      <a class="btn btn-primary" href="index.php" style="display:block; width:100%; padding:12px; margin-top:16px; text-align:center;">Go to Login</a>
    <?php elseif ($grantValid): ?>
      <?php if ($error): ?>
        <div class="recovery-alert recovery-alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_recovery_csrf']); ?>"/>
        <div class="form-group">
          <label for="new_password">New Password</label>
          <div class="password-wrap">
            <input id="new_password" type="password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="Enter a strong password"/>
            <button class="pw-toggle" type="button" aria-label="Show new password" aria-controls="new_password" onclick="togglePasswordVisibility('new_password', this)">Show</button>
          </div>
        </div>
        <p class="password-reminder" id="passwordReminder" role="status" aria-live="polite" <?php echo $passwordReminder === '' ? 'hidden' : ''; ?>><?php echo htmlspecialchars($passwordReminder); ?></p>
        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <div class="password-wrap">
            <input id="confirm_password" type="password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Re-enter your password"/>
            <button class="pw-toggle" type="button" aria-label="Show confirmation password" aria-controls="confirm_password" onclick="togglePasswordVisibility('confirm_password', this)">Show</button>
          </div>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%; padding:12px; margin-top:10px;">Reset Password</button>
      </form>
    <?php else: ?>
      <div class="recovery-alert recovery-alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
      <a class="btn btn-primary" href="forgot_password.php" style="display:block; width:100%; padding:12px; margin-top:16px; text-align:center;">Request a New Code</a>
    <?php endif; ?>

    <?php if (!$success): ?>
      <div style="text-align:center; margin-top:16px;">
        <a href="index.php" style="color:#d63384; font-size:13px; font-weight:600;">Back to login</a>
      </div>
    <?php endif; ?>
    <div class="login-footer">&copy; <?php echo date('Y'); ?> A&J Alfresco</div>
  </div>
  <script>
  function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const showPassword = input.type === 'password';
    input.type = showPassword ? 'text' : 'password';
    button.textContent = showPassword ? 'Hide' : 'Show';
    button.setAttribute('aria-label', (showPassword ? 'Hide ' : 'Show ') + (inputId === 'new_password' ? 'new password' : 'confirmation password'));
  }

  const newPasswordInput = document.getElementById('new_password');
  if (newPasswordInput) {
    const reminder = document.getElementById('passwordReminder');
    const missingRequirement = function(value) {
      if (!value) return '';
      if (value.length < 8) return 'Use 8 or more characters.';
      if (!/[a-z]/.test(value)) return 'Add a lowercase letter.';
      if (!/[A-Z]/.test(value)) return 'Add an uppercase letter.';
      if (!/\d/.test(value)) return 'Add a number.';
      if (!/[\W_]/.test(value)) return 'Add a special character.';
      return '';
    };

    newPasswordInput.addEventListener('input', function() {
      const message = missingRequirement(newPasswordInput.value);
      reminder.textContent = message;
      reminder.hidden = message === '';
    });
  }
  </script>
</body>
</html>
