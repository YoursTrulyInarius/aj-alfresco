<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$search = sanitize($_GET['search'] ?? '');
$editTenant = null;

if ($editId > 0) {
    $stmt = $conn->prepare("SELECT id, full_name, email, phone, status, address, business_name, business_type FROM users WHERE id=? AND role='tenant'");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editTenant = $stmt->get_result()->fetch_assoc();
}

$query = "SELECT u.*, (SELECT stall_number FROM stalls s JOIN contracts c ON s.id = c.stall_id WHERE c.tenant_id = u.id AND c.status='active' LIMIT 1) as stall_no 
          FROM users u WHERE u.role='tenant'";
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR u.business_name LIKE '%$search%')";
}
$query .= " ORDER BY u.id DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tenants - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=2"/>
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
      <li><a class="active" href="tenants.php"><span class="icon">👥</span> Tenants</a></li>
      <li><a href="stalls.php"><span class="icon">🏬</span> Stalls</a></li>
      <li><a href="contracts.php"><span class="icon">📄</span> Contracts</a></li>
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
        <h1>Tenants</h1>
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
          <h2><?php echo $editTenant ? 'Edit Tenant' : 'Add Tenant'; ?></h2>
        </div>
        <div class="card-body">
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
                <input type="text" name="phone" required pattern="[0-9]+" title="Numbers only" value="<?php echo htmlspecialchars($editTenant['phone'] ?? ''); ?>" placeholder="09171234567">
              </div>

              <div class="form-group">
                <label>Status</label>
                <select name="status">
                  <option value="active" <?php echo (($editTenant['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                  <option value="inactive" <?php echo (($editTenant['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Address *</label>
              <textarea name="address" required rows="2" placeholder="Full Address"><?php echo htmlspecialchars($editTenant['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
              <label><?php echo $editTenant ? 'New Password (leave blank to keep current)' : 'Password *'; ?></label>
              <div class="password-wrap">
                <input id="passwordInput" type="password" name="password" <?php echo $editTenant ? '' : 'required'; ?> 
                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}" 
                       title="Must contain at least one number, one uppercase, one lowercase letter, one special character, and 8+ characters"
                       placeholder="Enter strong password">
                <button class="pw-toggle" type="button" onclick="togglePassword()">Show</button>
              </div>
            </div>

            <button class="btn btn-primary" type="submit">
              <?php echo $editTenant ? 'Update Tenant' : 'Create Tenant Account'; ?>
            </button>

            <?php if ($editTenant): ?>
              <div style="margin-top:10px;">
                <a class="btn btn-warning btn-sm" href="tenants.php">Cancel Edit</a>
              </div>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2>Tenant List</h2>
          <form method="GET" style="display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search name or business..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 6px 12px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" class="btn btn-primary btn-sm" style="width:auto;">Search</button>
          </form>
        </div>
        <div class="card-body table-responsive">
          <table>
            <thead>
              <tr>
                <th>Name / Business</th>
                <th>Stall</th>
                <th>Contact</th>
                <th>Status</th>
                <th style="width:180px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                    <small><?php echo htmlspecialchars($row['business_name']); ?> (<?php echo htmlspecialchars($row['business_type']); ?>)</small>
                  </td>
                  <td>
                    <?php if ($row['stall_no']): ?>
                      <span class="status-badge occupied"><?php echo htmlspecialchars($row['stall_no']); ?></span>
                    <?php else: ?>
                      <small>No Stall</small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <small><?php echo htmlspecialchars($row['email']); ?></small><br>
                    <small><?php echo htmlspecialchars($row['phone']); ?></small>
                  </td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($row['status']); ?>">
                      <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:5px;">
                      <a class="btn btn-warning btn-sm" href="tenants.php?edit_id=<?php echo (int)$row['id']; ?>">Edit</a>
                      <a class="btn btn-success btn-sm" href="payments.php?tenant_id=<?php echo (int)$row['id']; ?>">Payments</a>
                      <form method="POST" action="process_tenant.php" onsubmit="return confirm('Delete this tenant?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
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

    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('show');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}

function togglePassword(){
  const input = document.getElementById('passwordInput');
  const btn = document.querySelector('.pw-toggle');
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.textContent = isHidden ? 'Hide' : 'Show';
}

function validateTenantForm() {
  // Additional JS validation if needed
  return true;
}
</script>
</body>
</html>