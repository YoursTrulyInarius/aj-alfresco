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
    <link rel="stylesheet" href="../assets/css/style.css?v=8"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .payment-card { max-width: 900px; margin: 0 auto; }
    .payment-card .card-header { padding: 24px 28px; }
    .payment-card .card-body { padding: 28px; }
    .payment-header-copy h2 { margin-bottom: 5px; }
    .payment-header-copy p { color: var(--muted); font-size: 13px; margin: 0; }
    .payment-overview {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 24px;
        align-items: center;
        padding: 22px;
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 12px;
    }
    .payment-label {
        display: block;
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .payment-stall { color: var(--secondary); font-size: 20px; font-weight: 800; }
    .payment-month { color: var(--secondary-mid); font-size: 14px; margin-top: 5px; }
    .payment-amount { color: var(--primary); font-size: 25px; font-weight: 800; text-align: right; }
    .payment-divider { border: 0; border-top: 1px solid var(--border); margin: 24px 0; }
    .payment-status { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 10px; font-size: 13px; line-height: 1.5; }
    .payment-status.paid { color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; }
    .payment-status.paid strong { color: #065f46; }
    .payment-status-mark { width: 22px; height: 22px; border-radius: 50%; background: #10b981; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; flex-shrink: 0; }
    .payment-instructions { color: var(--muted); font-size: 13px; line-height: 1.7; margin: 0 0 18px; }
    .payment-action { width: 100% !important; padding: 13px 18px; font-size: 14px; }
    .payment-provider { color: var(--muted); font-size: 11px; text-align: center; margin: 10px 0 0; }
    .payment-provider strong { color: var(--secondary-mid); }
    @media (max-width: 560px) {
        .payment-card .card-header, .payment-card .card-body { padding: 18px; }
        .payment-overview { grid-template-columns: 1fr; gap: 16px; padding: 18px; }
        .payment-amount { text-align: left; font-size: 23px; }
    }
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
            <div class="sidebar-brand">
                <span class="sidebar-brand-name">A&J Alfresco</span>
                <span class="sidebar-brand-sub">Tenant Panel</span>
            </div>
    </div>
        <p class="sidebar-nav-label">Main Menu</p>
    <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="contract.php">My Contract</a></li>
            <li><a class="active" href="make_payment.php">Make Payment</a></li>
            <li><a href="payments.php">Payment History</a></li>
            <li><a href="notifications.php">Notifications <?php if($unread>0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
            <li><a href="change_password.php">Change Password</a></li>
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
                 <h1>Rent Payment</h1>
             </div>
             <div class="user-info">
                 <div class="avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1)); ?></div>
                 <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
             </div>
    </div>

    <div class="content">
      <div class="payment-card card">
        <div class="card-header">
          <div class="payment-header-copy">
            <h2>Monthly Rent Payment</h2>
            <p>Review the payment details before continuing.</p>
          </div>
        </div>
        <div class="card-body">
            <div class="payment-overview">
                <div>
                    <span class="payment-label">Rental stall</span>
                    <strong class="payment-stall"><?php echo htmlspecialchars($contract['stall_number']); ?></strong>
                    <p class="payment-month">Payment for <?php echo date('F Y'); ?></p>
                </div>
                <div>
                    <span class="payment-label">Amount due</span>
                    <strong class="payment-amount"><?php echo formatMoney($contract['monthly_rent']); ?></strong>
                </div>
            </div>

            <?php if ($paid): ?>
                <hr class="payment-divider">
                <div class="payment-status paid">
                    <span class="payment-status-mark">&#10003;</span>
                    <span><strong>Payment already recorded.</strong> You have paid for this month. <a href="receipt.php?id=<?php echo $paidId; ?>" style="color:inherit; text-decoration:underline;">View receipt</a></span>
                </div>
            <?php else: ?>
                <hr class="payment-divider">
                <p class="payment-instructions">You will continue to the secure PayMongo checkout page, where you can complete payment using available supported e-wallet methods.</p>
                <button type="button" class="btn btn-primary payment-action" onclick="initiatePayment()">
                    Continue to secure payment
                </button>
                <p class="payment-provider">Secure checkout powered by <strong>PayMongo</strong></p>
            <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
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
            // Open secure PayMongo page in a new tab/popup
            window.open(data.checkout_url, '_blank');

            // Show waiting screen in original tab
            Swal.fire({
                title: 'Waiting for Payment...',
                html: 'Please complete your GCash/Maya payment in the new tab.<br><br><span style="color:#64748b; font-size: 0.9rem;">This window will automatically redirect once your payment is completed.</span>',
                icon: 'info',
                allowOutsideClick: false,
                showCancelButton: true,
                cancelButtonText: 'Cancel & Close',
                didOpen: () => {
                    Swal.showLoading(Swal.getCancelButton());
                }
            }).then((result) => {
                // If user clicks "Cancel & Close", stop polling
                if (result.dismiss === Swal.DismissReason.cancel) {
                    clearInterval(checkInterval);
                }
            });

            // Start checking for status update
            startStatusCheck(data.session_id);
        } else {
            Swal.fire({ icon: 'error', title: 'Payment Error', text: data.message || 'Could not create checkout session.' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'System Error', text: 'An unexpected error occurred. Please try again.' });
    }
}

function startStatusCheck(sessionId) {
    clearInterval(checkInterval);
    checkInterval = setInterval(async () => {
        try {
            const resp = await fetch(`check_payment_status.php?session_id=${sessionId}`);
            const result = await resp.json();
            if (result.status === 'paid') {
                clearInterval(checkInterval);
                Swal.close();
                window.location.href = `payment_success.php?session_id=${sessionId}&month=<?php echo date('Y-m'); ?>`;
            }
        } catch (e) {}
    }, 3000);
}

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
</script>
</body>
</html>
