<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$search = sanitize($_GET['search'] ?? '');
$month = sanitize($_GET['month'] ?? '');
$tenant_id = (int)($_GET['tenant_id'] ?? 0);

// Dropdown: active contracts (tenant + stall)
$contracts = $conn->query("
  SELECT c.id contract_id, c.monthly_rent, u.full_name tenant_name, u.business_name, s.stall_number
  FROM contracts c
  JOIN users u ON u.id = c.tenant_id
  JOIN stalls s ON s.id = c.stall_id
  WHERE c.status='active'
  ORDER BY u.full_name
");

// Payment list with filtering
$query = "SELECT p.*, u.full_name tenant_name, u.business_name, s.stall_number
          FROM payments p
          JOIN users u ON u.id = p.tenant_id
          JOIN contracts c ON c.id = p.contract_id
          JOIN stalls s ON s.id = c.stall_id";
$where = [];
if ($search) $where[] = "(u.full_name LIKE '%$search%' OR p.receipt_number LIKE '%$search%')";
if ($month) $where[] = "p.payment_for_month = '$month'";
if ($tenant_id > 0) $where[] = "p.tenant_id = $tenant_id";

if ($where) $query .= " WHERE " . implode(" AND ", $where);
$query .= " ORDER BY p.id DESC";
$payments = $conn->query($query);

$adminId = (int)$_SESSION['user_id'];
$unread = notifUnreadCount($adminId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payments - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=6"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .select2-container--default .select2-selection--single { height: 42px; padding: 6px; border: 1px solid #ddd; border-radius: 8px; }
    .select2-container { width: 100% !important; }
    #paymentFormContainer { display: none; margin-bottom: 25px; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  </style>
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
      <li><a href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
      <li><a href="tenants.php"><span class="icon">👥</span> Tenants</a></li>
      <li><a href="stalls.php"><span class="icon">🏬</span> Stalls</a></li>
      <li><a href="contracts.php"><span class="icon">📄</span> Contracts</a></li>
      <li><a class="active" href="payments.php"><span class="icon">💰</span> Payments</a></li>
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
        <h1>Payments</h1>
      </div>
      <div class="user-info">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">

      <div class="page-header">
        <h2 style="margin:0;">Payment Records</h2>
        <button class="btn btn-primary" onclick="togglePaymentForm()" style="width:auto;">+ Record New Payment</button>
      </div>

      <!-- TOGGLEABLE FORM -->
      <div id="paymentFormContainer" class="card">
        <div class="card-header" style="background: #fdf2f7;">
          <h2>Record New Payment</h2>
          <button class="btn btn-secondary btn-sm" onclick="togglePaymentForm()" style="width:auto; padding: 4px 10px;">Cancel</button>
        </div>
        <div class="card-body">
          <form method="POST" action="process_payments.php" onsubmit="return validatePaymentForm()">
            <input type="hidden" name="action" value="create">

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
              <div class="form-group">
                <label>Select Tenant / Stall *</label>
                <select name="contract_id" required id="contractSelect" onchange="updatePaymentInfo()">
                  <option value="">-- Choose Contract --</option>
                  <?php while($c = $contracts->fetch_assoc()): ?>
                    <option value="<?php echo (int)$c['contract_id']; ?>" 
                            data-rent="<?php echo $c['monthly_rent']; ?>">
                      <?php echo htmlspecialchars($c['tenant_name'] . " (" . $c['business_name'] . ") - " . $c['stall_number']); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Amount to Pay (PHP) *</label>
                <input type="text" name="amount" id="amountInput" required value="0.00" class="money-input">
                <small>Tip: Include advance payments (e.g. 2 months) here.</small>
              </div>

              <div class="form-group">
                <label>Month Covered *</label>
                <input type="month" name="payment_for_month" required value="<?php echo date('Y-m'); ?>">
              </div>

              <div class="form-group">
                <label>Payment Method *</label>
                <select name="payment_method" required>
                  <option value="cash">Cash</option>
                  <option value="gcash">GCash</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="paymongo">PayMongo (Test Mode)</option>
                </select>
              </div>

              <div class="form-group">
                <label>Reference # (if online)</label>
                <input type="text" name="reference_number" placeholder="Trans ID">
              </div>

              <div class="form-group">
                <label>Date *</label>
                <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>

            <div class="form-group" style="margin-top:15px;">
              <label>Internal Notes</label>
              <textarea name="notes" rows="2" placeholder="Optional notes..."></textarea>
            </div>

            <button class="btn btn-primary" type="submit" style="margin-top:10px;">Process Payment & Generate Receipt</button>
          </form>
        </div>
      </div>

      <!-- PAYMENT HISTORY -->
      <div class="card">
        <div class="card-header">
          <h2>Payment History</h2>
          <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Receipt or Name..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px; width:180px;">
            <input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" class="btn btn-primary btn-sm" style="width:auto;">Filter History</button>
          </form>
        </div>
        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Receipt</th>
                <th>Tenant / Stall</th>
                <th>Covered</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Date & Time</th>
                <th style="width:140px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while($p = $payments->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($p['receipt_number']); ?></strong></td>
                  <td>
                    <?php echo htmlspecialchars($p['tenant_name']); ?><br>
                    <small>Stall: <?php echo htmlspecialchars($p['stall_number']); ?></small>
                  </td>
                  <td><?php echo date('M Y', strtotime($p['payment_for_month'] . '-01')); ?></td>
                  <td><?php echo formatMoney($p['amount']); ?></td>
                   <td>
                     <span class="status-badge paid" style="<?php echo ($p['payment_method'] == 'paymongo') ? 'background:#e83e8c;' : ''; ?>">
                       <?php echo strtoupper($p['payment_method']); ?>
                     </span>
                   </td>
                  <td>
                    <small>
                      <?php echo date('M d, Y', strtotime($p['created_at'])); ?><br>
                      <strong><?php echo date('h:i A', strtotime($p['created_at'])); ?></strong>
                    </small>
                  </td>
                  <td>
                    <div style="display:flex; gap:8px;">
                      <a class="btn btn-info btn-sm" href="javascript:void(0)" onclick='viewPayment(<?php echo htmlspecialchars(json_encode($p)); ?>)' title="View Details">👁️</a>
                      <form method="POST" action="process_payments.php" onsubmit="return confirmDelete(event, this)" style="display:inline-block; margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit" title="Delete Payment">🗑️</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
              <?php if ($payments->num_rows === 0): ?>
                <tr><td colspan="7">No matching records.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
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

function togglePaymentForm() {
  const form = document.getElementById('paymentFormContainer');
  $(form).slideToggle(300);
}

function viewPayment(p) {
  Swal.fire({
    title: 'Payment Details',
    html: `
      <div style="text-align:left; line-height:1.8;">
        <p><b>Receipt:</b> ${p.receipt_number}</p>
        <p><b>Tenant:</b> ${p.tenant_name}</p>
        <p><b>Stall:</b> ${p.stall_number}</p>
        <p><b>Amount:</b> ₱${Number(p.amount).toLocaleString()}</p>
        <p><b>Method:</b> ${p.payment_method.toUpperCase()}</p>
        <p><b>Covered:</b> ${p.payment_for_month}</p>
        <p><b>Date:</b> ${new Date(p.created_at).toLocaleString()}</p>
        ${p.notes ? '<p><b>Notes:</b> ' + p.notes + '</p>' : ''}
      </div>
    `,
    icon: 'info',
    confirmButtonColor: '#ff2d55'
  });
}

function confirmDelete(e, form) {
  e.preventDefault();
  Swal.fire({
    title: 'Are you sure?',
    text: "This removal cannot be undone!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      form.submit();
    }
  });
}

function validatePaymentForm() {
  const amount = parseFloat(document.getElementById('amountInput').value.replace(/,/g, ''));
  if (amount <= 0) {
    Swal.fire({ icon: 'error', title: 'Invalid Amount', text: 'Please enter a valid payment amount.' });
    return false;
  }
  return true;
}

$(document).ready(function() {
  $('#contractSelect').select2({
    placeholder: "-- Search Tenant or Stall --",
    allowClear: true
  }).on('change', function() {
    updatePaymentInfo();
  });

  $('.money-input').on('blur', function() {
    formatMoneyInput(this);
  }).on('focus', function() {
    this.value = this.value.replace(/,/g, '');
  }).each(function() {
    formatMoneyInput(this);
  });
});

function formatMoneyInput(el) {
  let val = el.value.replace(/,/g, '');
  if (!isNaN(val) && val !== '') {
    el.value = parseFloat(val).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }
}

function updatePaymentInfo() {
  const sel = document.getElementById('contractSelect');
  if (!sel.value) return;
  const rent = sel.options[sel.selectedIndex].getAttribute('data-rent');
  if (rent) {
    const input = document.getElementById('amountInput');
    input.value = rent;
    formatMoneyInput(input);
  }
}

// --- SWEETALERT FLASH HANDLER ---
<?php if ($flash): ?>
  Swal.fire({
    icon: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "error" : "success"; ?>',
    title: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "Oops!" : "Perfect!"; ?>',
    text: '<?php echo htmlspecialchars($flash); ?>',
    confirmButtonColor: '#d63384'
  });
<?php endif; ?>
</script>
</body>
</html>