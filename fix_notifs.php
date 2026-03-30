<?php
require_once __DIR__ . '/config/database.php';
$conn->query("DELETE FROM notifications WHERE message LIKE '%Apr 01%'");
echo "Cleaned up incorrect 'Apr 01' notifications.\n";
?>
