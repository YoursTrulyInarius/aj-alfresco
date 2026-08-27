<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/paymongo.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$sessionId = sanitize($_GET['session_id'] ?? '');
$month = sanitize($_GET['month'] ?? '');

if (!$sessionId) {
    header("Location: payments.php");
    exit();
}

// Check Checkout Session Status
$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . $sessionId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
]);

$response = curl_exec($ch);
$resObj = json_decode($response, true);
curl_close($ch);

$status = $resObj['data']['attributes']['payment_intent']['attributes']['status'] ?? 'unknown';

if ($status === 'succeeded') {
    // Check if we already recorded this payment
    $chk = $conn->prepare("SELECT id FROM payments WHERE reference_number=? LIMIT 1");
    $chk->bind_param("s", $sessionId);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        // Get contract info to associate with stall
        $stmt = $conn->prepare("
          SELECT c.id, s.stall_number 
          FROM contracts c 
          JOIN stalls s ON s.id = c.stall_id
          WHERE c.tenant_id=? AND c.status='active' 
          LIMIT 1
        ");
        $stmt->bind_param("i", $tenantId);
        $stmt->execute();
        $contract = $stmt->get_result()->fetch_assoc();
        
        $paymentData = $resObj['data']['attributes']['payment_intent']['attributes'];
        $amount = (float)($paymentData['amount'] / 100);
        $receipt = generateReceiptNumber();
        $date = date('Y-m-d');
        $operator = 'System (PayMongo)';
        
        // Extract the actual e-wallet source type (e.g., 'gcash', 'paymaya', 'grab_pay')
        $method = 'paymongo';
        if (!empty($resObj['data']['attributes']['payments'])) {
            $firstPayment = $resObj['data']['attributes']['payments'][0];
            if (isset($firstPayment['attributes']['source']['type'])) {
                $method = $firstPayment['attributes']['source']['type'];
            }
        }

        $ins = $conn->prepare("
          INSERT INTO payments(contract_id, tenant_id, amount, payment_date, payment_for_month, payment_method, reference_number, receipt_number, operator, notes, status)
          VALUES(?,?,?,?,?,?,?,?,?,?, 'paid')
        ");
        $notes = "Online payment via PayMongo (Session: $sessionId).";
        $ins->bind_param("iidsssssss", $contract['id'], $tenantId, $amount, $date, $month, $method, $sessionId, $receipt, $operator, $notes);
        
        if ($ins->execute()) {
            // Notify Admin
            $adminRes = $conn->query("SELECT id FROM users WHERE role='admin'");
            while ($adm = $adminRes->fetch_assoc()) {
                createNotification(
                    $adm['id'],
                    "New Online Payment",
                    "Tenant " . $_SESSION['full_name'] . " paid " . formatMoney($amount) . " for " . date('F Y', strtotime($month . '-01')) . " (Stall " . $contract['stall_number'] . ") via PayMongo.",
                    "payment"
                );
            }
            // Notify Tenant
            createNotification(
                $tenantId,
                "Payment Successful",
                "Your payment of " . formatMoney($amount) . " for " . date('F Y', strtotime($month . '-01')) . " was received! Receipt #" . $receipt,
                "payment"
            );
            
            $_SESSION['flash_success'] = "Payment successful! Receipt #$receipt has been generated.";
        }
    } else {
        $_SESSION['flash_success'] = "Payment already recorded.";
    }
} else {
    $_SESSION['flash_error'] = "Payment status: " . strtoupper($status);
}

header("Location: payments.php");
exit();
?>
