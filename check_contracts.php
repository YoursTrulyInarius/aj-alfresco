<?php
require_once __DIR__ . '/config/database.php';
$res = $conn->query("SELECT id, tenant_id, start_date FROM contracts WHERE status='active'");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Tenant: {$row['tenant_id']} | Start: {$row['start_date']}\n";
}
?>
