<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$search = sanitize($_GET['search'] ?? '');

// Tenants dropdown
$tenants = $conn->query("SELECT id, full_name, business_name FROM users WHERE role='tenant' AND status='active' ORDER BY full_name");

// Available stalls dropdown
$stalls = $conn->query("SELECT id, stall_number, monthly_rate FROM stalls WHERE status='available' ORDER BY stall_number");

// Contract list
$query = "SELECT c.*, 
                 u.full_name tenant_name, u.business_name,
                 s.stall_number, s.location_description
          FROM contracts c
          JOIN users u ON u.id = c.tenant_id
          JOIN stalls s ON s.id = c.stall_id";
if ($search) {
    $query .= " WHERE u.full_name LIKE '%$search%' OR u.business_name LIKE '%$search%' OR s.stall_number LIKE '%$search%'";
}
$query .= " ORDER BY c.id DESC";
$contracts = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contracts - Admin</title>
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
    .select2-container { width: 100% !important; margin-top: 5px; }
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
      <li><a class="active" href="contracts.php"><span class="icon">📄</span> Contracts</a></li>
      <li><a href="payments.php"><span class="icon">💰</span> Payments</a></li>
      <li><a href="reports.php"><span class="icon">📈</span> Reports</a></li>
      <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="top-bar">
      <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
        <h1>Contracts</h1>
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
          <h2>Create New Contract</h2>
        </div>
        <div class="card-body">
          <form method="POST" action="process_contract.php">
            <input type="hidden" name="action" value="create">

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
              <div class="form-group">
                <label>Tenant Account *</label>
                <select name="tenant_id" required id="tenantSelect">
                  <option value="">-- Select Tenant --</option>
                  <?php while($t = $tenants->fetch_assoc()): ?>
                    <option value="<?php echo (int)$t['id']; ?>">
                      <?php echo htmlspecialchars($t['full_name'] . " (" . $t['business_name'] . ")"); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Stall (Available) *</label>
                <select name="stall_id" required id="stallSelect" onchange="updateRate()">
                  <option value="">-- Select Stall --</option>
                  <?php while($s = $stalls->fetch_assoc()): ?>
                    <option value="<?php echo (int)$s['id']; ?>" data-rate="<?php echo $s['monthly_rate']; ?>">
                      <?php echo htmlspecialchars($s['stall_number'] . " - " . formatMoney($s['monthly_rate']) . "/mo"); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" id="startDate" required onchange="calculateEndDate()">
              </div>

              <div class="form-group">
                <label>Contract Duration *</label>
                <select name="duration_type" id="durationType" required onchange="calculateEndDate()">
                  <option value="6 months">6 Months</option>
                  <option value="1 year" selected>1 Year</option>
                  <option value="2 years">2 Years</option>
                  <option value="3 years">3 Years</option>
                </select>
              </div>

              <div class="form-group">
                <label>End Date (Auto)</label>
                <input type="date" name="end_date" id="endDate" readonly required>
              </div>

              <div class="form-group">
                <label>Monthly Rent (PHP) *</label>
                <input type="text" name="monthly_rent" id="monthlyRent" required value="0.00" class="money-input">
              </div>

              <div class="form-group">
                <label>Security Deposit (Amount) *</label>
                <input type="text" name="deposit_amount" required value="0.00" placeholder="e.g. 2 months rent" class="money-input">
              </div>

              <div class="form-group">
                <label>Terms / Special Agreements</label>
                <textarea name="terms" rows="2" placeholder="Any extra terms..."></textarea>
              </div>
            </div>

            <button class="btn btn-primary" type="submit">Finalize & Create Contract</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2>Manage Contracts</h2>
          <form method="GET" style="display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search tenant or business..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" class="btn btn-primary btn-sm" style="width:auto;">Filter</button>
          </form>
        </div>
        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Tenant / Business</th>
                <th>Stall</th>
                <th>Duration</th>
                <th>Monthly</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php while($c = $contracts->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($c['tenant_name']); ?></strong><br>
                  <small><?php echo htmlspecialchars($c['business_name']); ?></small>
                </td>
                <td><strong><?php echo htmlspecialchars($c['stall_number']); ?></strong></td>
                <td>
                  <small><?php echo formatDate($c['start_date']); ?> to</small><br>
                  <small><?php echo formatDate($c['end_date']); ?></small>
                </td>
                <td><?php echo formatMoney($c['monthly_rent']); ?></td>
                <td>
                  <span class="status-badge <?php echo htmlspecialchars($c['status']); ?>">
                    <?php echo htmlspecialchars($c['status']); ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex; gap:5px;">
                    <a class="btn btn-primary btn-sm" href="print_contract.php?id=<?php echo (int)$c['id']; ?>" target="_blank">Print Agreement</a>
                    
                    <?php if ($c['status'] === 'active'): ?>
                      <form method="POST" action="process_contract.php" onsubmit="return confirm('Terminate this contract?');">
                        <input type="hidden" name="action" value="terminate">
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <input type="hidden" name="stall_id" value="<?php echo (int)$c['stall_id']; ?>">
                        <input type="hidden" name="tenant_id" value="<?php echo (int)$c['tenant_id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Terminate</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            <?php if ($contracts->num_rows === 0): ?>
              <tr><td colspan="6">No contracts found.</td></tr>
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

function updateRate() {
  const sel = document.getElementById('stallSelect');
  if (!sel.value) return;
  const rate = sel.options[sel.selectedIndex].getAttribute('data-rate');
  if (rate) {
    const input = document.getElementById('monthlyRent');
    input.value = rate;
    formatMoneyInput(input);
  }
}

function formatMoneyInput(el) {
  let val = el.value.replace(/,/g, '');
  if (!isNaN(val) && val !== '') {
    el.value = parseFloat(val).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }
}

$(document).ready(function() {
  $('#tenantSelect, #stallSelect').select2({
    placeholder: "Search...",
    allowClear: true
  });
  
  $('#stallSelect').on('change', function() {
    updateRate();
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

function calculateEndDate() {
  const start = document.getElementById('startDate').value;
  const duration = document.getElementById('durationType').value;
  if (!start) return;

  const date = new Date(start);
  if (duration === '6 months') date.setMonth(date.getMonth() + 6);
  else if (duration === '1 year') date.setFullYear(date.getFullYear() + 1);
  else if (duration === '2 years') date.setFullYear(date.getFullYear() + 2);
  else if (duration === '3 years') date.setFullYear(date.getFullYear() + 3);

  // Subtract 1 day for exact term
  date.setDate(date.getDate() - 1);
  
  document.getElementById('endDate').value = date.toISOString().split('T')[0];
}
</script>
</body>
</html>
```