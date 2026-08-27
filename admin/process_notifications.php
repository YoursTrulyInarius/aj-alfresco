<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$adminId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();

    header("Location: notifications.php");
    exit();
}

if ($action === 'mark_one_read') {
    $id = (int)($_POST['id'] ?? 0);

    // Only mark if notification belongs to this admin
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $adminId);
    $stmt->execute();

    header("Location: notifications.php");
    exit();
}

if ($action === 'approve_termination' || $action === 'reject_termination') {
    $notificationId = (int)($_POST['notification_id'] ?? 0);

    $notificationStmt = $conn->prepare("SELECT message FROM notifications WHERE id=? AND user_id=? AND title='Termination Request' AND is_read=0 LIMIT 1");
    $notificationStmt->bind_param("ii", $notificationId, $adminId);
    $notificationStmt->execute();
    $notification = $notificationStmt->get_result()->fetch_assoc();

    if (!$notification || !preg_match('/Contract ID:\s*(\d+)/', $notification['message'], $matches)) {
        $_SESSION['flash'] = "Termination request not found or already processed.";
        header("Location: dashboard.php");
        exit();
    }

    $contractId = (int)$matches[1];
    $contractStmt = $conn->prepare("SELECT id, tenant_id, stall_id, status FROM contracts WHERE id=? LIMIT 1");
    $contractStmt->bind_param("i", $contractId);
    $contractStmt->execute();
    $contract = $contractStmt->get_result()->fetch_assoc();

    if (!$contract) {
        $_SESSION['flash'] = "The contract connected to this request no longer exists.";
        header("Location: dashboard.php");
        exit();
    }

    if ($action === 'reject_termination') {
        $markRead = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
        $markRead->bind_param("ii", $notificationId, $adminId);
        $markRead->execute();
        createNotification((int)$contract['tenant_id'], "Termination Request Rejected", "Your termination request for Contract ID: $contractId was rejected by admin.", "general");
        $_SESSION['flash'] = "Termination request rejected.";
        header("Location: dashboard.php");
        exit();
    }

    if ($contract['status'] !== 'active') {
        $_SESSION['flash'] = "This contract is no longer active and cannot be terminated.";
        header("Location: dashboard.php");
        exit();
    }

    $conn->begin_transaction();
    $terminate = $conn->prepare("UPDATE contracts SET status='terminated' WHERE id=? AND status='active'");
    $terminate->bind_param("i", $contractId);
    $releaseStall = $conn->prepare("UPDATE stalls SET status='available' WHERE id=?");
    $releaseStall->bind_param("i", $contract['stall_id']);
    $markRead = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $markRead->bind_param("ii", $notificationId, $adminId);

    if ($terminate->execute() && $releaseStall->execute() && $markRead->execute()) {
        $conn->commit();
        createNotification((int)$contract['tenant_id'], "Termination Request Approved", "Your termination request for Contract ID: $contractId was approved by admin.", "general");
        $_SESSION['flash'] = "Termination approved. The contract was terminated and the stall is available.";
    } else {
        $conn->rollback();
        $_SESSION['flash'] = "Unable to approve the termination request.";
    }

    header("Location: dashboard.php");
    exit();
}

header("Location: notifications.php");
exit();
?>
