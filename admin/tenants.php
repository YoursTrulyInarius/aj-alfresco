<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$search = sanitize($_GET['search'] ?? '');
$editTenant = null;

if ($editId > 0) {
    $stmt = $conn->prepare("
        SELECT u.*, 
            (SELECT s.id FROM stalls s JOIN contracts c ON s.id = c.stall_id WHERE c.tenant_id = u.id AND c.status='active' LIMIT 1) as current_stall_id,
            (SELECT s.stall_number FROM stalls s JOIN contracts c ON s.id = c.stall_id WHERE c.tenant_id = u.id AND c.status='active' LIMIT 1) as current_stall_no
        FROM users u WHERE u.id=? AND u.role='tenant'
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editTenant = $stmt->get_result()->fetch_assoc();
}

$allStalls = $conn->query("SELECT id, stall_number, status FROM stalls ORDER BY stall_number");

$query = "SELECT u.*, (SELECT stall_number FROM stalls s JOIN contracts c ON s.id = c.stall_id WHERE c.tenant_id = u.id AND c.status='active' LIMIT 1) as stall_no 
          FROM users u WHERE u.role='tenant'";
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR u.business_name LIKE '%$search%')";
}
$query .= " ORDER BY u.id DESC";
$result = $conn->query($query);

$adminId = (int)$_SESSION['user_id'];
$unread = notifUnreadCount($adminId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="../assets/css/style.css?v=6"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
      backdrop-filter: blur(4px);
    }
    .modal-content {
      background: #fff;
      padding: 30px;
      border-radius: 24px;
      width: 95%;
      max-width: 900px;
      max-height: 85vh;
      overflow-y: auto;
      box-shadow: 0 15px 50px rgba(0,0,0,0.3);
      position: relative;
      z-index: 1010;
      scrollbar-width: thin;
      scrollbar-color: #d63384 #f8f9fa;
    }
    .modal-content::-webkit-scrollbar {
      width: 8px;
    }
    .modal-content::-webkit-scrollbar-track {
      background: #f8f9fa;
      border-radius: 10px;
    }
    .modal-content::-webkit-scrollbar-thumb {
      background-color: #d63384;
      border-radius: 10px;
    }
    .close-modal {
      font-size: 32px;
      font-weight: bold;
      color: #999;
      cursor: pointer;
      line-height: 1;
      padding: 0 10px;
      z-index: 1020;
      position: relative;
    }
    .close-modal:hover { color: #d63384; transform: rotate(90deg); transition: all 0.3s; }
    #tenantFormContainer { padding: 0 !important; border: none !important; box-shadow: none !important; margin: 0 !important; }
    .modal-header { padding-bottom: 20px; border-bottom: 1px solid #eee; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { margin: 0; color: #333; font-size: 1.5rem; }
  </style>
</head>
<body>
<div class="dashboard">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="logo">🏪</span>
      <h2>A&J Alfresco</h2>
      <p>Admin Panel</p>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
      <li><a class="active" href="tenants.php"><span class="icon">👥</span> Tenants</a></li>
      <li><a href="stalls.php"><span class="icon">🏬</span> Stalls</a></li>
      <li><a href="contracts.php"><span class="icon">📄</span> Contracts</a></li>
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
        <h1>Manage Tenants</h1>
      </div>
      <div class="header-right" style="display:flex; align-items:center; gap:15px;">
        <button class="btn btn-success" style="width:auto; padding: 10px 20px; font-weight:bold; border-radius:12px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);" onclick="toggleForm()">➕ Add New Tenant</button>
        <div class="user-info">
          <div class="avatar"></div>
          <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
      </div>
    </div>

    <div class="content">

      <!-- TENANT LIST TABLE (NOW ON TOP) -->
      <div class="card" style="margin-bottom: 25px;">
        <div class="card-header">
          <h2>Registered Tenants</h2>
          <form method="GET" style="display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search name or business..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 8px 15px; border:1px solid #eee; border-radius:10px; width:250px;">
            <button type="submit" class="btn btn-primary btn-sm" style="width:auto;">Search</button>
          </form>
        </div>
        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Tenant Info</th>
                <th>Business</th>
                <th>Stall #</th>
                <th>Status</th>
                <th style="width:160px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                    <small><?php echo htmlspecialchars($row['email']); ?></small><br>
                    <small><?php echo htmlspecialchars($row['phone']); ?></small>
                  </td>
                  <td>
                    <?php echo htmlspecialchars($row['business_name']); ?><br>
                    <small><?php echo htmlspecialchars($row['business_type']); ?></small>
                  </td>
                  <td><?php echo $row['stall_no'] ?: '<span style="color:#aaa;">None</span>'; ?></td>
                  <td>
                    <span class="status-badge <?php echo ($row['status'] === 'active' ? 'active' : 'overdue'); ?>">
                      <?php echo strtoupper($row['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:8px;">
                      <a class="btn btn-info btn-sm" href="javascript:void(0)" onclick='viewTenant(<?php echo htmlspecialchars(json_encode($row)); ?>)' title="View Details">👁️</a>
                      <a class="btn btn-warning btn-sm" href="javascript:void(0)" onclick='editTenantInPlace(<?php echo htmlspecialchars(json_encode($row)); ?>)' title="Edit Tenant">✏️</a>
                      <form method="POST" action="process_tenant.php" onsubmit="return confirmDelete(event, this)" style="display:inline-block; margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit" title="Delete Tenant">🗑️</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
              <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="5">No tenants found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- REGISTRATION MODAL -->
      <div id="modalOverlay" class="modal-overlay <?php echo $editTenant ? 'show' : ''; ?>" onclick="handleOverlayClick(event)">
        <div class="modal-content">
          <div class="modal-header">
            <h2><?php echo $editTenant ? 'Edit' : 'Register New'; ?> Tenant</h2>
            <span class="close-modal" onclick="toggleForm()">&times;</span>
          </div>
          <div id="tenantFormContainer">
            <div class="card-body" style="padding:0;">
          <form method="POST" action="process_tenant.php" onsubmit="return validateTenantForm()">
            <input type="hidden" name="action" value="<?php echo $editTenant ? 'update' : 'create'; ?>">
            <input type="hidden" name="id" value="<?php echo $editTenant['id'] ?? 0; ?>">

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
              <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" required pattern="[A-Za-z\s]+" title="Letters only" value="<?php echo htmlspecialchars($editTenant['full_name'] ?? ''); ?>" placeholder="John Doe">
              </div>

              <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($editTenant['email'] ?? ''); ?>" placeholder="tenant@example.com">
              </div>

              <div class="form-group">
                <label>Business Name *</label>
                <input type="text" name="business_name" required value="<?php echo htmlspecialchars($editTenant['business_name'] ?? ''); ?>" placeholder="Tasty Food Stall">
              </div>

              <div class="form-group">
                <label>Type of Business *</label>
                <input type="text" name="business_type" required value="<?php echo htmlspecialchars($editTenant['business_type'] ?? ''); ?>" placeholder="Fast Food / Beverage">
              </div>

              <div class="form-group">
                <label>Phone *</label>
                <input type="text" name="phone" required maxlength="11" 
                       oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11)" 
                       value="<?php echo htmlspecialchars($editTenant['phone'] ?? ''); ?>" 
                       placeholder="09171234567">
              </div>

              <div class="form-group">
                <label>Status</label>
                <select name="status">
                  <option value="active" <?php echo (($editTenant['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                  <option value="inactive" <?php echo (($editTenant['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
              </div>

              <div class="form-group">
                <label>Assigned Stall *</label>
                <select name="stall_id" required>
                  <option value="">-- Choose Assigned Stall --</option>
                  <?php 
                  $allStalls->data_seek(0);
                  while($s = $allStalls->fetch_assoc()): 
                    $isMyStall = ($editTenant && $s['id'] == ($editTenant['current_stall_id'] ?? 0));
                    $isTaken = ($s['status'] !== 'available' && !$isMyStall);
                  ?>
                    <option value="<?php echo $s['id']; ?>" 
                      <?php echo $isMyStall ? 'selected' : ''; ?>
                      <?php echo $isTaken ? 'disabled' : ''; ?>>
                      <?php echo htmlspecialchars($s['stall_number']); ?> 
                      <?php echo $isTaken ? '('.ucfirst($s['status']).')' : ''; ?>
                      <?php echo $isMyStall ? '(Current)' : ''; ?>
                    </option>
                  <?php endwhile; ?>
                </select>
                <?php if ($editTenant): ?>
                  <input type="hidden" name="original_stall_id" value="<?php echo $editTenant['current_stall_id'] ?? 0; ?>">
                <?php endif; ?>
                <small style="color:#666;">
                  <?php echo $editTenant ? 'Changing stall will update current contract.' : 'Choosing a stall auto-creates a contract starting today.'; ?>
                </small>
              </div>
            </div>

            <div class="form-group">
              <label>Address *</label>
              <textarea name="address" required rows="2" placeholder="Full Address"><?php echo htmlspecialchars($editTenant['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-group" style="max-width:300px;">
              <label><?php echo $editTenant ? 'New Password (optional)' : 'Password *'; ?></label>
              <div class="password-wrap">
                <input id="passwordInput" type="password" name="password" <?php echo $editTenant ? '' : 'required'; ?> 
                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}" 
                       title="Must contain at least one number, one uppercase, one lowercase letter, one special character, and 8+ characters"
                       placeholder="Enter strong password">
                <button class="pw-toggle" type="button" onclick="togglePassword()">Show</button>
              </div>
            </div>

            <button class="btn btn-primary" type="submit" style="width:auto; margin-top:10px;">
              <?php echo $editTenant ? '💾 Update Account' : '➕ Create Tenant Account'; ?>
            </button>
            <?php if($editTenant): ?>
              <a href="tenants.php" class="btn btn-secondary" style="display:inline-block; margin-left:10px; text-decoration:none;">Cancel</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
          </div>
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

function toggleForm() {
    const f = document.getElementById('modalOverlay');
    const b = document.body;
    f.classList.toggle('show');
    
    if (f.classList.contains('show')) {
        b.style.overflow = 'hidden'; 
    } else {
        b.style.overflow = 'auto';
        // Reset form for "Add New"
        const form = document.querySelector('#tenantFormContainer form');
        form.reset();
        form.querySelector('input[name="action"]').value = 'create';
        form.querySelector('input[name="id"]').value = '0';
        form.querySelector('button[type="submit"]').textContent = '➕ Create Tenant Account';
        form.querySelector('h2').textContent = 'Register New Tenant';
    }
}

function viewTenant(t) {
  Swal.fire({
    title: 'Tenant Profile',
    html: `
      <div style="text-align:left; line-height:1.8;">
        <p><b>Name:</b> ${t.full_name}</p>
        <p><b>Business:</b> ${t.business_name} (${t.business_type})</p>
        <p><b>Stall:</b> ${t.stall_no || 'None'}</p>
        <p><b>Email:</b> ${t.email}</p>
        <p><b>Phone:</b> ${t.phone}</p>
        <p><b>Address:</b> ${t.address}</p>
        <p><b>Status:</b> ${t.status.toUpperCase()}</p>
      </div>
    `,
    icon: 'info',
    confirmButtonColor: '#ff2d55'
  });
}

function editTenantInPlace(t) {
  toggleForm();
  const form = document.querySelector('#tenantFormContainer form');
  form.querySelector('h2').textContent = 'Edit Tenant Details';
  form.querySelector('input[name="action"]').value = 'update';
  form.querySelector('input[name="id"]').value = t.id;
  
  form.querySelector('input[name="full_name"]').value = t.full_name;
  form.querySelector('input[name="email"]').value = t.email;
  form.querySelector('input[name="business_name"]').value = t.business_name;
  form.querySelector('input[name="business_type"]').value = t.business_type;
  form.querySelector('input[name="phone"]').value = t.phone;
  form.querySelector('select[name="status"]').value = t.status;
  form.querySelector('select[name="stall_id"]').value = t.current_stall_id || '';
  form.querySelector('textarea[name="address"]').value = t.address;
  
  form.querySelector('button[type="submit"]').textContent = '💾 Update Account';
}

function confirmDelete(e, form) {
  e.preventDefault();
  Swal.fire({
    title: 'Are you sure?',
    text: "Deleting this tenant will also deactivate their contracts!",
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

function handleOverlayClick(e) {
    if (e.target.id === 'modalOverlay') {
        toggleForm();
    }
}

function togglePassword(){
  const input = document.getElementById('passwordInput');
  const btn = document.querySelector('.pw-toggle');
  input.type = (input.type === 'password') ? 'text' : 'password';
  btn.textContent = (input.type === 'password') ? 'Show' : 'Hide';
}

function validateTenantForm() {
  const phone = document.querySelector('input[name="phone"]').value;
  if (phone.length !== 11) {
    Swal.fire({ icon: 'error', title: 'Invalid Phone Number', text: 'Phone number must be exactly 11 digits.' });
    return false;
  }
  return true;
}

// --- SWEETALERT FLASH HANDLER ---
<?php if ($flash): ?>
  Swal.fire({
    icon: '<?php echo (strpos(strtolower($flash), 'error') !== false || strpos(strtolower($flash), 'exists') !== false) ? "error" : "success"; ?>',
    title: '<?php echo (strpos(strtolower($flash), 'error') !== false || strpos(strtolower($flash), 'exists') !== false) ? "Oops!" : "Success!"; ?>',
    text: '<?php echo htmlspecialchars($flash); ?>',
    confirmButtonColor: '#d63384'
  });
<?php endif; ?>
</script>
</body>
</html>