<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$initial = strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1));

$unread = notifUnreadCount($tenantId);

$filterType = sanitize($_GET['filter_type'] ?? '');

$query = "
  SELECT *
  FROM notifications
  WHERE user_id = ?
";
if ($filterType === 'payment') {
    $query .= " AND type IN ('payment', 'due_date')";
} elseif ($filterType === 'contract') {
    $query .= " AND type = 'contract_expiry'";
}
$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$notifs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
  <style>
    .notif-msg { position: relative; }

    .notif-details-trigger {
      display: block;
      margin-top: 6px;
      padding: 0;
      border: 0;
      background: none;
      color: #64748b;
      font: inherit;
      font-size: 11px;
      text-align: left;
      cursor: pointer;
    }

    .notif-details-trigger:hover,
    .notif-details-trigger:focus-visible { color: #d63384; color: var(--accent, #d63384); text-decoration: underline; }

    .tenant-notif-backdrop {
      position: fixed;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      background: rgba(15, 23, 42, 0.58);
    }

    .tenant-notif-backdrop[hidden] { display: none; }

    .tenant-notif-dialog {
      position: relative;
      width: 100%;
      max-width: 620px;
      max-height: 90vh;
      overflow-y: auto;
      padding: 32px 36px 30px;
      border-radius: 20px;
      background: #fff;
      box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
      text-align: center;
      color: #2d3748;
    }

    .tenant-notif-close {
      position: absolute;
      top: 12px;
      right: 14px;
      width: 36px;
      height: 36px;
      border: 0;
      border-radius: 50%;
      background: #f1f5f9;
      color: #475569;
      font-size: 24px;
      line-height: 1;
      cursor: pointer;
    }

    .tenant-notif-close:hover { background: #e2e8f0; }

    .tenant-notif-icon {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      border: 4px solid #29b7d9;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 18px;
      background: #f0fbfd;
      color: #29b7d9;
      font-size: 62px;
      font-weight: 700;
      line-height: 1;
    }

    .tenant-notif-title {
      font-size: 2.1rem;
      font-size: clamp(1.65rem, 5vw, 2.35rem);
      line-height: 1.08;
      letter-spacing: -0.04em;
      color: #2c2f36;
      font-weight: 700;
      margin: 0 0 18px;
      text-align: center;
      word-break: break-word;
      overflow-wrap: anywhere;
    }

    .tenant-notif-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin: 0 0 20px;
      text-align: left;
    }

    .tenant-notif-detail {
      flex: 1 1 calc(50% - 10px);
      min-width: 140px;
      padding: 12px 14px;
      border: 1px solid #e8edf2;
      border-radius: 12px;
      background: #f8fafc;
    }

    .tenant-notif-label {
      display: block;
      margin-bottom: 4px;
      color: #64748b;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .tenant-notif-value {
      display: block;
      color: #1e293b;
      font-size: 14px;
      font-weight: 700;
      word-break: break-word;
      overflow-wrap: anywhere;
    }

    .tenant-notif-value.is-alert {
      color: #b42318;
    }

    .tenant-notif-copy {
      font-size: 16px;
      line-height: 1.5;
      color: #475569;
      margin: 0 0 20px;
      text-align: left;
    }

    .tenant-notif-action {
      width: 100%;
      max-width: 220px;
      border: none;
      border-radius: 12px;
      background: linear-gradient(135deg, #ff5a7c 0%, #f84d76 100%);
      color: #fff;
      padding: 13px 20px;
      font-size: 16px;
      font-weight: 700;
      letter-spacing: -0.04em;
      cursor: pointer;
      box-shadow: 0 8px 18px rgba(248, 77, 118, 0.28);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .tenant-notif-action:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(248, 77, 118, 0.32);
    }

    @media (max-width: 480px) {
      .tenant-notif-backdrop { padding: 12px; }
      .tenant-notif-dialog { padding: 28px 20px 22px; border-radius: 16px; }
      .tenant-notif-icon { width: 76px; height: 76px; font-size: 48px; }
      .tenant-notif-copy { font-size: 15px; }
      .tenant-notif-detail { flex-basis: 100%; }
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
      <li>
        <a class="active" href="notifications.php">Notifications
          <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?>
        </a>
      </li>
      <li><a href="change_password.php">Change Password</a></li>
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
        <h1>Notifications</h1>
      </div>
      <div class="user-info">
        <div class="avatar"><?php echo $initial; ?></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="card">
        <div class="card-header" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
          <h2>All Notifications</h2>
          <div style="display:flex; gap:15px; align-items:center;">
            <form method="GET" style="margin:0; display:flex; align-items:center; gap:8px;">
              <label for="filter_type" style="font-size:13.5px; font-weight:600; color:#475569;">Filter:</label>
              <select name="filter_type" id="filter_type" onchange="this.form.submit()" style="padding:6px 12px; border:1px solid #ddd; border-radius:8px; font-size:13.5px;">
                <option value="">All Notifications</option>
                <option value="payment" <?php echo $filterType === 'payment' ? 'selected' : ''; ?>>Payment Alerts</option>
                <option value="contract" <?php echo $filterType === 'contract' ? 'selected' : ''; ?>>Contract Alerts</option>
              </select>
            </form>
            <form method="POST" action="process_notifications.php" style="margin:0">
              <input type="hidden" name="action" value="mark_all_read">
              <button class="btn btn-warning btn-sm" type="submit">Mark All as Read</button>
            </form>
          </div>
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
                  <td class="notif-msg">
                    <?php echo htmlspecialchars($n['message']); ?>
                    <button
                      type="button"
                      class="notif-details-trigger"
                      data-title="<?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>"
                      data-message="<?php echo htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'); ?>"
                      data-type="<?php echo htmlspecialchars($n['type'], ENT_QUOTES, 'UTF-8'); ?>"
                    >Click to view full details</button>
                  </td>
                  <td><?php echo htmlspecialchars($n['type']); ?></td>
                  <td>
                    <?php 
                    $type = $n['type'];
                    $isRead = (int)$n['is_read'] === 1;
                    
                    if ($type === 'contract_expiry') {
                        $badgeClass = 'overdue';
                        $labelText = $isRead ? 'read' : 'unread';
                    } elseif ($type === 'payment' || $type === 'due_date') {
                        $badgeClass = $isRead ? 'paid' : 'pending';
                        $labelText = $isRead ? 'read' : 'unread';
                    } else {
                        $badgeClass = $isRead ? 'active' : 'pending';
                        $labelText = $isRead ? 'read' : 'unread';
                    }
                    ?>
                    <span class="status-badge <?php echo $badgeClass; ?>">
                      <?php echo $labelText; ?>
                    </span>
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
<div class="tenant-notif-backdrop" id="tenantNotifBackdrop" hidden>
  <section
    class="tenant-notif-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tenantNotifTitle"
    aria-describedby="tenantNotifCopy"
  >
    <button type="button" class="tenant-notif-close" aria-label="Close notification details">&times;</button>
    <div class="tenant-notif-icon" aria-hidden="true">i</div>
    <h2 class="tenant-notif-title" id="tenantNotifTitle"></h2>
    <div class="tenant-notif-meta" id="tenantNotifMeta"></div>
    <p class="tenant-notif-copy" id="tenantNotifCopy"></p>
    <button type="button" class="tenant-notif-action">Okay</button>
  </section>
</div>
<script>
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('show');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}

var notifBackdrop = document.getElementById('tenantNotifBackdrop');
var notifDialog = notifBackdrop.querySelector('.tenant-notif-dialog');
var notifTitle = document.getElementById('tenantNotifTitle');
var notifMeta = document.getElementById('tenantNotifMeta');
var notifCopy = document.getElementById('tenantNotifCopy');
var previousNotifTrigger = null;

function closeNotif() {
  notifBackdrop.hidden = true;
  document.body.style.overflow = '';
  if (previousNotifTrigger && document.documentElement.contains(previousNotifTrigger)) {
    previousNotifTrigger.focus();
  }
}

var notifTriggers = document.querySelectorAll('.notif-details-trigger');
for (var triggerIndex = 0; triggerIndex < notifTriggers.length; triggerIndex++) {
  (function (trigger) {
    trigger.addEventListener('click', function () {
      var title = trigger.getAttribute('data-title') || '';
      var message = trigger.getAttribute('data-message') || '';
      var notificationType = trigger.getAttribute('data-type') || '';
      var metaMatch = message.match(/^\[([^\]]+)\]/);
      var metaItems = metaMatch ? metaMatch[1].split('|') : [];
      var detailText = message.replace(/^\[[^\]]+\]\s*/, '').trim();
      var monthNames = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
      ];

      notifTitle.textContent = title;
      notifCopy.textContent = detailText;
      while (notifMeta.firstChild) {
        notifMeta.removeChild(notifMeta.firstChild);
      }
      for (var metaIndex = 0; metaIndex < metaItems.length; metaIndex++) {
        var item = metaItems[metaIndex];
        var label = 'Details';
        var value = item;
        var isAlert = false;

        if (!item) continue;

        if (item === 'overdue') {
          label = 'Payment status';
          value = 'Overdue';
          isAlert = true;
        } else if (item === 'rent') {
          label = 'Payment status';
          value = 'Upcoming';
        } else if (/^contract#\d+$/i.test(item)) {
          label = 'Contract';
          value = '#' + item.split('#')[1];
        } else if (/^\d{4}-\d{2}$/.test(item)) {
          var monthPeriodParts = item.split('-');
          var periodYear = parseInt(monthPeriodParts[0], 10);
          var periodMonth = parseInt(monthPeriodParts[1], 10);
          if (periodMonth >= 1 && periodMonth <= 12) {
            label = 'Rent period';
            value = monthNames[periodMonth - 1] + ' ' + periodYear;
          }
        } else if (/^\d{4}-\d{2}-\d{2}$/.test(item)) {
          var dateParts = item.split('-');
          var dateYear = parseInt(dateParts[0], 10);
          var dateMonth = parseInt(dateParts[1], 10);
          var dateDay = parseInt(dateParts[2], 10);
          var parsedDate = new Date(dateYear, dateMonth - 1, dateDay);
          var dateIsValid = dateMonth >= 1 && dateMonth <= 12 &&
            parsedDate.getFullYear() === dateYear &&
            parsedDate.getMonth() === dateMonth - 1 &&
            parsedDate.getDate() === dateDay;
          if (dateIsValid) {
            label = notificationType === 'contract_expiry' ? 'Contract end date' : 'Due date';
            value = monthNames[dateMonth - 1] + ' ' + dateDay + ', ' + dateYear;
          }
        } else if (/^\d+d$/i.test(item)) {
          var days = parseInt(item.slice(0, -1), 10);
          var isOverdue = metaItems.indexOf('overdue') !== -1;
          label = isOverdue ? 'Overdue by' : 'Time remaining';
          value = days + (days === 1 ? ' day ' : ' days ') + (isOverdue ? 'overdue' : 'remaining');
          isAlert = isOverdue;
        } else if (metaIndex === 0) {
          label = 'Status';
        }

        var detail = document.createElement('div');
        detail.className = 'tenant-notif-detail';
        var detailLabel = document.createElement('span');
        detailLabel.className = 'tenant-notif-label';
        detailLabel.textContent = label;
        var detailValue = document.createElement('span');
        detailValue.className = 'tenant-notif-value' + (isAlert ? ' is-alert' : '');
        detailValue.textContent = value;
        detail.appendChild(detailLabel);
        detail.appendChild(detailValue);
        notifMeta.appendChild(detail);
      }

      previousNotifTrigger = trigger;
      notifBackdrop.hidden = false;
      document.body.style.overflow = 'hidden';
      notifDialog.querySelector('.tenant-notif-close').focus();
    });
  })(notifTriggers[triggerIndex]);
}

var notifCloseButtons = notifBackdrop.querySelectorAll('.tenant-notif-close, .tenant-notif-action');
for (var closeButtonIndex = 0; closeButtonIndex < notifCloseButtons.length; closeButtonIndex++) {
  notifCloseButtons[closeButtonIndex].addEventListener('click', closeNotif);
}

notifBackdrop.addEventListener('click', function (event) {
  if (event.target === notifBackdrop) closeNotif();
});

document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape' && !notifBackdrop.hidden) closeNotif();
});

notifDialog.addEventListener('keydown', function (event) {
  if (event.key !== 'Tab') return;

  var focusable = notifDialog.querySelectorAll('button:not([disabled])');
  var first = focusable[0];
  var last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
});

window.addEventListener('pageshow', function () {
  notifBackdrop.hidden = true;
  document.body.style.overflow = '';
});
</script>
</body>
</html>