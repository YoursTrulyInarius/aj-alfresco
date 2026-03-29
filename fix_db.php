<?php
require_once __DIR__ . '/config/database.php';

// Check if column exists first
$check = $conn->query("SHOW COLUMNS FROM payments LIKE 'reference_number'");
if ($check && $check->num_rows === 0) {
    $res = $conn->query("ALTER TABLE payments ADD COLUMN reference_number VARCHAR(100) AFTER payment_method");
    if ($res) {
        echo "SUCCESS: Added reference_number column to payments table.\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
} else {
    echo "COLUMN EXISTS: reference_number already in payments table.\n";
}
?>
