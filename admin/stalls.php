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

$query = "SELECT * FROM stalls";
if ($search) {
    $query .= " WHERE stall_number LIKE '%$search%' OR stall_name LIKE '%$search%' OR location_description LIKE '%$search%'";
}
$query .= " ORDER BY id DESC";
$result = $conn->query($query);

function selected($a, $b) {
    return ($a === $b) ? 'selected' : '';
}

$statusVal = $editStall['status'] ?? 'available';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Stalls - Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=2"/>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
      <li><a class="active" href="stalls.php"><span class="icon">🏬</span> Stalls</a></li>
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

      <!-- ADD/EDIT STALL FORM -->
      <div class="card">
        <div class="card-header">
          <h2><?php echo $editStall ? 'Edit Stall' : 'Add Stall'; ?></h2>
        </div>

        <div class="card-body">
          <form method="POST" action="process_stall.php">
            <input type="hidden" name="action" value="<?php echo $editStall ? 'update' : 'create'; ?>">
            <input type="hidden" name="id" value="<?php echo (int)($editStall['id'] ?? 0); ?>">

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
              <div class="form-group">
                <label>Stall Number *</label>
                <input type="text" name="stall_number" required
                       placeholder="e.g., S-001"
                       value="<?php echo htmlspecialchars($editStall['stall_number'] ?? ''); ?>">
              </div>

              <div class="form-group">
                <label>Stall Name (optional)</label>
                <input type="text" name="stall_name"
                       placeholder="e.g., Food Stall A"
                       value="<?php echo htmlspecialchars($editStall['stall_name'] ?? ''); ?>">
              </div>

              <div class="form-group">
                <label>Monthly Rate (PHP) *</label>
                <input type="text" name="monthly_rate" required class="money-input"
                       placeholder="0.00"
                       value="<?php echo htmlspecialchars($editStall['monthly_rate'] ?? '0.00'); ?>">
              </div>

              <div class="form-group">
                <label>Status</label>
                <select name="status">
                  <option value="available" <?php echo selected($statusVal, 'available'); ?>>Available</option>
                  <option value="occupied" <?php echo selected($statusVal, 'occupied'); ?>>Occupied</option>
                  <option value="maintenance" <?php echo selected($statusVal, 'maintenance'); ?>>Maintenance</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Location Description *</label>
              <textarea name="location_description" required rows="2" placeholder="e.g. Ground Floor, Left Wing"><?php echo htmlspecialchars($editStall['location_description'] ?? ''); ?></textarea>
            </div>

            <button class="btn btn-primary" type="submit">
              <?php echo $editStall ? 'Update Stall' : 'Create Stall'; ?>
            </button>

            <?php if ($editStall): ?>
              <div style="margin-top:10px;">
                <a class="btn btn-warning btn-sm" href="stalls.php">Cancel Edit</a>
              </div>
            <?php endif; ?>

          </form>
        </div>
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
                  <td><?php echo formatMoney($row['monthly_rate']); ?></td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($row['status']); ?>">
                      <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:5px;">
                      <a class="btn btn-warning btn-sm" href="stalls.php?edit_id=<?php echo (int)$row['id']; ?>">Edit</a>

                      <form method="POST"
                            action="process_stall.php"
                            onsubmit="return confirm('Delete this stall?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
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
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('show');
  document.getElementById('sidebarOverlay').classList.toggle('show');
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
  // Money input formatting
  $('.money-input').on('blur', function() {
    formatMoneyInput(this);
  }).on('focus', function() {
    this.value = this.value.replace(/,/g, '');
  }).each(function() {
    formatMoneyInput(this);
  });
});
</script>
</body>
</html>