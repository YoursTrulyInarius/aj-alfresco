<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$search = sanitize($_GET['search'] ?? '');
$editTenant = null;

if ($editId > 0) {
    $stmt = $conn->prepare("SELECT u.* FROM users u WHERE u.id=? AND u.role='tenant'");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editTenant = $stmt->get_result()->fetch_assoc();
}

$query = "SELECT u.*, (SELECT stall_number FROM stalls s JOIN contracts c ON s.id = c.stall_id WHERE c.tenant_id = u.id AND c.status='active' LIMIT 1) as stall_no,
          EXISTS (
            SELECT 1
            FROM contracts overdue_contract
            WHERE overdue_contract.tenant_id = u.id
              AND overdue_contract.status = 'active'
              AND overdue_contract.start_date <= CURDATE()
              AND DAY(CURDATE()) > LEAST(DAY(overdue_contract.start_date), DAY(LAST_DAY(CURDATE())))
              AND NOT EXISTS (
                SELECT 1 FROM payments overdue_payment
                WHERE overdue_payment.contract_id = overdue_contract.id
                  AND overdue_payment.payment_for_month = DATE_FORMAT(CURDATE(), '%Y-%m')
                  AND overdue_payment.status = 'paid'
              )
          ) AS is_overdue
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
  <link rel="stylesheet" href="../assets/css/style.css?v=9"/>
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
    .tenant-profile-popup { padding: 0 !important; overflow: hidden; border-radius: 18px !important; }
    .tenant-profile { text-align: left; color: var(--text); }
    .tenant-profile-header { display: flex; align-items: center; gap: 16px; padding: 24px 28px; background: linear-gradient(135deg, #fff1f7, #fff); border-bottom: 1px solid var(--border); }
    .tenant-profile-avatar { width: 58px; height: 58px; flex: 0 0 58px; display: grid; place-items: center; border-radius: 16px; color: #fff; background: var(--primary); font-size: 22px; font-weight: 800; box-shadow: 0 8px 18px var(--primary-shadow); }
    .tenant-profile-header h2 { margin: 0 0 4px; color: var(--secondary); font-size: 20px; line-height: 1.2; }
    .tenant-profile-header p { margin: 0; color: var(--muted); font-size: 13px; }
    .tenant-profile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 24px; padding: 24px 28px 26px; }
    .tenant-profile-item { min-width: 0; }
    .tenant-profile-label { display: block; margin-bottom: 5px; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .tenant-profile-value { display: block; overflow-wrap: anywhere; color: var(--secondary); font-size: 14px; font-weight: 600; line-height: 1.45; }
    .tenant-profile-value.status { display: inline-flex; width: fit-content; }
    .tenant-profile-item.full { grid-column: 1 / -1; }
    .tenant-profile-popup .swal2-actions { margin: 0; padding: 0 28px 24px; justify-content: flex-end; }
    .tenant-profile-popup .swal2-confirm { margin: 0 !important; border-radius: 9px; padding: 10px 20px; font-size: 13px; font-weight: 700; }
    @media (max-width: 560px) {
      .tenant-profile-grid { grid-template-columns: 1fr; gap: 16px; padding: 20px; }
      .tenant-profile-header { padding: 20px; }
      .tenant-profile-item.full { grid-column: auto; }
      .tenant-profile-popup .swal2-actions { padding: 0 20px 20px; }
    }
  </style>
</head>
<body>
<div class="dashboard">
  <!-- SIDEBAR -->
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
      <li><a class="active" href="tenants.php">Tenants</a></li>
      <li><a href="stalls.php">Stalls</a></li>
      <li><a href="contracts.php">Contracts</a></li>
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
        <h1>Manage Tenants</h1>
      </div>
      <div class="header-right" style="display:flex; align-items:center; gap:15px;">
        <button class="btn btn-success" style="width:auto; padding: 10px 20px; font-weight:bold; border-radius:12px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);" onclick="toggleForm()"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add New Tenant</button>
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
                    <small><?php echo htmlspecialchars($row['phone']); ?></small><br>
                    <small><?php echo htmlspecialchars($row['secondary_phone'] ?? ''); ?></small>
                  </td>
                  <td>
                    <?php echo htmlspecialchars($row['business_name']); ?><br>
                    <small><?php echo htmlspecialchars($row['business_type']); ?></small>
                  </td>
                  <td><?php echo $row['stall_no'] ?: '<span style="color:#aaa;">None</span>'; ?></td>
                  <td>
                    <span class="status-badge <?php echo (int)$row['is_overdue'] === 1 ? 'overdue' : ($row['status'] === 'active' ? 'active' : 'overdue'); ?>">
                      <?php echo (int)$row['is_overdue'] === 1 ? 'OVERDUE' : strtoupper($row['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:8px; align-items:center;">
                      <a class="btn btn-info btn-action" href="javascript:void(0)" onclick='viewTenant(<?php echo htmlspecialchars(json_encode($row)); ?>)' title="View tenant details" aria-label="View tenant details"><i class="fa-solid fa-eye" aria-hidden="true"></i></a>
                      <a class="btn btn-warning btn-action" href="javascript:void(0)" onclick='editTenantInPlace(<?php echo htmlspecialchars(json_encode($row)); ?>)' title="Edit tenant" aria-label="Edit tenant"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                      <form method="POST" action="process_tenant.php" onsubmit="return confirmDelete(event, this)" style="display:inline-block; margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <button class="btn btn-danger btn-action" type="submit" title="Delete tenant" aria-label="Delete tenant"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
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

                <!-- SECTION 1: Tenant Profile -->
                <div class="form-section-title">Personal Profile</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                  <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required pattern="[A-Za-z\s]+" title="Letters only" value="<?php echo htmlspecialchars($editTenant['full_name'] ?? ''); ?>" placeholder="John Doe">
                  </div>

                  <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($editTenant['email'] ?? ''); ?>" placeholder="tenant@example.com">
                  </div>

                  <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="text" name="phone" required maxlength="11" 
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11)" 
                           value="<?php echo htmlspecialchars($editTenant['phone'] ?? ''); ?>" 
                           placeholder="09171234567">
                  </div>
                </div>

                <!-- SECTION 2: Business & Contact -->
                <div class="form-section-title">Business & Contact</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                  <div class="form-group">
                    <label>Business Name *</label>
                    <input type="text" name="business_name" required value="<?php echo htmlspecialchars($editTenant['business_name'] ?? ''); ?>" placeholder="Tasty Food Stall">
                  </div>

                  <div class="form-group">
                    <label>Type of Business *</label>
                    <input type="text" name="business_type" required value="<?php echo htmlspecialchars($editTenant['business_type'] ?? ''); ?>" placeholder="Fast Food / Beverage">
                  </div>

                  <div class="form-group">
                    <label>Secondary Contact Number</label>
                    <input type="text" name="secondary_phone" maxlength="11"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11)"
                           value="<?php echo htmlspecialchars($editTenant['secondary_phone'] ?? ''); ?>"
                           placeholder="09123456789">
                  </div>

                  <input type="hidden" name="status" id="statusInputVal" value="<?php echo htmlspecialchars($editTenant['status'] ?? 'active'); ?>">
                </div>

                <!-- SECTION 3: Address & Security -->
                <div class="form-section-title">Address & Credentials</div>
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 24px;">
                  <div class="form-group">
                    <label>Address *</label>
                    <textarea name="address" required rows="2" placeholder="Full Address" style="width: 100%; padding: 11px 14px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: #fafbfc; font-size: 14px; outline: none; transition: border-color .2s ease, box-shadow .2s ease; font-family: 'Inter'; color: var(--text); resize: none;"><?php echo htmlspecialchars($editTenant['address'] ?? ''); ?></textarea>
                  </div>

                  <div class="form-group">
                    <label><?php echo $editTenant ? 'New Password (optional)' : 'Password *'; ?></label>
                    <div class="password-wrap">
                      <input id="passwordInput" type="password" name="password" <?php echo $editTenant ? '' : 'required'; ?> 
                             pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}" 
                             title="Must contain at least one number, one uppercase, one lowercase letter, one special character, and 8+ characters"
                             placeholder="Enter strong password">
                      <button class="pw-toggle" type="button" onclick="togglePassword()">Show</button>
                    </div>
                  </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end;">
                  <?php if($editTenant): ?>
                    <a href="tenants.php" class="btn btn-secondary" style="padding: 12px 24px;">Cancel</a>
                  <?php endif; ?>
                  <button class="btn btn-primary" type="submit" style="width: auto; padding: 12px 28px;">
                    <?php echo $editTenant ? 'Update Account' : 'Register Tenant Account'; ?>
                  </button>
                </div>
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
        form.querySelector('button[type="submit"]').textContent = 'Register Tenant Account';
        
        const heading = document.querySelector('.modal-header h2');
        if (heading) heading.textContent = 'Register New Tenant';
        
        const statusInput = form.querySelector('input[name="status"]');
        if (statusInput) statusInput.value = 'active';
    }
}

function viewTenant(t) {
  const escapeHtml = function (value) {
    return String(value ?? '').replace(/[&<>'"]/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character];
    });
  };
  const name = escapeHtml(t.full_name || 'Tenant');
  const initials = escapeHtml((t.full_name || 'T').trim().split(/\s+/).map(function (part) { return part[0]; }).join('').substring(0, 2).toUpperCase());
  const business = escapeHtml(t.business_name || 'No business recorded');
  const businessType = escapeHtml(t.business_type || '');
  const isOverdue = Number(t.is_overdue) === 1;
  const displayStatus = isOverdue ? 'OVERDUE' : (t.status || '').toUpperCase();
  const statusClass = isOverdue ? 'overdue' : (t.status || '');
  const status = escapeHtml(displayStatus);

  Swal.fire({
    title: '',
    html: `
      <div class="tenant-profile">
        <div class="tenant-profile-header">
          <div class="tenant-profile-avatar">${initials}</div>
          <div><h2>${name}</h2><p>${business}${businessType ? ` · ${businessType}` : ''}</p></div>
        </div>
        <div class="tenant-profile-grid">
          <div class="tenant-profile-item"><span class="tenant-profile-label">Stall</span><span class="tenant-profile-value">${escapeHtml(t.stall_no || 'Unassigned')}</span></div>
          <div class="tenant-profile-item"><span class="tenant-profile-label">Status</span><span class="tenant-profile-value status status-badge ${escapeHtml(statusClass)}">${status}</span></div>
          <div class="tenant-profile-item"><span class="tenant-profile-label">Email</span><span class="tenant-profile-value">${escapeHtml(t.email || 'Not provided')}</span></div>
          <div class="tenant-profile-item"><span class="tenant-profile-label">Phone</span><span class="tenant-profile-value">${escapeHtml(t.phone || 'Not provided')}</span></div>
          <div class="tenant-profile-item"><span class="tenant-profile-label">Secondary contact</span><span class="tenant-profile-value">${escapeHtml(t.secondary_phone || 'Not provided')}</span></div>
          <div class="tenant-profile-item full"><span class="tenant-profile-label">Address</span><span class="tenant-profile-value">${escapeHtml(t.address || 'Not provided')}</span></div>
        </div>
      </div>
    `,
    width: 560,
    showCloseButton: true,
    showConfirmButton: true,
    confirmButtonText: 'Close',
    confirmButtonColor: '#d63384',
    customClass: { popup: 'tenant-profile-popup' }
  });
}

function editTenantInPlace(t) {
  toggleForm();
  const form = document.querySelector('#tenantFormContainer form');
  form.querySelector('input[name="action"]').value = 'update';
  form.querySelector('input[name="id"]').value = t.id;
  
  form.querySelector('input[name="full_name"]').value = t.full_name;
  form.querySelector('input[name="email"]').value = t.email;
  form.querySelector('input[name="business_name"]').value = t.business_name;
  form.querySelector('input[name="business_type"]').value = t.business_type;
  form.querySelector('input[name="phone"]').value = t.phone;
  form.querySelector('input[name="secondary_phone"]').value = t.secondary_phone || '';
  
  const statusInput = form.querySelector('input[name="status"]');
  if (statusInput) statusInput.value = t.status;
  
  form.querySelector('textarea[name="address"]').value = t.address;
  
  form.querySelector('button[type="submit"]').textContent = 'Update Account';
  
  const heading = document.querySelector('.modal-header h2');
  if (heading) heading.textContent = 'Edit Tenant Details';
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
  const secondaryPhone = document.querySelector('input[name="secondary_phone"]').value;
  if (secondaryPhone && secondaryPhone.length !== 11) {
    Swal.fire({ icon: 'error', title: 'Invalid Secondary Contact', text: 'Secondary contact number must be exactly 11 digits.' });
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