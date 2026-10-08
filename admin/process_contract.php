<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $stall_id = (int)($_POST['stall_id'] ?? 0);
    $start_date = sanitize($_POST['start_date'] ?? '');
    $end_date = sanitize($_POST['end_date'] ?? '');
    $deposit_amount = (float)str_replace(',', '', $_POST['deposit_amount'] ?? 0);
    $duration_type = sanitize($_POST['duration_type'] ?? '1 year');
    $terms = sanitize($_POST['terms'] ?? '');

    if ($tenant_id <= 0 || $stall_id <= 0) {
        $_SESSION['flash'] = "Please select tenant and stall.";
        header("Location: contracts.php");
        exit();
    }

    // Ensure stall is available
    $chk = $conn->prepare("SELECT status, monthly_rate FROM stalls WHERE id=? LIMIT 1");
    $chk->bind_param("i", $stall_id);
    $chk->execute();
    $stall = $chk->get_result()->fetch_assoc();
    if (!$stall || $stall['status'] !== 'available') {
        $_SESSION['flash'] = "Selected stall is not available.";
        header("Location: contracts.php");
        exit();
    }
    $monthly_rent = (float)$stall['monthly_rate'];

    // Create contract
    $stmt = $conn->prepare("INSERT INTO contracts(tenant_id, stall_id, start_date, end_date, monthly_rent, deposit_amount, terms, status, duration_type)
                            VALUES(?,?,?,?,?,?,?,'active',?)");
    $stmt->bind_param("iissddss", $tenant_id, $stall_id, $start_date, $end_date, $monthly_rent, $deposit_amount, $terms, $duration_type);

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        auditLog('created', 'contract', $new_id, [
            'tenant_id' => $tenant_id,
            'stall_id' => $stall_id,
            'monthly_rent' => $monthly_rent,
            'deposit_amount' => $deposit_amount,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'active'
        ]);
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

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $stall_id = (int)($_POST['stall_id'] ?? 0);
    $start_date = sanitize($_POST['start_date'] ?? '');
    $end_date = sanitize($_POST['end_date'] ?? '');
    $deposit_amount = (float)str_replace(',', '', $_POST['deposit_amount'] ?? 0);
    $duration_type = sanitize($_POST['duration_type'] ?? '1 year');
    $terms = sanitize($_POST['terms'] ?? '');
    $new_status = $_POST['status'] ?? '';

    if ($id <= 0 || $tenant_id <= 0 || $stall_id <= 0 || !$start_date || !$end_date || $end_date < $start_date || !in_array($new_status, ['active', 'pending_renewal', 'for_renewal', 'expired', 'terminated'], true)) {
        $_SESSION['flash'] = "Please provide valid contract details.";
        header("Location: contracts.php");
        exit();
    }

    $contractStmt = $conn->prepare("SELECT * FROM contracts WHERE id=? LIMIT 1");
    $contractStmt->bind_param("i", $id);
    $contractStmt->execute();
    $contract = $contractStmt->get_result()->fetch_assoc();

    $tenantStmt = $conn->prepare("SELECT id FROM users WHERE id=? AND role='tenant' LIMIT 1");
    $tenantStmt->bind_param("i", $tenant_id);
    $tenantStmt->execute();
    $tenantExists = $tenantStmt->get_result()->num_rows > 0;

    $stallStmt = $conn->prepare("SELECT status, monthly_rate FROM stalls WHERE id=? LIMIT 1");
    $stallStmt->bind_param("i", $stall_id);
    $stallStmt->execute();
    $stall = $stallStmt->get_result()->fetch_assoc();

    if (!$contract || !$tenantExists || !$stall || ($stall_id !== (int)$contract['stall_id'] && $stall['status'] !== 'available')) {
        $_SESSION['flash'] = "The selected contract or stall is not available for editing.";
        header("Location: contracts.php");
        exit();
    }

    $monthly_rent = (float)$stall['monthly_rate'];
    $old_stall_id = (int)$contract['stall_id'];
    $activeStatuses = ['active', 'pending_renewal', 'for_renewal'];
    $needsOccupiedStall = in_array($new_status, $activeStatuses, true);
    $hasOtherActiveContract = false;
    if ($needsOccupiedStall) {
        $conflict = $conn->prepare("SELECT id FROM contracts WHERE stall_id=? AND id<>? AND status IN ('active','pending_renewal','for_renewal') LIMIT 1");
        $conflict->bind_param("ii", $stall_id, $id);
        $conflict->execute();
        $hasOtherActiveContract = $conflict->get_result()->num_rows > 0;
    }
    if ($hasOtherActiveContract) {
        $_SESSION['flash'] = "The selected stall already has an active contract.";
        header("Location: contracts.php");
        exit();
    }

    $conn->begin_transaction();
    $update = $conn->prepare("UPDATE contracts SET tenant_id=?, stall_id=?, start_date=?, end_date=?, monthly_rent=?, deposit_amount=?, terms=?, duration_type=?, status=? WHERE id=?");
    $update->bind_param("iissddsssi", $tenant_id, $stall_id, $start_date, $end_date, $monthly_rent, $deposit_amount, $terms, $duration_type, $new_status, $id);
    $success = $update->execute();

    if ($success && ($old_stall_id !== $stall_id || $contract['status'] !== $new_status)) {
        $release = $conn->prepare("UPDATE stalls SET status='available' WHERE id=?");
        $release->bind_param("i", $old_stall_id);
        $success = $release->execute();

        if ($success && $needsOccupiedStall) {
            $occupy = $conn->prepare("UPDATE stalls SET status='occupied' WHERE id=?");
            $occupy->bind_param("i", $stall_id);
            $success = $occupy->execute();
        }
    }

    if ($success) {
        auditLog('updated', 'contract', $id, [
            'before' => $contract,
            'after' => [
                'tenant_id' => $tenant_id,
                'stall_id' => $stall_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'monthly_rent' => $monthly_rent,
                'deposit_amount' => $deposit_amount,
                'duration_type' => $duration_type,
                'status' => $new_status
            ]
        ]);
        $conn->commit();
        if ($new_status === 'active') {
            tenantAutoReminders($tenant_id);
        }
        $_SESSION['flash'] = "Contract updated successfully.";
    } else {
        $conn->rollback();
        $_SESSION['flash'] = "Unable to update the contract.";
    }

    header("Location: contracts.php");
    exit();
}

if ($action === 'terminate') {
    $id = (int)($_POST['id'] ?? 0);

    // Look up the stall_id associated with this contract
    $stmt_find = $conn->prepare("SELECT stall_id, status FROM contracts WHERE id = ? LIMIT 1");
    $stmt_find->bind_param("i", $id);
    $stmt_find->execute();
    $contract_info = $stmt_find->get_result()->fetch_assoc();

    if ($contract_info) {
        $stall_id = (int)$contract_info['stall_id'];

        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE contracts SET status='terminated' WHERE id=?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();

        $up = $conn->prepare("UPDATE stalls SET status='available' WHERE id=?");
        $up->bind_param("i", $stall_id);
        $success = $success && $up->execute();

        if ($success) {
            auditLog('terminated', 'contract', $id, [
                'stall_id' => $stall_id,
                'previous_status' => $contract_info['status']
            ]);
            $conn->commit();
            $_SESSION['flash'] = "Contract terminated. Stall set to available.";
        } else {
            $conn->rollback();
            $_SESSION['flash'] = "Unable to terminate the contract.";
        }
    } else {
        $_SESSION['flash'] = "Error: Contract not found.";
    }
    header("Location: contracts.php");
    exit();
}

header("Location: contracts.php");
exit();
