<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $payment_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_for_month = sanitize($_POST['payment_for_month'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? 'cash');
    $reference_number = sanitize($_POST['reference_number'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if ($contract_id <= 0) {
        $_SESSION['flash'] = "Please select a contract.";
        header("Location: payments.php");
        exit();
    }

    // Get tenant_id from contract
    $stmt = $conn->prepare("SELECT tenant_id FROM contracts WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();

    if (!$contract) {
        $_SESSION['flash'] = "Contract not found.";
        header("Location: payments.php");
        exit();
    }

    $tenant_id = (int)$contract['tenant_id'];

    // Generate receipt number (retry if collision)
    do {
        $receipt = generateReceiptNumber();
        $check = $conn->prepare("SELECT id FROM payments WHERE receipt_number=? LIMIT 1");
        $check->bind_param("s", $receipt);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
    } while ($exists);

    $operator = $_SESSION['full_name'] ?? 'Admin';

    $ins = $conn->prepare("INSERT INTO payments(contract_id, tenant_id, amount, payment_date, payment_for_month,
                        payment_method, reference_number, receipt_number, operator, notes, status)
                        VALUES(?,?,?,?,?,?,?,?,?,?, 'paid')");
    $ins->bind_param(
        "iidsssssss",
        $contract_id,
        $tenant_id,
        $amount,
        $payment_date,
        $payment_for_month,
        $payment_method,
        $reference_number,
        $receipt,
        $operator,
        $notes
    );
    $ins->execute();

    auditLog('created', 'payment', $ins->insert_id, [
        'contract_id' => $contract_id,
        'tenant_id' => $tenant_id,
        'amount' => $amount,
        'payment_for_month' => $payment_for_month,
        'payment_method' => $payment_method,
        'receipt_number' => $receipt
    ]);

    // Notify tenant
    createNotification($tenant_id, "Payment Recorded", "Your payment was recorded. Receipt: $receipt", "payment");

    $_SESSION['flash'] = "Payment saved. Receipt generated: $receipt";
    header("Location: payments.php");
    exit();
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $paymentStmt = $conn->prepare("SELECT contract_id, tenant_id, amount, payment_for_month, payment_method, receipt_number FROM payments WHERE id=? LIMIT 1");
    $paymentStmt->bind_param("i", $id);
    $paymentStmt->execute();
    $payment = $paymentStmt->get_result()->fetch_assoc();

    $del = $conn->prepare("DELETE FROM payments WHERE id=?");
    $del->bind_param("i", $id);
    $del->execute();

    if ($del->affected_rows > 0) {
        auditLog('deleted', 'payment', $id, $payment ?? []);
        $_SESSION['flash'] = "Payment deleted.";
    } else {
        $_SESSION['flash'] = "Payment not found.";
    }
    header("Location: payments.php");
    exit();
}

header("Location: payments.php");
exit();