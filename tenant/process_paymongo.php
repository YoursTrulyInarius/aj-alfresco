<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/paymongo.php';
requireTenant();

header('Content-Type: application/json');

$tenantId = (int)$_SESSION['user_id'];
$phone = sanitize($_POST['phone'] ?? '');

// Get tenant active contract and stall
$stmt = $conn->prepare("
  SELECT c.monthly_rent, s.stall_number 
  FROM contracts c 
  JOIN stalls s ON s.id = c.stall_id
  WHERE c.tenant_id=? AND c.status='active' 
  LIMIT 1
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

if (!$contract) {
    echo json_encode(['success' => false, 'message' => 'No active contract found.']);
    exit();
}

$amount = (int)($contract['monthly_rent'] * 100); // Convert to cents
$month = date('Y-m');
$description = "Monthly Rent Payment - Stall " . $contract['stall_number'] . " (" . date('F Y') . ")";

$data = [
    'data' => [
        'attributes' => [
            'send_email_receipt' => true,
            'show_description' => true,
            'show_line_items' => true,
            'line_items' => [
                [
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'description' => $description,
                    'name' => 'Monthly Rent',
                    'quantity' => 1
                ]
            ],
            'payment_method_types' => ['gcash', 'paymaya', 'grab_pay'],
            'success_url' => PAYMONGO_SUCCESS_URL . "?session_id={CHECKOUT_SESSION_ID}&month=$month",
            'cancel_url' => PAYMONGO_CANCEL_URL,
            'description' => $description
        ]
    ]
];

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
]);

$response = curl_exec($ch);
$resObj = json_decode($response, true);
curl_close($ch);

if (isset($resObj['data']['id'])) {
    echo json_encode([
        'success' => true, 
        'checkout_url' => $resObj['data']['attributes']['checkout_url'],
        'session_id' => $resObj['data']['id']
    ]);
} else {
    $errMsg = $resObj['errors'][0]['detail'] ?? 'PayMongo API Error';
    echo json_encode([
        'success' => false, 
        'message' => $errMsg
    ]);
}
