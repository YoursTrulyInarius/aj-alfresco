<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$search = sanitize($_GET['search'] ?? '');
$editStall = null;

if ($editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM stalls WHERE id=?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editStall = $stmt->get_result()->fetch_assoc();
}

$query = "SELECT s.*, 
            (SELECT u.full_name FROM users u 
             JOIN contracts c ON u.id = c.tenant_id 
             WHERE c.stall_id = s.id AND c.status='active' LIMIT 1) as occupant_name
          FROM stalls s";
if ($search) {
    $query .= " WHERE s.stall_number LIKE '%$search%' OR s.stall_name LIKE '%$search%' OR s.location_description LIKE '%$search%'";
}
$query .= " ORDER BY s.id DESC";
$result = $conn->query($query);

function selected($a, $b) {
    return ($a === $b) ? 'selected' : '';
}

$statusVal = $editStall['status'] ?? 'available';

$adminId = (int)$_SESSION['user_id'];
$unread = notifUnreadCount($adminId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Stalls - Admin</title>
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
      background: rgba(15, 23, 42, 0.45);
      z-index: 1000;
      justify-content: center;
      align-items: center;
      backdrop-filter: blur(8px);
    }
    .modal-content {
      background: #fff;
      padding: 36px;
      border-radius: 24px;
      width: 95%;
      max-width: 680px;
      max-height: 85vh;
      overflow-y: auto;
      box-shadow: 0 25px 70px rgba(30, 41, 59, 0.15);
      position: relative;
      z-index: 1010;
      scrollbar-width: thin;
      scrollbar-color: var(--primary) var(--bg);
    }
    .modal-content::-webkit-scrollbar {
      width: 6px;
    }
    .modal-content::-webkit-scrollbar-track {
      background: var(--bg);
      border-radius: 10px;
    }
    .modal-content::-webkit-scrollbar-thumb {
      background-color: var(--primary);
      border-radius: 10px;
    }
    .modal-header {
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border);
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-header h2 {
      margin: 0;
      color: var(--secondary);
      font-size: 1.4rem;
      font-weight: 800;
      letter-spacing: -0.4px;
    }
    .close-modal {
      font-size: 28px;
      font-weight: 500;
      color: var(--muted);
      cursor: pointer;
      line-height: 1;
      transition: all 0.2s;
    }
    .close-modal:hover { color: var(--primary); transform: scale(1.15); }
    #stallForm {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }
    #stallForm .form-group {
      margin-bottom: 0;
    }
    #stallForm .full-width {
      grid-column: span 2;
    }
    #stallForm.is-create #statusFormGroup {
      display: none;
    }
    #stallForm.is-edit #statusFormGroup {
      display: block;
    }
    @media (max-width: 600px) {
      #stallForm {
        grid-template-columns: 1fr;
      }
      #stallForm .full-width {
        grid-column: span 1;
      }
    }
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
      <li><a class="active" href="stalls.php">Stalls</a></li>
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
        <h1>Stalls</h1>
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

      <!-- STALL FORM (MODAL) -->
      <div id="modalOverlay" class="modal-overlay <?php echo $editStall ? 'show' : ''; ?>" onclick="handleOverlayClick(event)">
        <div class="modal-content">
          <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
            <h2 id="modalTitle"><?php echo $editStall ? 'Edit Stall' : 'Add New Stall'; ?></h2>
            <span style="font-size:30px; cursor:pointer;" onclick="toggleForm()">&times;</span>
          </div>

          <form id="stallForm" class="<?php echo $editStall ? 'is-edit' : 'is-create'; ?>" method="POST" action="process_stall.php">
            <input type="hidden" name="action" value="<?php echo $editStall ? 'update' : 'create'; ?>">
            <input type="hidden" name="id" value="<?php echo (int)($editStall['id'] ?? 0); ?>">

            <div class="form-group">
              <label>Stall Number *</label>
              <input type="text" name="stall_number" required placeholder="e.g., S-001" value="<?php echo htmlspecialchars($editStall['stall_number'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
              <label>Stall Name (optional)</label>
              <input type="text" name="stall_name" placeholder="e.g., Food Stall A" value="<?php echo htmlspecialchars($editStall['stall_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group" id="monthlyRateGroup">
              <label>Monthly Rate (PHP) *</label>
              <input type="text" name="monthly_rate" required class="money-input" placeholder="0.00" value="<?php echo htmlspecialchars($editStall['monthly_rate'] ?? '0.00'); ?>">
            </div>
            
            <div class="form-group" id="statusFormGroup">
              <label>Status</label>
              <select name="status">
                <option value="available" <?php echo selected($statusVal, 'available'); ?>>Available</option>
                <option value="occupied" <?php echo selected($statusVal, 'occupied'); ?>>Occupied</option>
                <option value="maintenance" <?php echo selected($statusVal, 'maintenance'); ?>>Maintenance</option>
              </select>
            </div>

            <div class="form-group full-width">
              <label>Location Description *</label>
              <textarea name="location_description" required rows="2" placeholder="e.g. Ground Floor, Left Wing" style="width: 100%; padding: 11px 14px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: #fafbfc; font-size: 14px; outline: none; transition: border-color .2s ease, box-shadow .2s ease; font-family: 'Inter'; color: var(--text); resize: none;"><?php echo htmlspecialchars($editStall['location_description'] ?? ''); ?></textarea>
            </div>

            <div class="full-width" style="display: flex; gap: 12px; margin-top: 10px; justify-content: flex-end;">
              <button class="btn btn-primary" type="submit" style="width:auto; padding: 12px 28px;">
                <?php echo $editStall ? 'Update Stall' : 'Create Stall'; ?>
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>Management</h2>
        <button class="btn btn-success" style="width:auto;" onclick="toggleForm()">➕ Add New Stall</button>
      </div>

      <!-- STALL LIST TABLE -->
      <div class="card">
        <div class="card-header">
          <h2>Stall List</h2>
          <form method="GET" style="display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search stall # or location..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" class="btn btn-primary btn-sm" style="width:auto;">Search</button>
          </form>
        </div>

        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Stall #</th>
                <th>Name / Location</th>
                <th>Occupant</th>
                <th>Monthly Rate</th>
                <th>Status</th>
                <th style="width:190px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($row['stall_number']); ?></strong></td>
                  <td>
                    <?php echo htmlspecialchars($row['stall_name'] ?? 'No Name'); ?><br>
                    <small><?php echo htmlspecialchars($row['location_description'] ?? ''); ?></small>
                  </td>
                  <td>
                    <?php if ($row['occupant_name']): ?>
                      <strong>👤 <?php echo htmlspecialchars($row['occupant_name']); ?></strong>
                    <?php else: ?>
                      <span style="color:#aaa;">Available</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo formatMoney($row['monthly_rate']); ?></td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($row['status']); ?>">
                      <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:8px;">
                      <a class="btn btn-info btn-sm" href="javascript:void(0)" onclick='viewStall(<?php echo htmlspecialchars(json_encode($row)); ?>)' title="View Details">👁️</a>
                      <a class="btn btn-warning btn-sm" href="javascript:void(0)" onclick='editStallInPlace(<?php echo htmlspecialchars(json_encode($row)); ?>)' title="Edit Stall">✏️</a>
                      <form method="POST" action="process_stall.php" onsubmit="return confirmDelete(event, this)" style="display:inline-block; margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit" title="Delete Stall">🗑️</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>

              <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="5">No stalls found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
function toggleForm() {
    const f = document.getElementById('modalOverlay');
    const b = document.body;
    f.classList.toggle('show');
    
    if (f.classList.contains('show')) {
        b.style.overflow = 'hidden'; 
    } else {
        b.style.overflow = 'auto';
        // Reset form
        const form = document.getElementById('stallForm');
        form.reset();
        form.querySelector('input[name="action"]').value = 'create';
        form.querySelector('input[name="id"]').value = '0';
        document.getElementById('modalTitle').textContent = 'Add New Stall';
        form.querySelector('button[type="submit"]').textContent = 'Create Stall';
        
        form.classList.remove('is-edit');
        form.classList.add('is-create');
        
        const statusGroup = document.getElementById('statusFormGroup');
        if (statusGroup) statusGroup.style.display = 'none';
    }
}

function handleOverlayClick(e) {
    if (e.target.id === 'modalOverlay') {
        toggleForm();
    }
}

function viewStall(s) {
  Swal.fire({
    title: 'Stall Details',
    html: `
      <div style="text-align:left; line-height:1.8;">
        <p><b>Stall Number:</b> ${s.stall_number}</p>
        <p><b>Name:</b> ${s.stall_name || 'No Name'}</p>
        <p><b>Location:</b> ${s.location_description}</p>
        <p><b>Monthly Rate:</b> ₱${Number(s.monthly_rate).toLocaleString()}</p>
        <p><b>Current Status:</b> ${s.status.toUpperCase()}</p>
        <p><b>Occupant:</b> ${s.occupant_name || 'Available'}</p>
      </div>
    `,
    icon: 'info',
    confirmButtonColor: '#ff2d55'
  });
}

function editStallInPlace(s) {
  toggleForm();
  document.getElementById('modalTitle').textContent = 'Edit Stall Details';
  const form = document.getElementById('stallForm');
  form.querySelector('input[name="action"]').value = 'update';
  form.querySelector('input[name="id"]').value = s.id;
  
  form.querySelector('input[name="stall_number"]').value = s.stall_number;
  form.querySelector('input[name="stall_name"]').value = s.stall_name;
  form.querySelector('input[name="monthly_rate"]').value = s.monthly_rate;
  form.querySelector('select[name="status"]').value = s.status;
  form.querySelector('textarea[name="location_description"]').value = s.location_description;
  
  form.querySelector('button[type="submit"]').textContent = 'Update Stall';
  formatMoneyInput(form.querySelector('input[name="monthly_rate"]'));

  form.classList.remove('is-create');
  form.classList.add('is-edit');

  const statusGroup = document.getElementById('statusFormGroup');
  if (statusGroup) statusGroup.style.display = 'block';
}

function confirmDelete(e, form) {
  e.preventDefault();
  Swal.fire({
    title: 'Are you sure?',
    text: "Deleting this stall will impact active contracts!",
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

$(document).ready(function() {
  // Money input formatting
  $('.money-input').on('blur', function() {
    formatMoneyInput(this);
  }).on('focus', function() {
    this.value = this.value.replace(/,/g, '');
  }).each(function() {
    formatMoneyInput(this);
  });

  <?php if ($flash): ?>
  Swal.fire({
    icon: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "error" : "success"; ?>',
    title: '<?php echo (strpos(strtolower($flash), "error") !== false) ? "Oops!" : "Done!"; ?>',
    text: '<?php echo htmlspecialchars($flash); ?>',
    confirmButtonColor: '#ff2d55'
  });
  <?php endif; ?>
});
</script>
</body>
</html>