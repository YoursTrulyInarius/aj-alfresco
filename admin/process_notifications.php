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

header("Location: notifications.php");
exit();
?>
