<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/smtp.php';

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

function contractDisplayStatus($status, $endDate) {
    if ($status === 'pending_renewal') return 'pending_renewal';
    if ($status === 'terminated') return 'terminated';

    if ($status === 'active' && $endDate) {
        $today = new DateTime('today');
        $contractEnd = new DateTime($endDate);
        $daysLeft = $today <= $contractEnd ? (int)$today->diff($contractEnd)->days : -1;
        if ($daysLeft >= 0 && $daysLeft <= 30) return 'for_renewal';
    }

    return 'active';
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

function sendMail($toEmail, $toName, $subject, $htmlBody) {
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/../PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return false;
    }
}

function tenantAutoReminders($tenantId) {
    global $conn;

    // Get active contract
    $stmt = $conn->prepare("SELECT id, start_date, end_date FROM contracts WHERE tenant_id=? AND status='active' ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    if (!$contract) return;

    $today = new DateTime('today');
    $currentMonth = $today->format('Y-m');
    $startDate = new DateTime($contract['start_date']);
    $dueDay = min((int)(new DateTime($contract['start_date']))->format('d'), (int)$today->format('t'));
    $currentDue = new DateTime($currentMonth . '-01');
    $currentDue->modify('+' . ($dueDay - 1) . ' days');

    if ($startDate <= $today && $currentDue < $today) {
        $paidStmt = $conn->prepare("SELECT id FROM payments WHERE contract_id=? AND payment_for_month=? AND status='paid' LIMIT 1");
        $paidStmt->bind_param("is", $contract['id'], $currentMonth);
        $paidStmt->execute();

        if ($paidStmt->get_result()->num_rows === 0) {
            $monthStr = $currentDue->format('F Y');
            $daysOverdue = (int)$currentDue->diff($today)->days;
            $milestoneKey = "overdue|$currentMonth";
            $chkOverdue = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='due_date' AND message LIKE ? LIMIT 1");
            $likeOverdue = "%$milestoneKey%";
            $chkOverdue->bind_param("is", $tenantId, $likeOverdue);
            $chkOverdue->execute();

            if ($chkOverdue->get_result()->num_rows === 0) {
                createNotification(
                    $tenantId,
                    "Rent Overdue — $monthStr",
                    "[$milestoneKey] Your rent for $monthStr is overdue by $daysOverdue day(s). Please make your payment as soon as possible.",
                    "due_date"
                );

                $uStmtOverdue = $conn->prepare("SELECT full_name, email FROM users WHERE id=? LIMIT 1");
                $uStmtOverdue->bind_param("i", $tenantId);
                $uStmtOverdue->execute();
                $userOverdue = $uStmtOverdue->get_result()->fetch_assoc();

                if ($userOverdue && !empty($userOverdue['email'])) {
                    $subjectOverdue = "Rent Overdue — $monthStr — A&J Alfresco";
                    $dueLabel = $currentDue->format('M d, Y');
                    $htmlOverdue = "
                    <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
                      <div style='background:#d63384;padding:24px;text-align:center;'><h2 style='color:#fff;margin:0;'>A&amp;J Alfresco</h2><p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Rental Management System</p></div>
                      <div style='padding:28px 32px;background:#fff;'>
                        <p style='font-size:15px;color:#1e293b;'>Hi <strong>{$userOverdue['full_name']}</strong>,</p>
                        <p style='color:#475569;line-height:1.7;'>Your <strong>monthly rent</strong> for <strong>$monthStr</strong> is overdue.</p>
                        <div style='background:#fef2f2;border-left:4px solid #dc2626;border-radius:6px;padding:14px 18px;margin:20px 0;'><p style='margin:0 0 6px;font-size:14px;color:#1e293b;'><strong>Due Date:</strong> $dueLabel</p><p style='margin:0;font-size:13px;color:#dc2626;font-weight:700;'>🔴 Days Overdue: $daysOverdue</p></div>
                        <p style='color:#475569;line-height:1.7;'>Please make your payment as soon as possible to keep your account up to date. If you have already paid, please disregard this message.</p>
                      </div>
                      <div style='background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #eee;'><p style='color:#94a3b8;font-size:11px;margin:0;'>&copy; " . date('Y') . " A&amp;J Alfresco. All rights reserved.</p></div>
                    </div>";

                    sendMail($userOverdue['email'], $userOverdue['full_name'], $subjectOverdue, $htmlOverdue);
                }
            }
        }
    }

    // (A) Rent due reminder: based on the start date, find the NEXT due date
    $nextDue = new DateTime($contract['start_date']);
    
    // Increment by 1 month until we hit the first future due date
    while ($nextDue <= $today) {
        $nextDue->modify('+1 month');
    }
    
    $daysToDue = (int)$today->diff($nextDue)->days;

    // (A) Rent due reminder — fire at exactly 7, 3, and 1 days before
    $reminderMilestones = [7, 3, 1];

    if (in_array($daysToDue, $reminderMilestones)) {
        $monthStr  = $nextDue->format('F Y');
        $dueLabel  = date('M d, Y', strtotime($nextDue->format('Y-m-d')));

        // Deduplication: one notification per milestone per month
        $milestoneKey = "$monthStr|{$daysToDue}d";
        $chk = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='due_date' AND message LIKE ? LIMIT 1");
        $likeMsg = "%$milestoneKey%";
        $chk->bind_param("is", $tenantId, $likeMsg);
        $chk->execute();

        if ($chk->get_result()->num_rows === 0) {
            // Urgency label
            if ($daysToDue === 1) {
                $urgency = "TOMORROW";
                $badge   = "🔴";
                $color   = "#dc2626";
            } elseif ($daysToDue === 3) {
                $urgency = "in 3 DAYS";
                $badge   = "🟠";
                $color   = "#ea580c";
            } else {
                $urgency = "in 1 WEEK";
                $badge   = "🟡";
                $color   = "#d97706";
            }

            // In-app notification (includes milestone key for dedup)
            createNotification(
                $tenantId,
                "Rent Due $urgency",
                "[$milestoneKey] Your rent for $monthStr is due $urgency. (Due on $dueLabel)",
                "due_date"
            );

            // Fetch tenant email
            $uStmt = $conn->prepare("SELECT full_name, email FROM users WHERE id=? LIMIT 1");
            $uStmt->bind_param("i", $tenantId);
            $uStmt->execute();
            $user = $uStmt->get_result()->fetch_assoc();

            if ($user && !empty($user['email'])) {
                $subject = "Rent Due $urgency — A&J Alfresco";
                $html = "
                <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
                  <div style='background:#d63384;padding:24px;text-align:center;'>
                    <h2 style='color:#fff;margin:0;'>A&amp;J Alfresco</h2>
                    <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Rental Management System</p>
                  </div>
                  <div style='padding:28px 32px;background:#fff;'>
                    <p style='font-size:15px;color:#1e293b;'>Hi <strong>{$user['full_name']}</strong>,</p>
                    <p style='color:#475569;line-height:1.7;'>This is a reminder that your <strong>monthly rent</strong> for <strong>$monthStr</strong> is due <strong style='color:$color;'>$urgency</strong>.</p>
                    <div style='background:#fdf2f7;border-left:4px solid $color;border-radius:6px;padding:14px 18px;margin:20px 0;'>
                      <p style='margin:0 0 6px;font-size:14px;color:#1e293b;'><strong>Due Date:</strong> $dueLabel</p>
                      <p style='margin:0;font-size:13px;color:$color;font-weight:700;'>$badge Days Remaining: $daysToDue</p>
                    </div>
                    <p style='color:#475569;line-height:1.7;'>Please ensure your payment is made on or before the due date to avoid penalties.</p>
                    <p style='color:#94a3b8;font-size:12px;margin-top:28px;'>If you have already paid, please disregard this message.</p>
                  </div>
                  <div style='background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #eee;'>
                    <p style='color:#94a3b8;font-size:11px;margin:0;'>&copy; " . date('Y') . " A&amp;J Alfresco. All rights reserved.</p>
                  </div>
                </div>";

                sendMail($user['email'], $user['full_name'], $subject, $html);
            }
        }
    }

    // (B) Contract expiry reminder — fire at exactly 90, 60, and 30 days before end date
    $expiryMilestones = [90, 60, 30]; // 3 months, 2 months, 1 month

    $stmt2 = $conn->prepare("SELECT DATEDIFF(end_date, CURDATE()) AS days_left FROM contracts WHERE id=? LIMIT 1");
    $stmt2->bind_param("i", $contract['id']);
    $stmt2->execute();
    $daysLeft = (int)($stmt2->get_result()->fetch_assoc()['days_left'] ?? 9999);

    if (in_array($daysLeft, $expiryMilestones)) {
        $endLabel = date('M d, Y', strtotime($contract['end_date']));

        // Urgency label per milestone
        if ($daysLeft === 30) {
            $urgency  = "1 MONTH";   $badge = "🔴"; $color = "#dc2626";
        } elseif ($daysLeft === 60) {
            $urgency  = "2 MONTHS";  $badge = "🟠"; $color = "#ea580c";
        } else {
            $urgency  = "3 MONTHS";  $badge = "🟡"; $color = "#d97706";
        }

        // Deduplication key: one notification per milestone per contract
        $milestoneKey = "contract#{$contract['id']}|{$daysLeft}d";
        $chk2 = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='contract_expiry' AND message LIKE ? LIMIT 1");
        $likeMsg2 = "%$milestoneKey%";
        $chk2->bind_param("is", $tenantId, $likeMsg2);
        $chk2->execute();

        if ($chk2->get_result()->num_rows === 0) {
            // In-app notification
            createNotification(
                $tenantId,
                "Contract Expiring in $urgency",
                "[$milestoneKey] Your contract ends on $endLabel — that's $daysLeft day(s) away. Please request renewal if needed.",
                "contract_expiry"
            );

            // Fetch tenant email
            $uStmt3 = $conn->prepare("SELECT full_name, email FROM users WHERE id=? LIMIT 1");
            $uStmt3->bind_param("i", $tenantId);
            $uStmt3->execute();
            $user3 = $uStmt3->get_result()->fetch_assoc();

            if ($user3 && !empty($user3['email'])) {
                $subject3 = "Contract Expiring in $urgency — A&J Alfresco";
                $html3 = "
                <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
                  <div style='background:#d63384;padding:24px;text-align:center;'>
                    <h2 style='color:#fff;margin:0;'>A&amp;J Alfresco</h2>
                    <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Rental Management System</p>
                  </div>
                  <div style='padding:28px 32px;background:#fff;'>
                    <p style='font-size:15px;color:#1e293b;'>Hi <strong>{$user3['full_name']}</strong>,</p>
                    <p style='color:#475569;line-height:1.7;'>We would like to inform you that your <strong>stall rental contract</strong> with A&amp;J Alfresco is expiring in <strong style='color:$color;'>$urgency</strong>.</p>
                    <div style='background:#fdf2f7;border-left:4px solid $color;border-radius:6px;padding:14px 18px;margin:20px 0;'>
                      <p style='margin:0 0 6px;font-size:14px;color:#1e293b;'><strong>Contract End Date:</strong> $endLabel</p>
                      <p style='margin:0;font-size:13px;color:$color;font-weight:700;'>$badge Days Remaining: $daysLeft</p>
                    </div>
                    <p style='color:#475569;line-height:1.7;'>If you wish to continue renting, please <strong>submit a renewal request</strong> through your tenant portal or contact the A&amp;J Alfresco admin directly.</p>
                    <p style='color:#94a3b8;font-size:12px;margin-top:28px;'>If you no longer wish to renew, please disregard this message.</p>
                  </div>
                  <div style='background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #eee;'>
                    <p style='color:#94a3b8;font-size:11px;margin:0;'>&copy; " . date('Y') . " A&amp;J Alfresco. All rights reserved.</p>
                  </div>
                </div>";

                sendMail($user3['email'], $user3['full_name'], $subject3, $html3);
            }
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