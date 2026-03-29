<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $business_name = sanitize($_POST['business_name'] ?? '');
    $business_type = sanitize($_POST['business_type'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $_SESSION['flash'] = "Email already exists. Try another.";
        header("Location: tenants.php");
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users(full_name, email, phone, business_name, business_type, address, password, role, status) VALUES(?,?,?,?,?,?,?, 'tenant', ?)");
    $stmt->bind_param("ssssssss", $full_name, $email, $phone, $business_name, $business_type, $address, $hashedPassword, $status);
    
    if ($stmt->execute()) {
        $_SESSION['flash'] = "Tenant account created successfully.";
    } else {
        $_SESSION['flash'] = "Error creating account: " . $conn->error;
    }
    
    header("Location: tenants.php");
    exit();
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $business_name = sanitize($_POST['business_name'] ?? '');
    $business_type = sanitize($_POST['business_type'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';

    // Avoid duplicate email on other users
    $check = $conn->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $check->bind_param("si", $email, $id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $_SESSION['flash'] = "Email already used by another account.";
        header("Location: tenants.php?edit_id=" . $id);
        exit();
    }

    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, business_name=?, business_type=?, address=?, status=?, password=? WHERE id=? AND role='tenant'");
        $stmt->bind_param("ssssssssi", $full_name, $email, $phone, $business_name, $business_type, $address, $status, $hashedPassword, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, business_name=?, business_type=?, address=?, status=? WHERE id=? AND role='tenant'");
        $stmt->bind_param("sssssssi", $full_name, $email, $phone, $business_name, $business_type, $address, $status, $id);
    }
    
    $stmt->execute();

    $_SESSION['flash'] = "Tenant updated successfully.";
    header("Location: tenants.php");
    exit();
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='tenant'");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['flash'] = "Tenant deleted successfully.";
    header("Location: tenants.php");
    exit();
}

header("Location: tenants.php");
exit();