<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Get active contract & pending balance
$contract = $conn->query("
    SELECT c.*, s.stall_number 
    FROM contracts c
    JOIN stalls s ON s.id = c.stall_id
    WHERE c.tenant_id = $tenantId AND c.status='active'
    LIMIT 1
")->fetch_assoc();

if (!$contract) {
    $_SESSION['flash'] = "You do not have an active rental contract.";
    redirect('dashboard.php');
}

// Check for current month payment
$currentMonth = date('Y-m');
$paidRow = $conn->query("SELECT id FROM payments WHERE contract_id = {$contract['id']} AND payment_for_month = '$currentMonth' LIMIT 1")->fetch_assoc();
$paid = $paidRow ? true : false;
$paidId = $paidRow['id'] ?? 0;

// Get tenant phone for pre-filling
$tenant = $conn->query("SELECT phone FROM users WHERE id = $tenantId")->fetch_assoc();
$unread = notifUnreadCount($tenantId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Make Payment - A&J Alfresco</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=6"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .payment-card { max-width: 600px; margin: 0 auto; }
    #paymongo-modal {
        display: none; position: fixed; z-index: 2000; left: 0; top: 0;
        width: 100%; height: 100%; background: rgba(0,0,0,0.6);
        align-items: center; justify-content: center;
    }
    .modal-content {
        background: #fff; width: 90%; max-width: 500px; height: 600px;
        border-radius: 12px; position: relative; overflow: hidden;
    }
    .modal-header { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    iframe { width: 100%; height: calc(100% - 55px); border: none; }
  </style>
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="logo">🏪</span>
      <h2>A&J Alfresco</h2>
      <p>Tenant Panel</p>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><span class="icon">📊</span> Dashboard</a></li>
      <li><a href="contract.php"><span class="icon">📄</span> My Contract</a></li>
      <li><a class="active" href="make_payment.php"><span class="icon">🧾</span> Make Payment</a></li>
      <li><a href="payments.php"><span class="icon">💰</span> Payment History</a></li>
      <li><a href="notifications.php"><span class="icon">🔔</span> Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
      <li><a href="logout.php"><span class="icon">🚪</span> Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <div class="top-bar">
       <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
       <h1>Rent Payment</h1>
       <div class="user-info"><span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span></div>
    </div>

    <div class="content">
      <div class="payment-card card">
        <div class="card-header">
          <h2>Monthly Rent Payment</h2>
        </div>
        <div class="card-body">
            <div style="background:#f8f9fa; padding:20px; border-radius:12px; margin-bottom:25px;">
                <p><strong>Stall:</strong> <?php echo htmlspecialchars($contract['stall_number']); ?></p>
                <p><strong>Month:</strong> <?php echo date('F Y'); ?></p>
                <h3 style="color:#d63384; margin-top:10px;">Amount Due: <?php echo formatMoney($contract['monthly_rent']); ?></h3>
            </div>

            <?php if ($paid): ?>
                <div class="alert alert-success">✅ You have already paid for this month. <a href="receipt.php?id=<?php echo $paidId; ?>" style="color:inherit; text-decoration:underline;">View Receipt</a></div>
            <?php else: ?>
                <p style="margin-bottom:20px; color:#666;">You will be redirected to the secure **PayMongo** checkout page for GCash, Maya, or GrabPay.</p>
                <button type="button" class="btn btn-primary" onclick="initiatePayment()" style="width:100%; padding:15px; font-weight:bold; font-size:1.1rem;">
                    💳 Process E-Wallet Payment
                </button>
                <p style="text-align:center; margin-top:15px; color:#888; font-size:0.85rem;">Powered by <strong>PayMongo</strong> Secure Checkout</p>
            <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- OFFICIAL PAYMONGO MODAL -->
<div id="paymongo-modal">
    <div class="modal-content">
        <div class="modal-header">
            <strong>Secure PayMongo Checkout</strong>
            <button class="btn btn-secondary btn-sm" onclick="closeModal()" style="width:auto;">Close</button>
        </div>
        <iframe id="checkout-iframe" src=""></iframe>
    </div>
</div>

<script>
let checkInterval;

async function initiatePayment() {
    Swal.fire({
        title: 'Connecting to PayMongo...',
        text: 'Generating secure checkout session...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('process_paymongo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
        });
        const data = await response.json();

        if (data.success) {
            Swal.close();
            document.getElementById('checkout-iframe').src = data.checkout_url;
            document.getElementById('paymongo-modal').style.display = 'flex';
            startStatusCheck(data.session_id);
        } else {
            Swal.fire({ icon: 'error', title: 'Payment Error', text: data.message || 'Could not create checkout session.' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'System Error', text: 'An unexpected error occurred. Please try again.' });
    }
}

function startStatusCheck(sessionId) {
    checkInterval = setInterval(async () => {
        try {
            const resp = await fetch(`check_payment_status.php?session_id=${sessionId}`);
            const result = await resp.json();
            if (result.status === 'paid') {
                clearInterval(checkInterval);
                window.location.href = `payment_success.php?session_id=${sessionId}&month=<?php echo date('Y-m'); ?>`;
            }
        } catch (e) {}
    }, 3000);
}

function closeModal() {
    Swal.fire({
        title: 'Cancel Payment?',
        text: "Your progress on the checkout page will be lost.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Yes, cancel it'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('paymongo-modal').style.display = 'none';
            document.getElementById('checkout-iframe').src = '';
            clearInterval(checkInterval);
        }
    });
}

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('show');
}
</script>
</body>
</html>
