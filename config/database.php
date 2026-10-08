<?php
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$localDatabaseConfig = __DIR__ . '/database.local.php';
if (is_file($localDatabaseConfig)) {
    require_once $localDatabaseConfig;
}

$databaseSetting = static function ($name, $localValue, $default) {
    $environmentValue = getenv($name);
    return $environmentValue === false ? ($localValue ?? $default) : $environmentValue;
};

$host = $databaseSetting('DB_HOST', $dbHost ?? null, 'localhost');
$user = $databaseSetting('DB_USER', $dbUser ?? null, 'root');
$password = $databaseSetting('DB_PASSWORD', $dbPass ?? null, '');
$database = $databaseSetting('DB_NAME', $dbName ?? null, 'aj_alfresco_rms');

try {
    $conn = new mysqli($host, $user, $password, $database);
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    die("Database connection failed. Check config/database.local.php or the DB_* environment settings.");
}
if ($conn->connect_error) {
    die("Database connection failed. Check config/database.local.php or the DB_* environment settings.");
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");
?>