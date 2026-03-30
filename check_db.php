<?php
require_once __DIR__ . '/config/database.php';
echo "--- Payments Table ---\n";
$result = $conn->query("SHOW COLUMNS FROM payments");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n--- Recent Record ---\n";
$res = $conn->query("SELECT * FROM payments ORDER BY id DESC LIMIT 1");
if($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
