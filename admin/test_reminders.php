<?php
require_once __DIR__ . '/../includes/functions.php';

$localRequest = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (!$localRequest) requireAdmin();

$sent    = $_GET['sent']    ?? '';
$skipped = $_GET['skipped'] ?? '';

// Get all active contracts
$contracts = $conn->query("
    SELECT c.id, c.start_date, c.end_date, c.monthly_rent,
           u.id AS tenant_id, u.full_name, u.email,
           s.stall_number
    FROM contracts c
    JOIN users u ON u.id = c.tenant_id
    JOIN stalls s ON s.id = c.stall_id
    WHERE c.status = 'active'
    ORDER BY u.full_name
");

// ---- Handle force-send ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id'])) {
    $tid  = (int)$_POST['tenant_id'];
    $cid  = (int)$_POST['contract_id'];
    $days = (int)$_POST['days'];
    $type = $_POST['type'] ?? 'rent'; // 'rent' or 'contract'

    $st = $conn->prepare("
        SELECT c.*, s.stall_number, u.full_name, u.email
        FROM contracts c
        JOIN users u ON u.id = c.tenant_id
        JOIN stalls s ON s.id = c.stall_id
        WHERE c.id = ? LIMIT 1
    ");
    $st->bind_param("i", $cid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    $ok = false;
    if ($row && !empty($row['email'])) {

        if ($type === 'contract') {
            // Contract expiry email
            if ($days === 30)       { $urgency = "1 MONTH";  $badge = "🔴"; $color = "#dc2626"; }
            elseif ($days === 60)   { $urgency = "2 MONTHS"; $badge = "🟠"; $color = "#ea580c"; }
            else                    { $urgency = "3 MONTHS"; $badge = "🟡"; $color = "#d97706"; }

            $endLabel = date('M d, Y', strtotime($row['end_date']));
            $subject  = "Contract Expiring in $urgency — A&J Alfresco";
            $html = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
              <div style='background:#d63384;padding:24px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>A&amp;J Alfresco</h2>
                <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Rental Management System</p>
              </div>
              <div style='padding:28px 32px;background:#fff;'>
                <p style='font-size:15px;color:#1e293b;'>Hi <strong>{$row['full_name']}</strong>,</p>
                <p style='color:#475569;line-height:1.7;'>We would like to inform you that your <strong>stall rental contract</strong> with A&amp;J Alfresco is expiring in <strong style='color:$color;'>$urgency</strong>.</p>
                <div style='background:#fdf2f7;border-left:4px solid $color;border-radius:6px;padding:14px 18px;margin:20px 0;'>
                  <p style='margin:0 0 6px;font-size:14px;color:#1e293b;'><strong>Contract End Date:</strong> $endLabel</p>
                  <p style='margin:0;font-size:13px;color:$color;font-weight:700;'>$badge Days Remaining: $days</p>
                </div>
                <p style='color:#475569;line-height:1.7;'>If you wish to continue renting, please <strong>submit a renewal request</strong> through your tenant portal or contact admin directly.</p>
                <p style='color:#94a3b8;font-size:12px;margin-top:28px;'>If you no longer wish to renew, please disregard this message.</p>
              </div>
              <div style='background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #eee;'>
                <p style='color:#94a3b8;font-size:11px;margin:0;'>&copy; " . date('Y') . " A&amp;J Alfresco. All rights reserved.</p>
              </div>
            </div>";

        } elseif ($type === 'overdue') {
            $today = new DateTime('today');
            $currentMonth = $today->format('Y-m');
            $dueDay = min((int)(new DateTime($row['start_date']))->format('d'), (int)$today->format('t'));
            $dueDate = new DateTime($currentMonth . '-01');
            $dueDate->modify('+' . ($dueDay - 1) . ' days');
            $monthStr = $dueDate->format('F Y');
            $dueLabel = $dueDate->format('M d, Y');
            $daysOverdue = max(1, (int)$dueDate->diff($today)->days);
            $subject = "Rent Overdue — $monthStr — A&J Alfresco";
            $html = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
              <div style='background:#d63384;padding:24px;text-align:center;'><h2 style='color:#fff;margin:0;'>A&amp;J Alfresco</h2><p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Rental Management System</p></div>
              <div style='padding:28px 32px;background:#fff;'>
                <p style='font-size:15px;color:#1e293b;'>Hi <strong>{$row['full_name']}</strong>,</p>
                <p style='color:#475569;line-height:1.7;'>Your <strong>monthly rent</strong> for <strong>$monthStr</strong> is overdue.</p>
                <div style='background:#fef2f2;border-left:4px solid #dc2626;border-radius:6px;padding:14px 18px;margin:20px 0;'><p style='margin:0 0 6px;font-size:14px;color:#1e293b;'><strong>Due Date:</strong> $dueLabel</p><p style='margin:0;font-size:13px;color:#dc2626;font-weight:700;'>🔴 Days Overdue: $daysOverdue</p></div>
                <p style='color:#475569;line-height:1.7;'>Please make your payment as soon as possible to keep your account up to date. If you have already paid, please disregard this message.</p>
              </div>
              <div style='background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #eee;'><p style='color:#94a3b8;font-size:11px;margin:0;'>&copy; " . date('Y') . " A&amp;J Alfresco. All rights reserved.</p></div>
            </div>";

        } else {
            // Rent due email
            if ($days === 1)       { $urgency = "TOMORROW";  $badge = "🔴"; $color = "#dc2626"; }
            elseif ($days === 3)   { $urgency = "in 3 DAYS"; $badge = "🟠"; $color = "#ea580c"; }
            else                   { $urgency = "in 1 WEEK"; $badge = "🟡"; $color = "#d97706"; }

            $today   = new DateTime('today');
            $nextDue = new DateTime($row['start_date']);
            while ($nextDue <= $today) $nextDue->modify('+1 month');
            $monthStr = $nextDue->format('F Y');
            $dueLabel = $nextDue->format('M d, Y');
            $subject  = "Rent Due $urgency — A&J Alfresco";
            $html = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
              <div style='background:#d63384;padding:24px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>A&amp;J Alfresco</h2>
                <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Rental Management System</p>
              </div>
              <div style='padding:28px 32px;background:#fff;'>
                <p style='font-size:15px;color:#1e293b;'>Hi <strong>{$row['full_name']}</strong>,</p>
                <p style='color:#475569;line-height:1.7;'>This is a reminder that your <strong>monthly rent</strong> for <strong>$monthStr</strong> is due <strong style='color:$color;'>$urgency</strong>.</p>
                <div style='background:#fdf2f7;border-left:4px solid $color;border-radius:6px;padding:14px 18px;margin:20px 0;'>
                  <p style='margin:0 0 6px;font-size:14px;color:#1e293b;'><strong>Due Date:</strong> $dueLabel</p>
                  <p style='margin:0;font-size:13px;color:$color;font-weight:700;'>$badge Days Remaining: $days</p>
                </div>
                <p style='color:#475569;line-height:1.7;'>Please ensure your payment is made on or before the due date to avoid penalties.</p>
                <p style='color:#94a3b8;font-size:12px;margin-top:28px;'>If you have already paid, please disregard this message.</p>
              </div>
              <div style='background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #eee;'>
                <p style='color:#94a3b8;font-size:11px;margin:0;'>&copy; " . date('Y') . " A&amp;J Alfresco. All rights reserved.</p>
              </div>
            </div>";
        }

        $ok = sendMail($row['email'], $row['full_name'], $subject, $html);
    }

    header("Location: test_reminders.php?sent=" . ($ok ? urlencode($row['full_name'] ?? '') : '') . "&skipped=" . ($ok ? '' : urlencode($row['full_name'] ?? 'unknown')));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Test Email Reminders - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
      <li><a href="notifications.php">Notifications</a></li>
      <li><a href="reports.php">Reports</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php">Logout</a></div>
  </aside>

  <main class="main-content">
    <div class="top-bar">
      <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
        <h1>📧 Test Email Reminders</h1>
      </div>
    </div>

    <div class="content">

      <?php if ($sent): ?>
        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:14px 20px;margin-bottom:20px;color:#166534;font-weight:600;">
          ✅ Email successfully sent to <strong><?php echo htmlspecialchars(urldecode($sent)); ?></strong>
        </div>
      <?php elseif ($skipped): ?>
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:14px 20px;margin-bottom:20px;color:#991b1b;font-weight:600;">
          ❌ Failed to send email to <strong><?php echo htmlspecialchars(urldecode($skipped)); ?></strong> — check SMTP config or error log.
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h2>Active Contracts — Force Send Reminder</h2>
        </div>
        <div class="card-body">
          <p style="color:#64748b;font-size:13px;margin-bottom:20px;">
            Manually trigger a <strong>Contract Expiry</strong>, <strong>Rent Due</strong>, or <strong>Overdue Rent</strong> reminder email to any tenant, bypassing deduplication. Useful for SMTP testing.
          </p>

          <div style="overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Tenant</th>
                  <th>Stall</th>
                  <th>Email</th>
                  <th>Contract End</th>
                  <th>📄 Contract Expiry Email</th>
                  <th>🔴 OVERDUE RENT EMAIL</th>
                  <th>💰 Rent Due Email</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $contracts->data_seek(0);
                while ($row = $contracts->fetch_assoc()):
                ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['stall_number']); ?></td>
                  <td style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars($row['email']); ?></td>
                  <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>

                  <!-- CONTRACT EXPIRY -->
                  <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                      <input type="hidden" name="tenant_id" value="<?php echo (int)$row['tenant_id']; ?>">
                      <input type="hidden" name="contract_id" value="<?php echo (int)$row['id']; ?>">
                      <input type="hidden" name="type" value="contract">
                      <select name="days" style="padding:5px 8px;border:1px solid #ddd;border-radius:8px;font-size:12px;">
                        <option value="90">🟡 3 months (90d)</option>
                        <option value="60">🟠 2 months (60d)</option>
                        <option value="30">🔴 1 month (30d)</option>
                      </select>
                      <button type="submit" class="btn btn-primary btn-sm" style="width:auto;white-space:nowrap;padding:5px 10px;font-size:12px;">
                        <i class="fa-solid fa-paper-plane"></i>
                      </button>
                    </form>
                  </td>

                  <!-- OVERDUE RENT -->
                  <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                      <input type="hidden" name="tenant_id" value="<?php echo (int)$row['tenant_id']; ?>">
                      <input type="hidden" name="contract_id" value="<?php echo (int)$row['id']; ?>">
                      <input type="hidden" name="type" value="overdue">
                      <button type="submit" class="btn btn-primary btn-sm" style="width:auto;white-space:nowrap;padding:5px 10px;font-size:12px;">
                        <i class="fa-solid fa-paper-plane"></i>
                      </button>
                    </form>
                  </td>

                  <!-- RENT DUE -->
                  <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                      <input type="hidden" name="tenant_id" value="<?php echo (int)$row['tenant_id']; ?>">
                      <input type="hidden" name="contract_id" value="<?php echo (int)$row['id']; ?>">
                      <input type="hidden" name="type" value="rent">
                      <select name="days" style="padding:5px 8px;border:1px solid #ddd;border-radius:8px;font-size:12px;">
                        <option value="7">🟡 1 week (7d)</option>
                        <option value="3">🟠 3 days</option>
                        <option value="1">🔴 Tomorrow</option>
                      </select>
                      <button type="submit" class="btn btn-primary btn-sm" style="width:auto;white-space:nowrap;padding:5px 10px;font-size:12px;">
                        <i class="fa-solid fa-paper-plane"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($contracts->num_rows === 0): ?>
                  <tr><td colspan="7" style="text-align:center;color:#94a3b8;">No active contracts found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

      <div class="card" style="margin-top:20px;background:#fffbeb;border:1px solid #fde68a;">
        <div class="card-body" style="padding:18px 24px;">
          <p style="margin:0;font-size:13px;color:#92400e;">
            <strong>⚠️ Note:</strong> This page bypasses deduplication and sends immediately.
            The automatic system fires when a tenant logs into their dashboard: pre-due reminders match a milestone exactly, while overdue rent is sent once per unpaid month after the due date.
          </p>
        </div>
      </div>


    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('show');
}
</script>
</body>
</html>
