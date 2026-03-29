<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("
    SELECT c.*, 
           u.full_name as tenant_name, u.business_name, u.address as tenant_address,
           s.stall_number, s.location_description
    FROM contracts c
    JOIN users u ON u.id = c.tenant_id
    JOIN stalls s ON s.id = c.stall_id
    WHERE c.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

if (!$c) die("Contract not found.");

$start = new DateTime($c['start_date']);
$end = new DateTime($c['end_date']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Contract - <?php echo htmlspecialchars($c['tenant_name']); ?></title>
    <style>
        body { font-family: serif; line-height: 1.6; color: #333; padding: 40px; }
        .contract-box { max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 50px; background: #fff; }
        h1 { text-align: center; text-transform: uppercase; font-size: 22px; margin-bottom: 30px; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; display: block; }
        .sig-row { display: flex; justify-content: space-between; margin-top: 60px; }
        .sig-col { width: 45%; border-top: 1px solid #000; text-align: center; padding-top: 5px; }
        @media print {
            body { padding: 0; }
            .contract-box { border: none; width: 100%; max-width: 100%; }
            .btn-print, .no-print, .btn-back { display: none !important; }
        }
        .btn-print, .btn-back {
            display: inline-block; width: 180px; margin: 5px; padding: 12px;
            background: #ffb6c1; border: none; color: #fff; font-weight: bold;
            cursor: pointer; border-radius: 8px; text-align: center; text-decoration: none;
            transition: .2s;
        }
        .btn-back { background: #666; }
        .btn-back:hover { background: #444; }
        .btn-print:hover { background: #ff91a4; }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <a href="contracts.php" class="btn-back">Go Back</a>
        <button class="btn-print" onclick="window.print()" style="display: inline-block;">Print Contract</button>
    </div>

    <div class="contract-box">
        <h1>Food Stall Rental Agreement</h1>

        <div class="section">
            <p><strong>Between:</strong><br>
            <strong>Landlord:</strong> A&J Alfresco<br>
            <strong>Tenant:</strong> <?php echo htmlspecialchars($c['tenant_name']); ?> (<?php echo htmlspecialchars($c['business_name']); ?>)</p>
        </div>

        <div class="section">
            <p><strong>Premises:</strong> Stall <?php echo htmlspecialchars($c['stall_number']); ?> - <?php echo htmlspecialchars($c['location_description']); ?></p>
            <p><strong>Term:</strong> <?php echo htmlspecialchars($c['duration_type']); ?>, commencing on <?php echo formatDate($c['start_date']); ?> and ending on <?php echo formatDate($c['end_date']); ?></p>
        </div>

        <div class="section">
            <span class="section-title">1. Security Deposit</span>
            <p>Tenant shall pay a security deposit equivalent to <?php echo formatMoney($c['deposit_amount']); ?> upon signing this Agreement.</p>
            <p>In the event of early termination by the Tenant, the security deposit shall be forfeited as liquidated damages.</p>
        </div>

        <div class="section">
            <span class="section-title">2. Rent Payment</span>
            <p>Tenant agrees to pay monthly rent of <?php echo formatMoney($c['monthly_rent']); ?> on or before the due date of each month.</p>
            <p>Rent is payable regardless of stall usage unless otherwise terminated under this Agreement.</p>
        </div>

        <div class="section">
            <span class="section-title">3. Early Termination</span>
            <p>If the Tenant vacates the stall before the end of the agreed term:</p>
            <ul>
                <li><strong>Forfeiture of Security Deposit:</strong> The deposit shall be retained by the Landlord.</li>
                <li><strong>Liability for Remaining Rent:</strong> Tenant remains liable for the balance of rent until the end of the contract term.</li>
                <li><strong>Mitigation:</strong> If the Landlord secures a new tenant, the original Tenant’s liability ceases from the date the new tenant begins paying rent.</li>
                <li><strong>Pre-Termination Fee:</strong> Tenant may terminate early by paying an additional fee equivalent to one (1) month’s rent.</li>
            </ul>
        </div>

        <div class="section">
            <span class="section-title">4. Termination by Landlord</span>
            <p>The Landlord may terminate this Agreement if:</p>
            <ul>
                <li>Tenant fails to pay rent for two consecutive months.</li>
                <li>Tenant violates stall rules, health regulations, or engages in unlawful activity.</li>
            </ul>
        </div>

        <div class="section">
            <span class="section-title">5. System Records</span>
            <p>Upon termination, the contract status shall be marked as “Terminated” in the A&J Alfresco system. Stall shall be made available for re-rental.</p>
        </div>

        <div class="section">
            <span class="section-title">6. Miscellaneous</span>
            <p>This Agreement constitutes the entire understanding between the parties. Amendments must be in writing and signed by both parties.</p>
        </div>

        <div class="sig-row">
            <div class="sig-col">
                Landlord: A&J Alfresco
            </div>
            <div class="sig-col">
                Tenant: <?php echo htmlspecialchars($c['tenant_name']); ?>
            </div>
        </div>
        <p style="text-align: center; margin-top: 30px;">Date: <?php echo date('M d, Y'); ?></p>
    </div>

</body>
</html>
