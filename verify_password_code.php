<?php
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['password_recovery_csrf'])) {
    $_SESSION['password_recovery_csrf'] = bin2hex(random_bytes(32));
}

$email = $_SESSION['password_recovery_email'] ?? '';
$email = is_string($email) ? $email : '';
$error = '';
$notice = $_SESSION['password_recovery_notice'] ?? '';
unset($_SESSION['password_recovery_notice']);
$code = $_POST['code'] ?? '';
$code = is_string($code) ? trim($code) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['password_recovery_csrf'], $csrfToken)) {
        $error = 'Your request could not be verified. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\A\d{6}\z/', $code)) {
        $error = 'Enter the six-digit verification code from your email.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.failed_attempts
                FROM password_resets pr
                JOIN users u ON u.id=pr.user_id
                WHERE u.email=? AND u.role='tenant' AND u.status='active'
                  AND pr.used_at IS NULL AND pr.verified_at IS NULL
                  AND pr.expires_at > NOW() AND pr.failed_attempts < 5
                ORDER BY pr.id DESC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $reset = $stmt->get_result()->fetch_assoc();

            if ($reset && password_verify($code, $reset['token_hash'])) {
                $resetId = (int)$reset['id'];
                $markVerified = $conn->prepare("UPDATE password_resets SET verified_at=NOW() WHERE id=? AND used_at IS NULL AND verified_at IS NULL");
                $markVerified->bind_param("i", $resetId);
                $markVerified->execute();
                $verified = $markVerified->affected_rows > 0;
                $conn->commit();

                if ($verified) {
                    session_regenerate_id(true);
                    $_SESSION['password_reset_grant'] = [
                        'reset_id' => $resetId,
                        'user_id' => (int)$reset['user_id'],
                        'expires_at' => min(time() + 600, strtotime($reset['expires_at']))
                    ];
                    header('Location: reset_password.php', true, 303);
                    exit();
                }

                $error = 'The code is invalid, expired, or already used. Request a new code.';
            } elseif ($reset) {
                $resetId = (int)$reset['id'];
                $failAttempt = $conn->prepare("UPDATE password_resets SET failed_attempts=failed_attempts + 1 WHERE id=?");
                $failAttempt->bind_param("i", $resetId);
                $failAttempt->execute();
                $attempts = (int)$reset['failed_attempts'] + 1;

                if ($attempts >= 5) {
                    $invalidate = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=? AND used_at IS NULL");
                    $invalidate->bind_param("i", $resetId);
                    $invalidate->execute();
                    $conn->commit();
                    $_SESSION['password_recovery_notice'] = 'Too many tries. Request a new code.';
                    header('Location: forgot_password.php', true, 303);
                    exit();
                }

                $conn->commit();
                $remainingAttempts = 5 - $attempts;
                $error = 'Incorrect code. ' . $remainingAttempts . ' ' . ($remainingAttempts === 1 ? 'try' : 'tries') . ' left.';
            } else {
                $conn->commit();
                $error = 'The code is invalid or expired. Request a new code and try again.';
            }
        } catch (Throwable $exception) {
            $conn->rollback();
            throw $exception;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Verify Code - A&J Alfresco</title>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <style>
    .recovery-alert { margin: 16px 0; padding: 12px 14px; border-radius: 10px; text-align: left; line-height: 1.45; }
    .recovery-alert-error { color: #842029; background: #f8d7da; border: 1px solid #f5c2c7; }
    .recovery-alert-info { color: #084298; background: #cfe2ff; border: 1px solid #b6d4fe; }
    .verification-code-boxes { display: flex; justify-content: center; gap: 10px; }
    .verification-code-box {
      width: 48px !important;
      height: 58px;
      padding: 0 !important;
      text-align: center;
      font-size: 27px !important;
      font-weight: 700;
      border-radius: 12px !important;
      caret-color: #d63384;
    }
    @media (max-width: 400px) {
      .verification-code-boxes { gap: 6px; }
      .verification-code-box { width: 42px !important; height: 54px; }
    }
  </style>
</head>
<body class="login-page">
  <div class="login-box">
    <div class="logo">🏪</div>
    <h1>A&J Alfresco</h1>
    <p class="subtitle">Verify your email address</p>
    <p style="margin:18px 0; color:#475569; line-height:1.5;">Enter the code sent to your email. Expires in 10 minutes.</p>
    <?php if ($error): ?>
      <div class="recovery-alert recovery-alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($email && $error !== 'Too many incorrect attempts. Request a new code.'): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_recovery_csrf']); ?>"/>
        <div class="form-group">
          <label for="code">Six-Digit Verification Code</label>
          <input id="code" type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>"/>
          <div class="verification-code-boxes" role="group" aria-label="Six-digit verification code">
            <?php for ($digitIndex = 0; $digitIndex < 6; $digitIndex++): ?>
              <input class="verification-code-box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="<?php echo $digitIndex === 0 ? 'one-time-code' : 'off'; ?>" aria-label="Digit <?php echo $digitIndex + 1; ?>" value="<?php echo htmlspecialchars($code[$digitIndex] ?? ''); ?>" <?php echo $digitIndex === 0 ? 'autofocus' : ''; ?> required/>
            <?php endfor; ?>
          </div>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%; padding:12px; margin-top:10px;">Verify Code</button>
      </form>
      <form method="POST" action="forgot_password.php" style="margin-top:12px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_recovery_csrf']); ?>"/>
        <input type="hidden" name="action" value="resend_code"/>
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>"/>
        <button type="submit" class="btn btn-secondary" style="width:100%;">Send a New Code</button>
      </form>
    <?php else: ?>
      <a class="btn btn-primary" href="forgot_password.php" style="display:block; width:100%; padding:12px; margin-top:16px; text-align:center;">Request a New Code</a>
    <?php endif; ?>

    <div style="text-align:center; margin-top:16px;">
      <a href="index.php" style="color:#d63384; font-size:13px; font-weight:600;">Back to login</a>
    </div>
    <div class="login-footer">&copy; <?php echo date('Y'); ?> A&J Alfresco</div>
  </div>
  <script>
  (function() {
    const form = document.querySelector('form');
    const boxes = Array.from(document.querySelectorAll('.verification-code-box'));
    const codeInput = document.getElementById('code');

    if (!form || boxes.length !== 6 || !codeInput) return;

    function updateCode() {
      codeInput.value = boxes.map(function(box) { return box.value; }).join('');
    }

    function fillFrom(startIndex, value) {
      const digits = value.replace(/\D/g, '').slice(0, boxes.length - startIndex);
      for (let index = 0; index < digits.length; index++) {
        boxes[startIndex + index].value = digits[index];
      }
      updateCode();
      const nextBox = boxes[Math.min(startIndex + digits.length, boxes.length - 1)];
      nextBox.focus();
      nextBox.select();
    }

    boxes.forEach(function(box, index) {
      box.addEventListener('input', function() {
        const input = box.value;
        if (input.length > 1) {
          fillFrom(index, input);
          return;
        }
        box.value = input.replace(/\D/g, '').slice(-1);
        updateCode();
        if (box.value && index < boxes.length - 1) {
          boxes[index + 1].focus();
        }
      });

      box.addEventListener('keydown', function(event) {
        if (event.key === 'Backspace' && !box.value && index > 0) {
          boxes[index - 1].focus();
          boxes[index - 1].value = '';
          updateCode();
        } else if (event.key === 'ArrowLeft' && index > 0) {
          boxes[index - 1].focus();
        } else if (event.key === 'ArrowRight' && index < boxes.length - 1) {
          boxes[index + 1].focus();
        }
      });

      box.addEventListener('paste', function(event) {
        event.preventDefault();
        const pasted = event.clipboardData ? event.clipboardData.getData('text') : '';
        fillFrom(index, pasted);
      });
    });

    form.addEventListener('submit', function(event) {
      updateCode();
      if (!/^\d{6}$/.test(codeInput.value)) {
        event.preventDefault();
        boxes.find(function(box) { return !box.value; }).focus();
      }
    });
  })();
  </script>
</body>
</html>
