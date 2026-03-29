<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'request_renewal') {
    $id = (int)($_POST['id'] ?? 0);

    // Only allow if contract belongs to tenant and is active
    $stmt = $conn->prepare("SELECT id FROM contracts WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
    $stmt->bind_param("ii", $id, $tenantId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows === 1;

    if (!$ok) {
        $_SESSION['flash'] = "Renewal request not allowed.";
        header("Location: contract.php");
        exit();
    }

    // Mark contract as pending_renewal
    $up = $conn->prepare("UPDATE contracts SET status='pending_renewal' WHERE id=? AND tenant_id=?");
    $up->bind_param("ii", $id, $tenantId);
    $up->execute();

    // Notify admin (assumes admin user id = 1)
    createNotification(1, "Renewal Request", "Tenant ".$_SESSION['full_name']." requested contract renewal (Contract ID: $id).", "renewal");

    $_SESSION['flash'] = "Renewal request sent to admin.";
    header("Location: contract.php");
    exit();
}

header("Location: contract.php");
exit();