<?php
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

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

if (!$c) {
    die("Contract not found.");
}

// Security: Check if user is Admin OR is the specific Tenant who owns the contract
if (!isAdmin()) {
    if (!isTenant() || (int)$c['tenant_id'] !== (int)$_SESSION['user_id']) {
        header("Location: ../index.php");
        exit();
    }
}

// Import FPDF
require_once __DIR__ . '/../includes/fpdf/fpdf.php';

class ContractPDF extends FPDF {
    // Header
    function Header() {
        // Logo or Company Header
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(33, 33, 33);
        $this->Cell(0, 10, 'A&J ALFRESCO', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Food Stall Rental Management System', 0, 1, 'C');
        
        // Draw line separator
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, 32, 195, 32);
        $this->Ln(8);
    }

    // Footer
    function Footer() {
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        
        // Horizontal line
        $this->SetDrawColor(230, 230, 230);
        $this->Line(15, $this->GetY() - 2, 195, $this->GetY() - 2);
        
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

// Helper to format currency for PDF (replaces Peso sign with PHP to avoid FPDF charset issues)
function formatMoneyPDF($amount) {
    return 'PHP ' . number_format((float)$amount, 2, '.', ',');
}

// Create instance of PDF
$pdf = new ContractPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

// Document Title
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'FOOD STALL RENTAL AGREEMENT', 0, 1, 'C');
$pdf->Ln(5);

// Parties Section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, '1. PARTIES TO THE AGREEMENT', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

$partiesText = "This Rental Agreement is made and entered into on " . date('F d, Y') . ", by and between:\n\n" .
               "LANDLORD: A&J Alfresco, operating the stall facilities.\n\n" .
               "TENANT: " . $c['tenant_name'] . " (" . $c['business_name'] . "), residing at " . ($c['tenant_address'] ? $c['tenant_address'] : 'N/A') . ".";
$pdf->MultiCell(0, 5, $partiesText);
$pdf->Ln(6);

// Premises Section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, '2. PREMISES', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$premisesText = "Landlord hereby leases to Tenant, and Tenant hereby leases from Landlord, the rental space designated as Stall " . $c['stall_number'] . " described as " . ($c['location_description'] ? $c['location_description'] : 'N/A') . ".";
$pdf->MultiCell(0, 5, $premisesText);
$pdf->Ln(6);

// Term Section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, '3. LEASE TERM', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$termText = "This Agreement shall be for a duration of " . $c['duration_type'] . ", commencing on " . formatDate($c['start_date']) . " and ending on " . formatDate($c['end_date']) . ".";
$pdf->MultiCell(0, 5, $termText);
$pdf->Ln(6);

// Financials Section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, '4. RENT AND DEPOSIT TERMS', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

// Rent
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 6, 'Monthly Rent:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, formatMoneyPDF($c['monthly_rent']), 0, 1);

// Deposit
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 6, 'Security Deposit:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, formatMoneyPDF($c['deposit_amount']), 0, 1);

$pdf->Ln(3);
$financialNotes = "The Tenant shall pay the security deposit upon signing this Agreement. In the event of early termination by the Tenant, the security deposit shall be forfeited as liquidated damages. Rent is due and payable on or before the due date of each month, regardless of stall usage.";
$pdf->MultiCell(0, 5, $financialNotes);
$pdf->Ln(6);

// Rules Section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, '5. EARLY TERMINATION POLICIES', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$rulesText = "If the Tenant vacates the stall prior to the expiration of the lease term, the following rules apply:\n" .
            "- Forfeiture of Deposit: The security deposit will be retained by the Landlord.\n" .
            "- Liability for Remaining Rent: The Tenant remains liable for rent until the end of the term, or until the Landlord secures a new tenant.\n" .
            "- Pre-Termination Option: The Tenant may elect to terminate early by paying an additional pre-termination fee equivalent to one (1) month's rent.";
$pdf->MultiCell(0, 5, $rulesText);
$pdf->Ln(6);

// Landlord Termination
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, '6. TERMINATION BY LANDLORD', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$landlordTermText = "The Landlord reserves the right to terminate this lease agreement immediately if the Tenant fails to pay rent for two (2) consecutive months, or if the Tenant violates health, safety, or operational regulations.";
$pdf->MultiCell(0, 5, $landlordTermText);
$pdf->Ln(6);

// Special Terms Section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, '7. SPECIAL TERMS & CONDITIONS', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$specialTerms = $c['terms'] ? trim($c['terms']) : 'No special terms or conditions specified.';
$pdf->MultiCell(0, 5, $specialTerms);
$pdf->Ln(15);

// Signature Section (with Page Break check if needed)
if ($pdf->GetY() > 240) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(90, 6, 'LANDLORD SIGNATURE', 0, 0, 'L');
$pdf->Cell(90, 6, 'TENANT SIGNATURE', 0, 1, 'L');
$pdf->Ln(15); // space for actual signature

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(90, 5, '___________________________', 0, 0, 'L');
$pdf->Cell(90, 5, '___________________________', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 5, 'A&J Alfresco Representative', 0, 0, 'L');
$pdf->Cell(90, 5, $c['tenant_name'], 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 4, 'Date: _________________', 0, 0, 'L');
$pdf->Cell(90, 4, 'Date: _________________', 0, 1, 'L');

// Output PDF to browser
$pdf->Output('I', 'Contract_Stall_' . $c['stall_number'] . '.pdf');
?>
