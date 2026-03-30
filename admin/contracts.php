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

$adminId = (int)$_SESSION['user_id'];
$unread = notifUnreadCount($adminId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contracts - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=5"/>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .select2-container--default .select2-selection--single { height: 42px; padding: 6px; border: 1px solid #ddd; border-radius: 8px; }
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
        <h1>Manage Contracts</h1>
      </div>
      <div class="user-info">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="card" style="margin-bottom: 25px;">
        <div class="card-header">
          <h2>Create New Contract</h2>
        </div>
        <div class="card-body">
          <form method="POST" action="process_contract.php" id="contractForm" onsubmit="return validateContract()">
            <input type="hidden" name="action" value="create">

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
              <div class="form-group">
                <label>Select Tenant *</label>
                <select name="tenant_id" required id="tenantSelect">
                  <option value="">-- Search Tenant --</option>
                  <?php while($t = $tenants->fetch_assoc()): ?>
                    <option value="<?php echo (int)$t['id']; ?>">
                      <?php echo htmlspecialchars($t['full_name'] . " (" . $t['business_name'] . ")"); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Select Stall *</label>
                <select name="stall_id" required id="stallSelect">
                  <option value="">-- Choose Available Stall --</option>
                  <?php while($s = $stalls->fetch_assoc()): ?>
                    <option value="<?php echo (int)$s['id']; ?>" data-rate="<?php echo $s['monthly_rate']; ?>">
                      <?php echo htmlspecialchars($s['stall_number']); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Monthly Rent (PHP) *</label>
                <input type="number" name="monthly_rent" id="rentInput" step="0.01" required placeholder="0.00">
              </div>

              <div class="form-group">
                <label>Security Deposit (PHP) *</label>
                <input type="number" name="deposit_amount" step="0.01" required placeholder="0.00">
              </div>

              <div class="form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>">
              </div>

              <div class="form-group">
                <label>End Date *</label>
                <input type="date" name="end_date" required value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
              </div>
            </div>

            <div class="form-group" style="margin-top:20px;">
              <label>Special Terms & Conditions</label>
              <textarea name="terms" rows="4" placeholder="Optional notes about the lease..."></textarea>
            </div>

            <button class="btn btn-primary" type="submit" style="width:auto; margin-top:10px;">📄 Create Contract</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2>Active & Past Leases</h2>
          <form method="GET" style="display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" class="btn btn-primary btn-sm" style="width:auto;">Filter</button>
          </form>
        </div>
        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Tenant / Business</th>
                <th>Stall Info</th>
                <th>Validity</th>
                <th>Rent / Deposit</th>
                <th>Status</th>
                <th style="width:180px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while($c = $contracts->fetch_assoc()): ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($c['tenant_name']); ?></strong><br>
                    <small><?php echo htmlspecialchars($c['business_name']); ?></small>
                  </td>
                  <td>
                    <strong><?php echo htmlspecialchars($c['stall_number']); ?></strong><br>
                    <small><?php echo htmlspecialchars($c['location_description']); ?></small>
                  </td>
                  <td>
                    <small><?php echo formatDate($c['start_date']); ?> to</small><br>
                    <strong><?php echo formatDate($c['end_date']); ?></strong>
                  </td>
                  <td>
                    <small>Rent: <strong><?php echo formatMoney($c['monthly_rent']); ?></strong></small><br>
                    <small>Dep: <?php echo formatMoney($c['deposit_amount']); ?></small>
                  </td>
                  <td>
                    <span class="status-badge <?php echo $c['status'] === 'active' ? 'active' : 'overdue'; ?>">
                      <?php echo strtoupper($c['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:5px;">
                      <a class="btn btn-primary btn-sm" target="_blank" href="print_contract.php?id=<?php echo (int)$c['id']; ?>">Print</a>
                      <form method="POST" action="process_contract.php" onsubmit="return confirmAction(event, this, 'terminate')">
                        <input type="hidden" name="action" value="terminate">
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit">End Lease</button>
                      </form>
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

function confirmAction(e, form, type) {
  e.preventDefault();
  Swal.fire({
    title: 'Are you sure?',
    text: "You are about to "+type+" this contract lease.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, proceed'
  }).then((result) => {
    if (result.isConfirmed) {
      form.submit();
    }
  });
}

function validateContract() {
    const rent = document.getElementById('rentInput').value;
    if (rent <= 0) {
        Swal.fire({ icon: 'error', title: 'Invalid Rent', text: 'Monthly rent must be greater than zero.' });
        return false;
    }
    return true;
}

$(document).ready(function() {
  $('#tenantSelect').select2({ placeholder: "-- Search Tenant --", allowClear: true });
  $('#stallSelect').select2({ placeholder: "-- Choose Available Stall --", allowClear: true }).on('change', function() {
    const rate = $(this).find(':selected').data('rate');
    if (rate) $('#rentInput').val(rate);
  });
});

// --- SWEETALERT FLASH HANDLER ---
<?php if ($flash): ?>
  Swal.fire({
    icon: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "error" : "success"; ?>',
    title: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "Oops!" : "Perfect!"; ?>',
    text: '<?php echo htmlspecialchars($flash); ?>',
    confirmButtonColor: '#007bff'
  });
<?php endif; ?>
</script>
</body>
</html>