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
    $stallId = (int)($_POST['stall_id'] ?? 0);

    if ($stallId <= 0) {
        $_SESSION['flash'] = "Please select a stall to assign to the new tenant.";
        header("Location: tenants.php");
        exit();
    }

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
        $newTenantId = $stmt->insert_id;
        $stallId = (int)($_POST['stall_id'] ?? 0);

        if ($stallId > 0) {
            $stall = $conn->query("SELECT monthly_rate, status FROM stalls WHERE id=$stallId")->fetch_assoc();
            if ($stall && $stall['status'] === 'available') {
                $rent = $stall['monthly_rate'];
                $today = date('Y-m-d');
                $oneYear = date('Y-m-d', strtotime('+1 year'));
                
                $cStmt = $conn->prepare("INSERT INTO contracts(tenant_id, stall_id, start_date, end_date, monthly_rent, status) VALUES(?,?,?,?,?,'active')");
                $cStmt->bind_param("iissd", $newTenantId, $stallId, $today, $oneYear, $rent);
                $cStmt->execute();
                
                $conn->query("UPDATE stalls SET status='occupied' WHERE id=$stallId");
                $_SESSION['flash'] = "Tenant created and assigned to stall successfully.";
            } else {
                $_SESSION['flash'] = "Tenant created, but stall was no longer available.";
            }
        } else {
            $_SESSION['flash'] = "Tenant account created successfully.";
        }
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
    
    if ($stmt->execute()) {
        $stallId = (int)($_POST['stall_id'] ?? 0);
        $originalStallId = (int)($_POST['original_stall_id'] ?? 0);

        if ($stallId > 0 && $stallId !== $originalStallId) {
            // 1. Terminate current active contract for this tenant
            $conn->query("UPDATE contracts SET status='terminated' WHERE tenant_id=$id AND status='active'");
            
            // 2. Clear status of the old stall
            if ($originalStallId > 0) {
                $conn->query("UPDATE stalls SET status='available' WHERE id=$originalStallId");
            }

            // 3. Assign new stall and create contract
            $stall = $conn->query("SELECT monthly_rate, status FROM stalls WHERE id=$stallId")->fetch_assoc();
            if ($stall && $stall['status'] === 'available') {
                $rent = $stall['monthly_rate'];
                $today = date('Y-m-d');
                $oneYear = date('Y-m-d', strtotime('+1 year'));
                
                $cStmt = $conn->prepare("INSERT INTO contracts(tenant_id, stall_id, start_date, end_date, monthly_rent, status) VALUES(?,?,?,?,?,'active')");
                $cStmt->bind_param("iissd", $id, $stallId, $today, $oneYear, $rent);
                $cStmt->execute();
                
                $conn->query("UPDATE stalls SET status='occupied' WHERE id=$stallId");
                $_SESSION['flash'] = "Tenant updated and stall transferred successfully.";
            } else {
                $_SESSION['flash'] = "Tenant info updated, but new stall was no longer available.";
            }
        } else {
            $_SESSION['flash'] = "Tenant updated successfully.";
        }
    } else {
        $_SESSION['flash'] = "Error updating tenant: " . $conn->error;
    }
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