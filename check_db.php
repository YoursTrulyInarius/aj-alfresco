<?php
require_once __DIR__ . '/config/database.php';
$result = $conn->query("SHOW COLUMNS FROM users");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n--- Contracts Table ---\n";
$result = $conn->query("SHOW COLUMNS FROM contracts");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
