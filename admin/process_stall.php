<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $stall_number = sanitize($_POST['stall_number'] ?? '');
    $stall_name = sanitize($_POST['stall_name'] ?? '');
    $location_description = sanitize($_POST['location_description'] ?? '');
    $monthly_rate = (float)str_replace(',', '', $_POST['monthly_rate'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'available');

    $check = $conn->prepare("SELECT id FROM stalls WHERE stall_number=? LIMIT 1");
    $check->bind_param("s", $stall_number);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['flash'] = "Stall number already exists.";
        header("Location: stalls.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO stalls(stall_number, stall_name, location_description, monthly_rate, status) VALUES(?,?,?,?,?)");
    $stmt->bind_param("sssds", $stall_number, $stall_name, $location_description, $monthly_rate, $status);
    $stmt->execute();

    $_SESSION['flash'] = "Stall created successfully.";
    header("Location: stalls.php");
    exit();
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $stall_number = sanitize($_POST['stall_number'] ?? '');
    $stall_name = sanitize($_POST['stall_name'] ?? '');
    $location_description = sanitize($_POST['location_description'] ?? '');
    $monthly_rate = (float)str_replace(',', '', $_POST['monthly_rate'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'available');

    $check = $conn->prepare("SELECT id FROM stalls WHERE stall_number=? AND id<>? LIMIT 1");
    $check->bind_param("si", $stall_number, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['flash'] = "Stall number already used by another stall.";
        header("Location: stalls.php?edit_id=" . $id);
        exit();
    }

    $stmt = $conn->prepare("UPDATE stalls SET stall_number=?, stall_name=?, location_description=?, monthly_rate=?, status=? WHERE id=?");
    $stmt->bind_param("sssdsi", $stall_number, $stall_name, $location_description, $monthly_rate, $status, $id);
    $stmt->execute();

    $_SESSION['flash'] = "Stall updated successfully.";
    header("Location: stalls.php");
    exit();
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $check = $conn->prepare("SELECT status FROM stalls WHERE id=? LIMIT 1");
    $check->bind_param("i", $id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    if ($row && $row['status'] === 'occupied') {
        $_SESSION['flash'] = "Cannot delete an occupied stall. Terminate the contract first.";
        header("Location: stalls.php");
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM stalls WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['flash'] = "Stall deleted successfully.";
    header("Location: stalls.php");
    exit();
}

header("Location: stalls.php");
exit();