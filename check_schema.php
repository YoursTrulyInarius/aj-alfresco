<?php
require_once __DIR__ . '/config/database.php';
$tables = ['users', 'stalls', 'contracts', 'payments', 'notifications'];
foreach ($tables as $table) {
    echo "--- $table Table ---\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table $table not found.\n";
    }
    echo "\n";
}
