<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$month = sanitize($_GET['month'] ?? date('Y-m'));
$method = sanitize($_GET['method'] ?? 'card'); // Dynamic method from demo
$fakeSession = 'DEMO-' . strtoupper(bin2hex(random_bytes(6)));

// Get contract info
$stmt = $conn->prepare("
  SELECT c.id, c.monthly_rent, s.stall_number 
  FROM contracts c 
  JOIN stalls s ON s.id = c.stall_id
  WHERE c.tenant_id=? AND c.status='active' 
  LIMIT 1
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

if (!$contract) {
    $_SESSION['flash'] = "Demo Error: No active contract found for this tenant.";
    header("Location: payments.php");
    exit();
}

$amount = (float)$contract['monthly_rent'];
$receipt = generateReceiptNumber();
$date = date('Y-m-d');
$operator = 'System (Demo Bypass)';
$notes = "Demo Bypass Payment - Method: " . strtoupper($method) . " - Session: $fakeSession";

$ins = $conn->prepare("
  INSERT INTO payments(contract_id, tenant_id, amount, payment_date, payment_for_month, payment_method, reference_number, receipt_number, operator, notes, status)
  VALUES(?,?,?,?,?,?,?,?,?,?, 'paid')
");
$ins->bind_param("iidsssssss", $contract['id'], $tenantId, $amount, $date, $month, $method, $fakeSession, $receipt, $operator, $notes);

if ($ins->execute()) {
    // Notify Admin
    $adminRes = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
    if($adm = $adminRes->fetch_assoc()){
        createNotification(
            $adm['id'],
            "New Demo Payment (Bypass)",
            "Tenant " . $_SESSION['full_name'] . " paid " . formatMoney($amount) . " for " . date('F Y', strtotime($month . '-01')) . " (Stall " . $contract['stall_number'] . ") via Demo Bypass.",
            "payment"
        );
    }
    
    // Notify Tenant
    createNotification(
        $tenantId,
        "Payment Successful (Demo)",
        "Your payment of " . formatMoney($amount) . " was processed successfully via Demo Bypass! Receipt #" . $receipt,
        "payment"
    );

    $_SESSION['flash'] = "Success! Demo payment recorded. Receipt #$receipt generated.";
} else {
    $_SESSION['flash'] = "Demo Error: " . $conn->error;
}

header("Location: payments.php");
exit();
