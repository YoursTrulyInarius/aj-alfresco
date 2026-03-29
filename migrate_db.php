<?php
require_once __DIR__ . '/config/database.php';

echo "Running migrations...\n";

$queries = [
    // Users updates
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS business_name VARCHAR(150)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS business_type VARCHAR(100)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS advance_balance DECIMAL(10,2) DEFAULT 0.00",

    // Stalls updates
    "ALTER TABLE stalls DROP COLUMN IF EXISTS size_sqm",

    // Contracts updates
    "ALTER TABLE contracts ADD COLUMN IF NOT EXISTS duration_type VARCHAR(50)",
    "ALTER TABLE contracts MODIFY COLUMN status ENUM('active','expired','pending_renewal','terminated') DEFAULT 'active'",

    // Payments updates
    "ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','gcash','bank_transfer','paymongo','other') DEFAULT 'cash'",
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "Success: $sql\n";
    } else {
        echo "Error on query: $sql\n" . $conn->error . "\n";
    }
}

echo "Migration script finished.\n";
?>
