<?php
$f = 'c:/xampp/htdocs/aj-alfresco/tenant/dashboard.php';
$c = file_get_contents($f);
$p = strpos($c, '</html>');
if ($p !== false) {
    file_put_contents($f, substr($c, 0, $p + 7));
    echo "Fixed.";
} else {
    echo "No </html> found.";
}
?>
