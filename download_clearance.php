<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_logged_in() || !is_student()) {
    redirect('login.php');
}

$student_id = $_GET['student_id'] ?? 0;

// Verify the student is requesting their own clearance form
if ($student_id != $_SESSION['user_id']) {
    die("Unauthorized access");
}

// Get student and clearance info
$stmt = $pdo->prepare("
    SELECT u.full_name, u.department, cs.approved_at 
    FROM users u
    JOIN clearance_status cs ON u.id = cs.student_id
    WHERE u.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Clearance information not found");
}

// Generate HTML that can be printed as PDF
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clearance Certificate</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto&display=swap');
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 40px 0;
        }
        .certificate {
            background: #fff;
            border: 4px double #2c3e50;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(44,62,80,0.12);
            padding: 48px 60px;
            max-width: 850px;
            margin: 0 auto;
            position: relative;
        }
        .header {
            text-align: center;
            margin-bottom: 36px;
        }
        .university-logo {
            width: 90px;
            margin-bottom: 12px;
        }
        .university-name {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #2c3e50;
        }
        .certificate-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a237e;
            margin-top: 8px;
            letter-spacing: 1px;
        }
        .certificate-id {
            font-size: 13px;
            color: #888;
            margin-top: 4px;
        }
        .content {
            margin: 40px 0 36px 0;
            font-size: 18px;
            color: #222;
        }
        .content strong {
            font-size: 20px;
            color: #1a237e;
        }
        .details-table {
            margin: 30px auto 0 auto;
            border-collapse: collapse;
            font-size: 18px;
        }
        .details-table td {
            padding: 8px 18px 8px 0;
        }
        .details-table .label {
            font-weight: bold;
            color: #2c3e50;
            width: 180px;
        }
        .success {
            margin-top: 38px;
            text-align: center;
            font-weight: bold;
            font-size: 20px;
            color: #388e3c;
            letter-spacing: 1px;
        }
        .signature-section {
            margin-top: 60px;
            text-align: right;
        }
        .signature-label {
            font-weight: bold;
            font-size: 16px;
            color: #1a237e;
        }
        .signature-line {
            margin: 36px 0 8px 0;
            border-top: 2px solid #2c3e50;
            width: 260px;
            display: inline-block;
        }
        .signatory {
            font-size: 15px;
            color: #222;
        }
        .seal {
            position: absolute;
            left: 40px;
            bottom: 40px;
            opacity: 0.13;
            width: 120px;
        }
        @media print {
            body { padding: 0; background: #fff; }
            .no-print { display: none; }
            .certificate { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <img src="assets/university-logo.png" alt="University Logo" class="university-logo" />
            <div class="university-name">BINGHAM UNIVERSITY</div>
            <div class="certificate-title">OFFICIAL CLEARANCE CERTIFICATE</div>
            <div class="certificate-id">Certificate #: <?= date('Y') . '-' . str_pad($student_id, 5, '0', STR_PAD_LEFT) ?></div>
        </div>
        
        <div class="content">
            <p><strong>This is to certify that:</strong></p>
            <table class="details-table">
                <tr>
                    <td class="label">Student Name:</td>
                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                </tr>
                <tr>
                    <td class="label">Department:</td>
                    <td><?= htmlspecialchars($student['department']) ?></td>
                </tr>
                <tr>
                    <td class="label">Date Approved:</td>
                    <td><?= date('F j, Y', strtotime($student['approved_at'])) ?></td>
                </tr>
            </table>
            <div class="success">
                HAS SUCCESSFULLY COMPLETED ALL CLEARANCE REQUIREMENTS
            </div>
        </div>
        
        <div class="signature-section">
            <div class="signature-label">APPROVED CLEARANCE</div>
            <div class="signatory">University Clearance Office</div>
            <div class="signature-line"></div>
            <div class="signatory">Registrar / Authorized Signatory</div>
        </div>
        <!-- <img src="assets/seal.png" alt="University Seal" class="seal" /> -->
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 28px;">
        <button onclick="window.print()" style="padding: 10px 28px; font-size: 17px; background: #1a237e; color: #fff; border: none; border-radius: 6px; cursor: pointer;">Print Certificate</button>
        <!-- <p style="color: #888; margin-top: 10px;">Use your browser's print function to save as PDF</p> -->
    </div>
    
    <script>
        // Auto-print and close (optional)
        window.onload = function() {
            // Uncomment to automatically open print dialog
            // window.print();
            window.onafterprint = function() {
                // window.close();
            };
        };
    </script>
</body>
</html>