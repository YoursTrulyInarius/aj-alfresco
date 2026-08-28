<?php
require_once __DIR__ . '/config/database.php';

$changes = [
    "ALTER TABLE payments MODIFY COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'cash'"
];

$indexes = [
    'contracts' => [
        'idx_contracts_tenant_status' => '(tenant_id, status, id)'
    ],
    'payments' => [
        'idx_payments_contract_month_status' => '(contract_id, payment_for_month, status)',
        'idx_payments_tenant_date' => '(tenant_id, payment_date, id)'
    ],
    'notifications' => [
        'idx_notifications_user_read' => '(user_id, is_read, id)',
        'idx_notifications_user_type' => '(user_id, type, id)'
    ]
];

foreach ($changes as $sql) {
    if (!$conn->query($sql) && stripos($conn->error, 'duplicate') === false) {
        die("Error: " . $conn->error . PHP_EOL);
    }
}

foreach ($indexes as $table => $tableIndexes) {
    foreach ($tableIndexes as $name => $columns) {
        $nameEscaped = $conn->real_escape_string($name);
        $existing = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name='$nameEscaped'");
        if ($existing && $existing->num_rows > 0) continue;

        if (!$conn->query("CREATE INDEX `$name` ON `$table` $columns")) {
            die("Error creating $name: " . $conn->error . PHP_EOL);
        }
        echo "Created $name" . PHP_EOL;
    }
}

echo "Database migration complete." . PHP_EOL;
?>
