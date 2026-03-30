<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/paymongo.php';
requireTenant();

header('Content-Type: application/json');

$sessionId = sanitize($_GET['session_id'] ?? '');

if (!$sessionId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing session id']);
    exit();
}

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . $sessionId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
]);

$response = curl_exec($ch);
$resObj = json_decode($response, true);
curl_close($ch);

$status = $resObj['data']['attributes']['payment_intent']['attributes']['status'] ?? 'unknown';

echo json_encode(['status' => ($status === 'succeeded' ? 'paid' : 'pending')]);
