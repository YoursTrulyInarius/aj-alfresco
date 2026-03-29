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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payments - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=2"/>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <style>
    .select2-container--default .select2-selection--single {
      height: 42px;
      padding: 6px;
      border: 1px solid #ddd;
      border-radius: 8px;
    }
    .select2-container { width: 100% !important; }
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

      <?php if ($flash): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h2>Record New Payment</h2>
        </div>
        <div class="card-body">
          <form method="POST" action="process_payments.php">
            <input type="hidden" name="action" value="create">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
              <div class="form-group">
                <label>Select Tenant / Stall *</label>
                <select name="contract_id" required id="contractSelect" onchange="updatePaymentInfo()">
                  <option value="">-- Choose Contract --</option>
                  <?php while($c = $contracts->fetch_assoc()): ?>
                    <option value="<?php echo (int)$c['contract_id']; ?>" 
                            data-rent="<?php echo $c['monthly_rent']; ?>"
                            <?php echo ($tenant_id == (int)$c['contract_id']) ? 'selected' : ''; ?>>
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
                  <option value="paymongo">PayMongo</option>
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

            <div class="form-group">
              <label>Internal Notes</label>
              <textarea name="notes" rows="2" placeholder="Optional notes..."></textarea>
            </div>

            <button class="btn btn-primary" type="submit">Process Payment & Generate Receipt</button>
          </form>
        </div>
      </div>

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
                <th style="width:200px;">Action</th>
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
                    <span class="status-badge paid"><?php echo strtoupper($p['payment_method']); ?></span><br>
                    <small><?php echo htmlspecialchars($p['reference_number']); ?></small>
                  </td>
                  <td>
                    <div style="display:flex; gap:5px;">
                      <a class="btn btn-success btn-sm" target="_blank" href="receipt.php?id=<?php echo (int)$p['id']; ?>">Print</a>
                      <form method="POST" action="process_payments.php" onsubmit="return confirm('Delete this record?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
              <?php if ($payments->num_rows === 0): ?>
                <tr><td colspan="6">No matching records.</td></tr>
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

$(document).ready(function() {
  $('#contractSelect').select2({
    placeholder: "-- Search Tenant or Stall --",
    allowClear: true
  }).on('change', function() {
    updatePaymentInfo();
  });

  // Money input formatting
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
</script>
</body>
</html>
</div>
</body>
</html>