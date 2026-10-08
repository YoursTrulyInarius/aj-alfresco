<?php
require_once __DIR__ . '/../includes/functions.php';
requireTenant();

$tenantId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

function getActiveAdminId() {
    global $conn;

    $admin = $conn->query("SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1")->fetch_assoc();
    return $admin ? (int)$admin['id'] : 0;
}

function notifyActiveAdmin($title, $message) {
    $adminId = getActiveAdminId();
    if ($adminId > 0) createNotification($adminId, $title, $message, 'general');
    return $adminId;
}

if ($action === 'request_renewal') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("SELECT id, end_date FROM contracts WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
    $stmt->bind_param("ii", $id, $tenantId);
    $stmt->execute();
    $renewalContract = $stmt->get_result()->fetch_assoc();
    $ok = false;
    if ($renewalContract) {
        $today = new DateTime('today');
        $endDate = new DateTime($renewalContract['end_date']);
        $daysLeft = $today <= $endDate ? (int)$today->diff($endDate)->days : -1;
        $ok = $daysLeft >= 0 && $daysLeft <= 30;
    }

    if (!$ok) {
        $_SESSION['flash'] = "Renewal is available only when your contract has 30 days or less remaining.";
        header("Location: contract.php");
        exit();
    }

    $up = $conn->prepare("UPDATE contracts SET status='pending_renewal' WHERE id=? AND tenant_id=?");
    $up->bind_param("ii", $id, $tenantId);
    if ($up->execute() && $up->affected_rows > 0) {
        auditLog('renewal_requested', 'contract', $id, [
            'previous_status' => 'active',
            'requested_status' => 'pending_renewal'
        ]);
    }

    notifyActiveAdmin(
        "Renewal Request",
        "Tenant " . $_SESSION['full_name'] . " requested contract renewal (Contract ID: $id)."
    );

    $_SESSION['flash'] = "Renewal request sent to admin.";
    header("Location: contract.php");
    exit();
}

if ($action === 'terminate') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("SELECT id, status FROM contracts WHERE id=? AND tenant_id=? LIMIT 1");
    $stmt->bind_param("ii", $id, $tenantId);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();

    if (!$contract || $contract['status'] !== 'active') {
        $_SESSION['flash'] = "Contract termination request is not available.";
        header("Location: contract.php");
        exit();
    }

    $adminId = getActiveAdminId();
    if ($adminId <= 0) {
        $_SESSION['flash'] = "No active administrator is available to review this request.";
    } else {
        $likeMessage = "%Contract ID: $id%";
        $pending = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND title='Termination Request' AND message LIKE ? AND is_read=0 LIMIT 1");
        $pending->bind_param("is", $adminId, $likeMessage);
        $pending->execute();

        if ($pending->get_result()->num_rows > 0) {
            $_SESSION['flash'] = "Your termination request is already pending admin review.";
        } else {
            createNotification(
                $adminId,
                "Termination Request",
                "Tenant " . $_SESSION['full_name'] . " requested termination for Contract ID: $id.",
                "general"
            );
            auditLog('termination_requested', 'contract', $id, [
                'status' => $contract['status']
            ]);
            $_SESSION['flash'] = "Termination request sent to admin for approval.";
        }
    }

    header("Location: contract.php");
    exit();
}

header("Location: contract.php");
exit();
