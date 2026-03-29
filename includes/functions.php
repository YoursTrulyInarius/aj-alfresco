<?php
require_once __DIR__ . '/../config/database.php';

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTenant() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'tenant';
}

function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: ../index.php");
        exit();
    }
}

function requireTenant() {
    if (!isLoggedIn() || !isTenant()) {
        header("Location: ../index.php");
        exit();
    }
}

function sanitize($value) {
    global $conn;
    return htmlspecialchars(trim($conn->real_escape_string($value)));
}

function formatMoney($amount) {
    return '₱' . number_format((float)$amount, 2, '.', ',');
}

function formatDate($date) {
    if (!$date) return '';
    return date('M d, Y', strtotime($date));
}

function generateReceiptNumber() {
    return 'AJA-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function notifUnreadCount($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

function createNotification($userId, $title, $message, $type='general') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications(user_id,title,message,type) VALUES(?,?,?,?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
}
?>
<?php
// === Auto reminders for TENANT dashboard (pop-up notifications) ===
function tenantAutoReminders($tenantId) {
    global $conn;

    // Get active contract
    $stmt = $conn->prepare("SELECT id, end_date FROM contracts WHERE tenant_id=? AND status='active' ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    if (!$contract) return;

    // (A) Rent due reminder: due every 1st of month, remind within 5 days
    $today = new DateTime();
    $nextDue = new DateTime($today->format('Y-m') . '-01');
    if ((int)$today->format('d') > 1) $nextDue->modify('+1 month');
    $daysToDue = (int)$today->diff($nextDue)->days;

    if ($daysToDue > 0 && $daysToDue <= 5) {
        // only create once per day
        $chk = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='due_date' AND DATE(created_at)=CURDATE() LIMIT 1");
        $chk->bind_param("i", $tenantId);
        $chk->execute();

        if ($chk->get_result()->num_rows === 0) {
            createNotification(
                $tenantId,
                "Rent Due Reminder",
                "Your rent is due in $daysToDue day(s). (Due every 1st of the month)",
                "due_date"
            );
        }
    }

    // (B) Contract expiry reminder: within 14 days
    $stmt2 = $conn->prepare("SELECT DATEDIFF(?, CURDATE()) AS days_left");
    $stmt2->bind_param("s", $contract['end_date']);
    $stmt2->execute();
    $daysLeftRow = $stmt2->get_result()->fetch_assoc();
    $daysLeft = (int)($daysLeftRow['days_left'] ?? 9999);

    if ($daysLeft >= 0 && $daysLeft <= 14) {
        $chk2 = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='contract_expiry' AND DATE(created_at)=CURDATE() LIMIT 1");
        $chk2->bind_param("i", $tenantId);
        $chk2->execute();

        if ($chk2->get_result()->num_rows === 0) {
            createNotification(
                $tenantId,
                "Contract Expiring Soon",
                "Your contract will expire in $daysLeft day(s). Please request renewal if needed.",
                "contract_expiry"
            );
        }
    }
}

// Get unread notifications (for toast popups)
function getUnreadNotifications($userId, $limit = 3) {
    global $conn;
    $limit = (int)$limit;
    $sql = "SELECT id, title, message, type, created_at
            FROM notifications
            WHERE user_id=? AND is_read=0
            ORDER BY id DESC
            LIMIT $limit";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) $items[] = $row;
    return $items;
}
?>