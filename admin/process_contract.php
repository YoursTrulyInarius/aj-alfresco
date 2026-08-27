<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $stall_id = (int)($_POST['stall_id'] ?? 0);
    $start_date = sanitize($_POST['start_date'] ?? '');
    $end_date = sanitize($_POST['end_date'] ?? '');
    $monthly_rent = (float)str_replace(',', '', $_POST['monthly_rent'] ?? 0);
    $deposit_amount = (float)str_replace(',', '', $_POST['deposit_amount'] ?? 0);
    $duration_type = sanitize($_POST['duration_type'] ?? '1 year');
    $terms = sanitize($_POST['terms'] ?? '');

    if ($tenant_id <= 0 || $stall_id <= 0) {
        $_SESSION['flash'] = "Please select tenant and stall.";
        header("Location: contracts.php");
        exit();
    }

    // Ensure stall is available
    $chk = $conn->prepare("SELECT status FROM stalls WHERE id=? LIMIT 1");
    $chk->bind_param("i", $stall_id);
    $chk->execute();
    $stall = $chk->get_result()->fetch_assoc();
    if (!$stall || $stall['status'] !== 'available') {
        $_SESSION['flash'] = "Selected stall is not available.";
        header("Location: contracts.php");
        exit();
    }

    // Create contract
    $stmt = $conn->prepare("INSERT INTO contracts(tenant_id, stall_id, start_date, end_date, monthly_rent, deposit_amount, terms, status, duration_type)
                            VALUES(?,?,?,?,?,?,?,'active',?)");
    $stmt->bind_param("iissddss", $tenant_id, $stall_id, $start_date, $end_date, $monthly_rent, $deposit_amount, $terms, $duration_type);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $_SESSION['new_contract_id'] = $new_id;
        // Mark stall occupied
        $up = $conn->prepare("UPDATE stalls SET status='occupied' WHERE id=?");
        $up->bind_param("i", $stall_id);
        $up->execute();

        $_SESSION['flash'] = "Contract created successfully.";
    } else {
        $_SESSION['flash'] = "Error: " . $conn->error;
    }

    header("Location: contracts.php");
    exit();
}

if ($action === 'terminate') {
    $id = (int)($_POST['id'] ?? 0);

    // Look up the stall_id associated with this contract
    $stmt_find = $conn->prepare("SELECT stall_id FROM contracts WHERE id = ? LIMIT 1");
    $stmt_find->bind_param("i", $id);
    $stmt_find->execute();
    $contract_info = $stmt_find->get_result()->fetch_assoc();

    if ($contract_info) {
        $stall_id = (int)$contract_info['stall_id'];

        $stmt = $conn->prepare("UPDATE contracts SET status='terminated' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $up = $conn->prepare("UPDATE stalls SET status='available' WHERE id=?");
        $up->bind_param("i", $stall_id);
        $up->execute();

        $_SESSION['flash'] = "Contract terminated. Stall set to available.";
    } else {
        $_SESSION['flash'] = "Error: Contract not found.";
    }
    header("Location: contracts.php");
    exit();
}

header("Location: contracts.php");
exit();