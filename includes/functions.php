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

function auditLog($action, $entityType, $entityId = null, array $details = []) {
    global $conn;

    $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $actorName = $_SESSION['full_name'] ?? 'System';
    $actorRole = $_SESSION['role'] ?? 'system';
    $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt = $conn->prepare("
        INSERT INTO audit_logs(actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES(?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "issssisss",
        $actorId,
        $actorName,
        $actorRole,
        $action,
        $entityType,
        $entityId,
        $detailsJson,
        $ipAddress,
        $userAgent
    );
    $stmt->execute();
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
    if ($status === 'for_renewal') return 'for_renewal';
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
    return $conn->insert_id;
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
        $mail->CharSet    = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;

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

function tenantAutoReminders($tenantId, ?DateTime $reminderDate = null) {
    global $conn;

    // Get active contract
    $stmt = $conn->prepare("SELECT id, start_date, end_date FROM contracts WHERE tenant_id=? AND status='active' ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    if (!$contract) return;

    $today = $reminderDate ? clone $reminderDate : new DateTime('today');
    $today->setTime(0, 0, 0);
    $currentMonth = $today->format('Y-m');
    $startDate = new DateTime($contract['start_date']);
    $rentDueDay = (int)$startDate->format('j');
    $dueDay = min($rentDueDay, (int)$today->format('t'));
    $currentDue = new DateTime($currentMonth . '-01');
    $currentDue->modify('+' . ($dueDay - 1) . ' days');

    if ($startDate <= $today && $currentDue < $today) {
        $paidStmt = $conn->prepare("SELECT id FROM payments WHERE contract_id=? AND payment_for_month=? AND status='paid' LIMIT 1");
        $paidStmt->bind_param("is", $contract['id'], $currentMonth);
        $paidStmt->execute();

        if ($paidStmt->get_result()->num_rows === 0) {
            $monthStr = $currentDue->format('F Y');
            $daysOverdue = (int)$currentDue->diff($today)->days;
            foreach ([1, 3, 7] as $overdueMilestone) {
                if ($daysOverdue < $overdueMilestone) continue;

                $milestoneKey = "overdue|contract#{$contract['id']}|$currentMonth|{$currentDue->format('Y-m-d')}|{$overdueMilestone}d";
                $chkOverdue = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='due_date' AND message LIKE ? LIMIT 1");
                $likeOverdue = "%$milestoneKey%";
                $chkOverdue->bind_param("is", $tenantId, $likeOverdue);
                $chkOverdue->execute();

                if ($chkOverdue->get_result()->num_rows > 0) continue;

                $notificationId = createNotification(
                    $tenantId,
                    "Rent Overdue — $monthStr",
                    "[$milestoneKey] Your rent for $monthStr is at least $overdueMilestone day(s) overdue. Please make your payment as soon as possible.",
                    "due_date"
                );

                $uStmtOverdue = $conn->prepare("SELECT full_name, email FROM users WHERE id=? LIMIT 1");
                $uStmtOverdue->bind_param("i", $tenantId);
                $uStmtOverdue->execute();
                $userOverdue = $uStmtOverdue->get_result()->fetch_assoc();

                if ($userOverdue && !empty($userOverdue['email'])) {
                    $subjectOverdue = "Rent Overdue — $overdueMilestone-Day Reminder — $monthStr — A&J Alfresco";
                    $dueLabel = $currentDue->format('M d, Y');
                    $htmlOverdue = "
                    <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
                      <div style='background:#d63384;padding:24px;text-align:center;'><h2 style='color:#fff;margin:0;'>A&amp;J Alfresco</h2><p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Rental Management System</p></div>
                      <div style='padding:28px 32px;background:#fff;'>
                        <p style='font-size:15px;color:#1e293b;'>Hi <strong>{$userOverdue['full_name']}</strong>,</p>
                        <p style='color:#475569;line-height:1.7;'>Your <strong>monthly rent</strong> for <strong>$monthStr</strong> is overdue.</p>
                        <div style='background:#fef2f2;border-left:4px solid #dc2626;border-radius:6px;padding:14px 18px;margin:20px 0;'><p style='margin:0 0 6px;font-size:14px;color:#1e293b;'><strong>Due Date:</strong> $dueLabel</p><p style='margin:0 0 6px;font-size:13px;color:#dc2626;font-weight:700;'>🔴 Reminder: $overdueMilestone day(s) overdue</p><p style='margin:0;font-size:12px;color:#64748b;'>Currently overdue by $daysOverdue day(s)</p></div>
                        <p style='color:#475569;line-height:1.7;'>Please make your payment as soon as possible to keep your account up to date. If you have already paid, please disregard this message.</p>
                      </div>
                      <div style='background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #eee;'><p style='color:#94a3b8;font-size:11px;margin:0;'>&copy; " . date('Y') . " A&amp;J Alfresco. All rights reserved.</p></div>
                    </div>";

                    if (!sendMail($userOverdue['email'], $userOverdue['full_name'], $subjectOverdue, $htmlOverdue)) {
                        $deleteNotification = $conn->prepare("DELETE FROM notifications WHERE id=?");
                        $deleteNotification->bind_param("i", $notificationId);
                        $deleteNotification->execute();
                    }
                }
            }
        }
    }

    // (A) Rent due reminder: based on the start date, find the NEXT due date
    if ($startDate > $today) {
        $nextDue = clone $startDate;
    } else {
        $nextDue = new DateTime($today->format('Y-m-01'));
        $nextDueDay = min($rentDueDay, (int)$nextDue->format('t'));
        $nextDue->setDate((int)$nextDue->format('Y'), (int)$nextDue->format('n'), $nextDueDay);

        if ($nextDue <= $today) {
            $nextDue->modify('first day of next month');
            $nextDueDay = min($rentDueDay, (int)$nextDue->format('t'));
            $nextDue->setDate((int)$nextDue->format('Y'), (int)$nextDue->format('n'), $nextDueDay);
        }
    }
    
    $daysToDue = (int)$today->diff($nextDue)->days;

    // Send the first missed milestone when the tenant opens the dashboard.
    $rentMilestone = $daysToDue <= 1 ? 1 : ($daysToDue <= 3 ? 3 : ($daysToDue <= 7 ? 7 : 0));

    if ($rentMilestone > 0) {
        $monthStr  = $nextDue->format('F Y');
        $dueLabel  = date('M d, Y', strtotime($nextDue->format('Y-m-d')));

        // Deduplication: one notification per milestone per month
        $milestoneKey = "rent|contract#{$contract['id']}|{$nextDue->format('Y-m-d')}|{$rentMilestone}d";
        $chk = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='due_date' AND message LIKE ? LIMIT 1");
        $likeMsg = "%$milestoneKey%";
        $chk->bind_param("is", $tenantId, $likeMsg);
        $chk->execute();

        if ($chk->get_result()->num_rows === 0) {
            // Urgency label
            if ($rentMilestone === 1) {
                $urgency = "TOMORROW";
                $badge   = "🔴";
                $color   = "#dc2626";
            } elseif ($rentMilestone === 3) {
                $urgency = "in $daysToDue DAYS";
                $badge   = "🟠";
                $color   = "#ea580c";
            } else {
                $urgency = "in $daysToDue DAYS";
                $badge   = "🟡";
                $color   = "#d97706";
            }

            // In-app notification (includes milestone key for dedup)
            $notificationId = createNotification(
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

                if (!sendMail($user['email'], $user['full_name'], $subject, $html)) {
                    $deleteNotification = $conn->prepare("DELETE FROM notifications WHERE id=?");
                    $deleteNotification->bind_param("i", $notificationId);
                    $deleteNotification->execute();
                }
            }
        }
    }

    // (B) Contract expiry reminder — send the first missed 90, 60, or 30-day milestone
    $expiryMilestones = [90, 60, 30]; // 3 months, 2 months, 1 month

    $endDate = new DateTime($contract['end_date']);
    $daysLeft = $today <= $endDate ? (int)$today->diff($endDate)->days : -1;

    $expiryMilestone = $daysLeft > 0 ? ($daysLeft <= 30 ? 30 : ($daysLeft <= 60 ? 60 : ($daysLeft <= 90 ? 90 : 0))) : 0;

    if ($expiryMilestone > 0) {
        $endLabel = date('M d, Y', strtotime($contract['end_date']));

        // Urgency label per milestone
        if ($expiryMilestone === 30) {
            $urgency  = "1 MONTH";   $badge = "🔴"; $color = "#dc2626";
        } elseif ($expiryMilestone === 60) {
            $urgency  = "2 MONTHS";  $badge = "🟠"; $color = "#ea580c";
        } else {
            $urgency  = "3 MONTHS";  $badge = "🟡"; $color = "#d97706";
        }

        // Deduplication key: one notification per milestone per contract
        $milestoneKey = "contract#{$contract['id']}|{$endDate->format('Y-m-d')}|{$expiryMilestone}d";
        $chk2 = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type='contract_expiry' AND message LIKE ? LIMIT 1");
        $likeMsg2 = "%$milestoneKey%";
        $chk2->bind_param("is", $tenantId, $likeMsg2);
        $chk2->execute();

        if ($chk2->get_result()->num_rows === 0) {
            // In-app notification
            $notificationId = createNotification(
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

                if (!sendMail($user3['email'], $user3['full_name'], $subject3, $html3)) {
                    $deleteNotification = $conn->prepare("DELETE FROM notifications WHERE id=?");
                    $deleteNotification->bind_param("i", $notificationId);
                    $deleteNotification->execute();
                }
            }
        }
    }

}

function runAutomaticReminders(?DateTime $reminderDate = null) {
    global $conn;

    $result = $conn->query("SELECT DISTINCT tenant_id FROM contracts WHERE status='active'");
    if (!$result) return;

    while ($row = $result->fetch_assoc()) {
        tenantAutoReminders((int)$row['tenant_id'], $reminderDate);
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