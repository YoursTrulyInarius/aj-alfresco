<?php
// PayMongo API Configuration
// Replace these with your actual PayMongo keys
// Secret Key (sk_test_...) and Public Key (pk_test_...)

define('PAYMONGO_SECRET_KEY', 'YOUR SECRET_KEY_HERE');
define('PAYMONGO_PUBLIC_KEY', 'YOUR PUBLIC_KEY_HERE');

// Success and Cancel URLs
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if ($base_path == '\\' || $base_path == '/') $base_path = '';

define('PAYMONGO_SUCCESS_URL', $current_url . $base_path . '/tenant/payment_success.php');
define('PAYMONGO_CANCEL_URL', $current_url . $base_path . '/tenant/payments.php?status=cancelled');
?>
