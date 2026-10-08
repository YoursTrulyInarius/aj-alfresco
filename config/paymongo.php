<?php
// PayMongo API Configuration
$localPaymongoConfig = __DIR__ . '/paymongo.local.php';
if (is_file($localPaymongoConfig)) {
	require_once $localPaymongoConfig;
}

$paymongoSecretEnvironment = getenv('PAYMONGO_SECRET_KEY');
$paymongoPublicEnvironment = getenv('PAYMONGO_PUBLIC_KEY');
define('PAYMONGO_SECRET_KEY', $paymongoSecretEnvironment === false ? ($paymongoSecretKey ?? '') : $paymongoSecretEnvironment);
define('PAYMONGO_PUBLIC_KEY', $paymongoPublicEnvironment === false ? ($paymongoPublicKey ?? '') : $paymongoPublicEnvironment);

// Success and Cancel URLs
$requestHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $requestHost;
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$base_path = rtrim(dirname(dirname($scriptPath)), '/');
if ($base_path === '.') {
    $base_path = '';
}

define('PAYMONGO_SUCCESS_URL', $current_url . $base_path . '/tenant/payment_success.php');
define('PAYMONGO_CANCEL_URL', $current_url . $base_path . '/tenant/payments.php?status=cancelled');
?>
 