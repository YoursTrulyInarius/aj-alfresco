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
$editTenants = $conn->query("SELECT id, full_name, business_name FROM users WHERE role='tenant' ORDER BY full_name");
$editStalls = $conn->query("SELECT id, stall_number, monthly_rate, status FROM stalls ORDER BY stall_number");

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
  <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
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
      <li><a class="active" href="contracts.php">Contracts</a></li>
      <li><a href="payments.php">Payments</a></li>
      <li><a href="notifications.php">Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
      <li><a href="reports.php">Reports</a></li>
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
          <form method="POST" action="process_contract.php" id="contractForm" onsubmit="return handleContractSubmit(event)">
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
                <input type="number" name="monthly_rent" step="0.01" required id="rentInput" placeholder="0.00" readonly aria-readonly="true" autocomplete="off" onkeydown="return false" onpaste="return false" ondrop="return false">
              </div>

              <div class="form-group">
                <label>Security Deposit (PHP) *</label>
                <input type="number" name="deposit_amount" step="0.01" required placeholder="0.00">
              </div>

              <div class="form-group">
                <label>Duration Type *</label>
                <select name="duration_type" id="durationType" required onchange="updateEndDate()">
                  <option value="6 months">6 Months</option>
                  <option value="1 year" selected>1 Year</option>
                  <option value="2 years">2 Years</option>
                  <option value="3 years">3 Years</option>
                </select>
              </div>

              <div class="form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" id="startDate" required value="<?php echo date('Y-m-d'); ?>" onchange="updateEndDate()">
              </div>

              <div class="form-group">
                <label>End Date *</label>
                <input type="date" name="end_date" id="endDate" required value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
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
                <?php $displayStatus = contractDisplayStatus($c['status'], $c['end_date']); ?>
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
                    <span class="status-badge <?php echo htmlspecialchars($displayStatus); ?>">
                      <?php echo strtoupper(str_replace('_', ' ', $displayStatus)); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:8px; align-items:center;">
                      <a class="btn btn-primary btn-sm btn-action" href="print_contract.php?id=<?php echo (int)$c['id']; ?>" target="_blank" title="View PDF"><i class="fa-solid fa-eye"></i></a>
                      <button class="btn btn-warning btn-sm btn-action" type="button" onclick='editContract(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)' title="Edit Contract"><i class="fa-solid fa-pen"></i></button>
                      <?php if ($c['status'] !== 'terminated'): ?>
                        <form method="POST" action="process_contract.php" onsubmit="return confirmAction(event, this, 'terminate')" style="display:inline-block; margin:0; line-height: 0;">
                          <input type="hidden" name="action" value="terminate">
                          <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                          <button class="btn btn-danger btn-sm btn-action" type="submit" title="Terminate Contract"><i class="fa-solid fa-ban"></i></button>
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

      <div id="contractEditOverlay" class="modal-overlay" onclick="handleContractOverlayClick(event)">
        <div class="modal-content">
          <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
            <h2>Edit Contract</h2>
            <span style="font-size:30px; cursor:pointer;" onclick="closeContractEditor()">&times;</span>
          </div>
          <form method="POST" action="process_contract.php" id="contractEditForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editContractId">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
              <div class="form-group">
                <label>Select Tenant *</label>
                <select name="tenant_id" id="editTenantSelect" required>
                  <?php while($t = $editTenants->fetch_assoc()): ?>
                    <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['full_name'] . " (" . $t['business_name'] . ")"); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Select Stall *</label>
                <select name="stall_id" id="editStallSelect" required>
                  <?php while($s = $editStalls->fetch_assoc()): ?>
                    <option value="<?php echo (int)$s['id']; ?>" data-rate="<?php echo htmlspecialchars($s['monthly_rate']); ?>">
                      <?php echo htmlspecialchars($s['stall_number'] . ($s['status'] !== 'available' ? ' (' . $s['status'] . ')' : '')); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Monthly Rent (PHP)</label>
                <input type="number" id="editRentInput" step="0.01" readonly aria-readonly="true" tabindex="-1">
              </div>
              <div class="form-group">
                <label>Contract Status *</label>
                <select name="status" id="editStatusSelect" required>
                  <option value="active">Active</option>
                  <option value="pending_renewal">Pending Renewal</option>
                  <option value="terminated">Terminated</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
              <div class="form-group">
                <label>Security Deposit (PHP) *</label>
                <input type="number" name="deposit_amount" id="editDepositInput" step="0.01" required>
              </div>
              <div class="form-group">
                <label>Duration Type *</label>
                <select name="duration_type" id="editDurationType" required>
                  <option value="6 months">6 Months</option>
                  <option value="1 year">1 Year</option>
                  <option value="2 years">2 Years</option>
                  <option value="3 years">3 Years</option>
                </select>
              </div>
              <div class="form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" id="editStartDate" required>
              </div>
              <div class="form-group">
                <label>End Date *</label>
                <input type="date" name="end_date" id="editEndDate" required>
              </div>
            </div>
            <div class="form-group" style="margin-top:20px;">
              <label>Special Terms & Conditions</label>
              <textarea name="terms" id="editTermsInput" rows="4" placeholder="Optional notes about the lease..."></textarea>
            </div>
            <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:10px;">
              <button class="btn btn-secondary" type="button" onclick="closeContractEditor()">Cancel</button>
              <button class="btn btn-primary" type="submit" style="width:auto;">Save Changes</button>
            </div>
          </form>
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

function editContract(contract) {
  document.getElementById('editContractId').value = contract.id;
  $('#editTenantSelect').val(contract.tenant_id).trigger('change');
  $('#editStallSelect').val(contract.stall_id).trigger('change');
  document.getElementById('editStatusSelect').value = contract.status;
  document.getElementById('editDepositInput').value = contract.deposit_amount;
  document.getElementById('editDurationType').value = contract.duration_type;
  document.getElementById('editStartDate').value = contract.start_date;
  document.getElementById('editEndDate').value = contract.end_date;
  document.getElementById('editTermsInput').value = contract.terms || '';
  document.getElementById('contractEditOverlay').classList.add('show');
}

function closeContractEditor() {
  document.getElementById('contractEditOverlay').classList.remove('show');
}

function handleContractOverlayClick(event) {
  if (event.target.id === 'contractEditOverlay') closeContractEditor();
}

function viewContract(c) {
  Swal.fire({
    title: 'Contract Details',
    html: `
      <div style="text-align:left; line-height:1.8;">
        <p><b>Tenant:</b> ${c.tenant_name}</p>
        <p><b>Business:</b> ${c.business_name}</p>
        <p><b>Stall:</b> ${c.stall_number}</p>
        <p><b>Validity:</b> ${c.start_date} to ${c.end_date}</p>
        <p><b>Monthly Rent:</b> ₱${Number(c.monthly_rent).toLocaleString()}</p>
        <p><b>Security Deposit:</b> ₱${Number(c.deposit_amount).toLocaleString()}</p>
        <p><b>Status:</b> ${c.status.toUpperCase()}</p>
        <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
        <p><b>Terms:</b><br><span style="color:#666;">${c.terms || 'No special terms provided.'}</span></p>
      </div>
    `,
    icon: 'info',
    confirmButtonColor: '#ff2d55'
  });
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

function handleContractSubmit(e) {
    e.preventDefault();
    const rent = document.getElementById('rentInput').value;
    if (!rent || parseFloat(rent) <= 0) {
        Swal.fire({ icon: 'error', title: 'Invalid Rent', text: 'Monthly rent must be greater than zero.' });
        return false;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: 'Are you sure you want to create this contract?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ff2d55',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, create it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('contractForm').submit();
        }
    });
    return false;
}

function updateEndDate() {
    const startDateVal = document.getElementById('startDate').value;
    if (!startDateVal) return;
    
    const startDate = new Date(startDateVal);
    const duration = document.getElementById('durationType').value;
    
    let targetDate = new Date(startDate);
    if (duration === '1 year') {
        targetDate.setFullYear(startDate.getFullYear() + 1);
    } else if (duration === '2 years') {
        targetDate.setFullYear(startDate.getFullYear() + 2);
    } else if (duration === '3 years') {
        targetDate.setFullYear(startDate.getFullYear() + 3);
    } else if (duration === '6 months') {
        targetDate.setMonth(startDate.getMonth() + 6);
    }
    
    const yyyy = targetDate.getFullYear();
    let mm = targetDate.getMonth() + 1;
    let dd = targetDate.getDate();
    
    if (mm < 10) mm = '0' + mm;
    if (dd < 10) dd = '0' + dd;
    
    document.getElementById('endDate').value = yyyy + '-' + mm + '-' + dd;
}

$(document).ready(function() {
  $('#tenantSelect').select2({ placeholder: "-- Search Tenant --", allowClear: true });
  $('#stallSelect').select2({ placeholder: "-- Choose Available Stall --", allowClear: true }).on('change', function() {
    const rate = $(this).find(':selected').data('rate');
    $('#rentInput').val(rate || '').prop('readonly', true);
  });
  $('#editTenantSelect, #editStallSelect').select2({ dropdownParent: $('#contractEditOverlay') });
  $('#editStallSelect').on('change', function() {
    $('#editRentInput').val($(this).find(':selected').data('rate') || '');
  });
});

// --- SWEETALERT FLASH HANDLER ---
<?php 
$newContractId = $_SESSION['new_contract_id'] ?? 0;
unset($_SESSION['new_contract_id']);
?>
<?php if ($flash): ?>
  <?php if ($newContractId > 0 && strpos(strtolower($flash), "error") === false): ?>
    Swal.fire({
      icon: 'success',
      title: 'Contract Created!',
      text: '<?php echo htmlspecialchars($flash); ?>',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'View PDF',
      cancelButtonText: 'Close'
    }).then((result) => {
      if (result.isConfirmed) {
        window.open('print_contract.php?id=<?php echo $newContractId; ?>', '_blank');
      }
    });
  <?php else: ?>
    Swal.fire({
      icon: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "error" : "success"; ?>',
      title: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "Oops!" : "Perfect!"; ?>',
      text: '<?php echo htmlspecialchars($flash); ?>',
      confirmButtonColor: '#007bff'
    });
  <?php endif; ?>
<?php endif; ?>
</script>
</body>
</html>