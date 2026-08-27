<?php
require_once __DIR__ . '/config/database.php';
$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS secondary_phone VARCHAR(30) AFTER phone";
if ($conn->query($sql)) {
    echo "Column secondary_phone added successfully.\n";
} else {
    echo "Error or column already exists: " . $conn->error . "\n";
}
