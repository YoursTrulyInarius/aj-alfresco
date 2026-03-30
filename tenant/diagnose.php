<?php
require_once __DIR__ . '/../config/paymongo.php';

echo "<h2>Diagnosis for PayMongo API (Links Check)</h2>";
echo "<p>Secret Key: " . (strlen(PAYMONGO_SECRET_KEY) > 10 ? substr(PAYMONGO_SECRET_KEY, 0, 8) . '...' : 'INVALID') . "</p>";

$payload = json_encode([
    'data' => [
        'attributes' => [
            'amount' => 10000,
            'description' => "Test Link Diagnosis"
        ]
    ]
]);

$ch = curl_init('https://api.paymongo.com/v1/links');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p>HTTP Status Code: $httpCode</p>";
if ($error) echo "<p style='color:red'>cURL Error: $error</p>";

echo "<h3>RAW Response Body:</h3>";
echo "<pre style='background:#eee; padding:10px; border:1px solid #ccc; max-width:1000px; overflow:auto;'>";
echo htmlspecialchars($response);
echo "</pre>";

$resObj = json_decode($response, true);
if ($resObj) {
    echo "<h3>Decoded JSON Data:</h3>";
    echo "<pre>";
    print_r($resObj);
    echo "</pre>";
} else {
    echo "<p style='color:red'>Failed to decode JSON response. If this is a 401, check your Secret Key in config/paymongo.php!</p>";
}
?>
