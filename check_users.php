<?php
require_once __DIR__ . '/config/database.php';
$res = $conn->query("SELECT id, email, role FROM users");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Email: {$row['email']} | Role: {$row['role']}\n";
}
?>
