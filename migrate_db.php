<?php
require_once __DIR__ . '/config/database.php';
$conn->query("ALTER TABLE payments MODIFY COLUMN payment_method VARCHAR(50)");
if ($conn->error) {
    echo "Error: " . $conn->error;
} else {
    echo "Success! Database updated to VARCHAR(50).";
}
?>
