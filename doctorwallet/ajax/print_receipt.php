<?php
require_once '../config.php';
requireLogin();

$receipt_id = (int)($_GET['id'] ?? 0);

if (!$receipt_id) {
    die('Invalid receipt ID');
}

// Function to round to nearest 50
function roundToNearest50($amount) {
    return round($amount / 50) * 50;
}

// Get receipt details (updated query to include new fields)
$stmt = $pdo->prepare("
    SELECT e.*, d.doctor_name, d.slmc_no, d.phone_number as doctor_phone,
           CASE 
               WHEN e.patient_type = 'adult' THEN a.name
               ELSE k.name
           END as patient_name,
           CASE 
               WHEN e.patient_type = 'adult' THEN a.phone_number
               ELSE ap.phone_number
           END as patient_phone,
           CASE 
               WHEN e.patient_type = 'adult' THEN a.nic_number
               ELSE NULL
           END as patient_nic,
           CASE 
               WHEN e.patient_type = 'kid' THEN ap.name
               ELSE NULL
           END as parent_name
    FROM e_receipts e
    JOIN doctors d ON e.doctor_id = d.id
    LEFT JOIN adults a ON e.patient_type = 'adult' AND e.patient_id = a.id
    LEFT JOIN kids k ON e.patient_type = 'kid' AND e.patient_id = k.id
    LEFT JOIN adults ap ON e.patient_type = 'kid' AND k.parent_id = ap.id
    WHERE e.id = ?
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die('Receipt not found');
}

// Get doctor images
$doctor_images = [];
try {
    $stmt = $pdo->prepare("
        SELECT image_type, file_path 
        FROM doctor_images 
        WHERE doctor_id = ? AND is_active = 1
    ");
    $stmt->execute([$receipt['doctor_id']]);
    $doctor_images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    // Table might not exist, continue without images
    $doctor_images = [];
}

// Get receipt items with proper dosage handling
$stmt = $pdo->prepare("
    SELECT ri.*, m.drug_name,
           CASE 
               WHEN ri.dosage = 'M' THEN 'M'
               WHEN ri.dosage = 'N' THEN 'N'
               WHEN ri.dosage = 'Bd' THEN 'Bd'
               WHEN ri.dosage = 'Tds' THEN 'Tds'
               WHEN ri.dosage = 'Qds' THEN 'Qds'
               WHEN ri.dosage = 'SOS' THEN 'SOS'
               WHEN ri.dosage = 'EOD' THEN 'EOD'
               WHEN ri.dosage = 'STAT' THEN 'STAT'
               WHEN ri.dosage = 'VESP' THEN 'VESP'
               WHEN ri.dosage = 'NOON' THEN 'NOON'
               WHEN ri.dosage = '3H' THEN '3H'
               WHEN ri.dosage = '4H' THEN '4H'
               WHEN ri.dosage = '6H' THEN '6H'
               WHEN ri.dosage = '8H' THEN '8H'
               WHEN ri.dosage = 'WEEKLY' THEN 'WEEKLY'
               WHEN ri.dosage = '5X' THEN '5X'
               ELSE COALESCE(ri.dosage, 'As directed')
           END as dosage_description
    FROM receipt_items ri 
    JOIN medicines m ON ri.medicine_id = m.id 
    WHERE ri.receipt_id = ?
    ORDER BY ri.id
");
$stmt->execute([$receipt_id]);
$items = $stmt->fetchAll();

// Calculate medicine total
$medicine_total = 0;
foreach ($items as $item) {
    $medicine_total += $item['amount'];
}

// Get consultation fee (default to 0 if not set)
$consultation_fee = $receipt['consultation_fee'] ?? 0;

// Calculate what the total would be before rounding
$subtotal = $medicine_total + $consultation_fee;

// Check if this is a mobile app request
$is_mobile_app = isset($_GET['mobile_app']) || 
                 strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'YourAppName') !== false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon (modern browsers) -->
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <!-- High-res favicon -->
    <link rel="icon" type="image/png" sizes="192x192" href="../icon.png">
    <!-- Apple touch icon (iOS home screen) -->
    <link rel="apple-touch-icon" sizes="180x180" href="../icon.png">
    <!-- Safari pinned tab (monochrome SVG) -->
    <link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <title>Receipt #<?php echo $receipt['id']; ?> - Print</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
            line-height: 1.3;
            font-size: 12px;
        }
        
        /* Mobile app specific styles */
        .mobile-controls {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: none;
            gap: 10px;
            z-index: 1000;
        }
        
        .mobile-controls.show {
            display: flex;
        }
        
        .mobile-btn {
            padding: 12px 20px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            min-width: 100px;
        }
        
        .print-btn {
            background: #007bff;
            color: white;
        }
        
        .share-btn {
            background: #28a745;
            color: white;
        }
        
        .download-btn {
            background: #17a2b8;
            color: white;
        }
        
        .close-btn {
            background: #6c757d;
            color: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
            position: relative;
            min-height: 100px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .header p {
            margin: 2px 0;
            color: #666;
            font-size: 11px;
        }
        
        /* Doctor Logo - A5 optimized */
        .doctor-logo {
            position: absolute;
            top: 0px;
            left: 5px;
            width: 80px;
            height: 80px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .doctor-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .doctor-logo-placeholder {
            font-size: 8px;
            color: #999;
            text-align: center;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        /* A5 Print optimizations */
        @media print {
            body { 
                padding: 8mm;
                font-size: 10px;
                line-height: 1.2;
                margin: 0;
            }
            
            .header {
                min-height: 60px;
                padding-bottom: 8px;
                margin-bottom: 10px;
            }
            
            .header h1 {
                font-size: 14px;
                margin-bottom: 3px;
            }
            
            .header p {
                font-size: 9px;
                margin: 1px 0;
            }
            
            /* Doctor Logo - A5 print */
            .doctor-logo {
                width: 60px;
                height: 60px;
                top: -5px;
                left: 0px;
            }
            
            .info-grid {
                gap: 8px;
                margin-bottom: 10px;
            }
            
            .info-section h3 {
                font-size: 11px;
                margin-bottom: 5px;
            }
            
            .info-section p {
                font-size: 9px;
                margin: 1px 0;
            }
            
            table {
                margin-bottom: 10px;
                font-size: 9px;
            }
            
            th, td {
                padding: 4px 6px;
            }
            
            .symptoms, .outside-prescription {
                padding: 8px;
                margin-bottom: 10px;
                font-size: 9px;
            }
            
            .footer {
                margin-top: 10px;
                padding-top: 10px;
                min-height: 80px;
                page-break-inside: avoid;
            }
            
            /* Signature section - A5 optimized */
            .signature-section {
                bottom: 25px;
                left: 5px;
                width: 140px;
            }
            
            /* E-signature - A5 print with enhanced watermark */
            .e-signature {
                width: 100px;
                height: 40px;
                margin: 5px auto;
            }
            
            .e-signature::before {
                font-size: 5px;
                color: rgba(0, 0, 0, 0.2);
                background: rgba(255, 255, 255, 0.8);
            }
            
            .doctor-name-line {
                font-size: 9px;
                margin-top: 3px;
                padding-top: 3px;
            }
            
            /* Doctor seal - A5 print with enhanced watermark */
            .doctor-seal {
                bottom: 0px;
                right: 5px;
                width: 120px;
                height: 120px;
                border-radius: 10%;
            }
            
            .doctor-seal::before {
                font-size: 6px;
                color: rgba(0, 0, 0, 0.18);
                background: rgba(255, 255, 255, 0.75);
                line-height: 1.1;
            }
            
            .doctor-seal-placeholder,
            .e-signature-placeholder,
            .doctor-logo-placeholder {
                font-size: 6px;
            }
            
            .mobile-controls { 
                display: none !important; 
            }
        }
        
        /* Responsive adjustments for mobile */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .doctor-logo {
                position: relative;
                width: 70px;
                height: 70px;
                margin: 0 auto 10px;
            }
            
            body {
                padding: 10px;
            }
        }
        
        .info-section h3 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        .info-section p {
            margin: 2px 0;
            font-size: 11px;
        }
        .symptoms, .outside-prescription {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 15px;
        }
        .symptoms h3, .outside-prescription h3 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #333;
        }
        .outside-prescription {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .subtotal-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #e8f5e8;
            color: #2d5a2d;
        }
        .rounding-note {
            font-size: 10px;
            color: #666;
            font-style: italic;
        }
        .instructions {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 15px;
            font-size: 11px;
        }
        .instructions h3 {
            margin: 0 0 8px 0;
            color: #1e40af;
            font-size: 12px;
        }
        .instructions ul {
            margin: 0;
            padding-left: 15px;
        }
        .instructions li {
            margin-bottom: 3px;
        }
        .footer {
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 12px;
            margin-top: 15px;
            color: #666;
            font-size: 10px;
            position: relative;
            min-height: 100px;
        }
        
        /* Signature section - A5 optimized */
        .signature-section {
            position: absolute;
            bottom: 30px;
            left: 15px;
            text-align: center;
            width: 160px;
        }
        
        /* E-signature - A5 optimized with watermark protection */
        .e-signature {
            width: 120px;
            height: 45px;
            margin: 8px auto;
            background: rgba(249, 249, 249, 0);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .e-signature img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .e-signature-placeholder {
            font-size: 8px;
            color: #999;
        }
        
        /* E-signature watermark */
        .e-signature::before {
            content: 'Receipt #<?php echo $receipt['id']; ?>\A<?php echo date('d/m/Y', strtotime($receipt['created_at'])); ?>';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            color: rgba(0, 0, 0, 0.15);
            font-size: 6px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: pre;
            text-align: center;
            line-height: 1.1;
            pointer-events: none;
            z-index: 10;
        }
        
        /* Doctor name line - A5 optimized */
        .doctor-name-line {
            border-top: 1px solid #333;
            margin-top: 5px;
            padding-top: 3px;
            font-weight: bold;
            font-size: 10px;
        }
        
        /* Doctor seal - A5 optimized with watermark protection */
        .doctor-seal {
            position: absolute;
            bottom: 5px;
            right: 15px;
            width: 140px;
            height: 140px;
            border-radius: 10%;
            background: rgba(249, 249, 249, 0);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .doctor-seal img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .doctor-seal-placeholder {
            font-size: 7px;
            color: #999;
            text-align: center;
        }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .mobile-controls { display: none !important; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <!-- Doctor Logo -->
        <div class="doctor-logo">
            <?php if (!empty($doctor_images['logo'])): ?>
                <img src="<?php echo htmlspecialchars($doctor_images['logo']); ?>" alt="Doctor Logo">
            <?php else: ?>
                <div class="doctor-logo-placeholder">Doctor<br>Logo</div>
            <?php endif; ?>
        </div>
        
        <h1>Medical Receipt</h1>
        <p>Dr. <?php echo htmlspecialchars($receipt['doctor_name']); ?></p>
        <p>MBBS, Peradeniya, Sri Lanka</p>
        <p>SLMC NO. <?php echo !empty($receipt['slmc_no']) ? htmlspecialchars($receipt['slmc_no']) : 'N/A'; ?>, Phone number <?php echo !empty($receipt['doctor_phone']) ? htmlspecialchars($receipt['doctor_phone']) : 'N/A'; ?></p>
        <p class="text-right">Receipt #<?php echo $receipt['id']; ?></p>
    </div>

    <!-- Patient and Visit Information -->
    <div class="info-grid">
        <div class="info-section">
            <h3>Patient Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($receipt['patient_name']); ?></p>
            <?php if ($receipt['parent_name']): ?>
                <p><strong>Parent:</strong> <?php echo htmlspecialchars($receipt['parent_name']); ?></p>
            <?php endif; ?>
            <?php if ($receipt['patient_phone']): ?>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($receipt['patient_phone']); ?></p>
            <?php endif; ?>
            <?php if ($receipt['patient_nic']): ?>
                <p><strong>NIC:</strong> <?php echo htmlspecialchars($receipt['patient_nic']); ?></p>
            <?php endif; ?>
            <p><strong>Patient Type:</strong> <?php echo ucfirst($receipt['patient_type']); ?></p>
        </div>
        
        <div class="info-section">
            <h3>Visit Information</h3>
            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($receipt['created_at'])); ?></p>
        </div>
    </div>

    <div>
        <table>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>For Consultation, care & medicine sum total: <?php echo formatCurrency($receipt['total_amount']); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Outside Prescription (OC) --
    <?php if (!empty($receipt['outside_prescription'])): ?>
    <div class="outside-prescription">
        <h3>Outside Prescription (OC)</h3>
        <p><?php echo nl2br(htmlspecialchars($receipt['outside_prescription'])); ?></p>
    </div>
    <?php endif; ?>-->

    <!-- Mobile Controls -->
    <div class="mobile-controls" id="mobileControls">
        <button class="mobile-btn print-btn" onclick="handlePrint()">Print</button>
        <button class="mobile-btn share-btn" onclick="handleShare()">Share</button>
        <button class="mobile-btn download-btn" onclick="handleDownload()">Download</button>
        <button class="mobile-btn close-btn" onclick="handleClose()">Close</button>
    </div>

    <!-- Footer -->
    <div class="footer">
        <!-- Signature Section -->
        <div class="signature-section">
            <!-- E-signature -->
            <div class="e-signature">
                <?php if (!empty($doctor_images['signature'])): ?>
                    <img src="<?php echo htmlspecialchars($doctor_images['signature']); ?>" alt="Doctor Signature">
                <?php else: ?>
                    <div class="e-signature-placeholder">E-Signature</div>
                <?php endif; ?>
            </div>
            
            <!-- Doctor Name Line -->
            <div class="doctor-name-line">
                Dr. <?php echo htmlspecialchars($receipt['doctor_name']); ?>
            </div>
        </div>
        <!-- Doctor Seal -->
        <div class="doctor-seal">
            <?php if (!empty($doctor_images['seal'])): ?>
                <img src="<?php echo htmlspecialchars($doctor_images['seal']); ?>" alt="Doctor Seal">
            <?php else: ?>
                <div class="doctor-seal-placeholder">Doctor<br>Seal</div>
            <?php endif; ?>
        </div>
        <div>
            <p>Thank you for choosing our medical services</p>
            <p>For any queries, please contact us</p>
            <p>Generated on <?php echo date('F d, Y'); ?></p>
        </div>
    </div>

    <script>
        // Detect mobile app environment
        function isMobileApp() {
            return <?php echo $is_mobile_app ? 'true' : 'false'; ?> ||
                   /Android.*wv|iPhone.*Mobile.*Safari/i.test(navigator.userAgent) ||
                   window.navigator.standalone === true ||
                   document.referrer.includes('android-app://') ||
                   document.referrer.includes('ios-app://');
        }
        
        // Handle print functionality
        function handlePrint() {
            // Try multiple print methods for mobile compatibility
            if (window.print) {
                // Hide mobile controls before printing
                document.getElementById('mobileControls').style.display = 'none';
                
                setTimeout(() => {
                    window.print();
                    // Show controls again after print
                    setTimeout(() => {
                        document.getElementById('mobileControls').style.display = 'flex';
                    }, 1000);
                }, 100);
            } else {
                // Fallback: Open print-friendly version
                window.open(window.location.href + '&print=1', '_blank');
            }
        }
        
        // Handle sharing
        function handleShare() {
            if (navigator.share) {
                navigator.share({
                    title: 'Medical Receipt #<?php echo $receipt['id']; ?>',
                    text: 'Medical Receipt for <?php echo htmlspecialchars($receipt['patient_name']); ?>',
                    url: window.location.href
                });
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Receipt link copied to clipboard');
                });
            }
        }
        
        // Handle download
        function handleDownload() {
            // Create PDF-like view by hiding controls and opening in new window
            const printWindow = window.open('', '_blank');
            const content = document.documentElement.outerHTML;
            const modifiedContent = content.replace(
                '<div class="mobile-controls" id="mobileControls">',
                '<div class="mobile-controls" id="mobileControls" style="display: none;">'
            );
            printWindow.document.write(modifiedContent);
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }
        
        // Handle close
        function handleClose() {
            // Try to close window/go back
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        }
        
        // Initialize based on environment
        window.onload = function() {
            if (isMobileApp()) {
                // Show mobile controls instead of auto-print
                document.getElementById('mobileControls').classList.add('show');
                
                // Optional: Show a message
                console.log('Mobile app detected - showing controls');
            } else {
                // Browser environment - auto print
                window.print();
            }
        };
        
        // Handle Android back button
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                handleClose();
            }
        });
        
        // Communicate with mobile app (if your app supports it)
        function notifyApp(action, data) {
            try {
                // Android WebView interface
                if (window.Android && window.Android.receiptAction) {
                    window.Android.receiptAction(action, JSON.stringify(data));
                }
                
                // iOS WKWebView interface  
                if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.receiptHandler) {
                    window.webkit.messageHandlers.receiptHandler.postMessage({
                        action: action,
                        data: data
                    });
                }
                
                // React Native WebView
                if (window.ReactNativeWebView) {
                    window.ReactNativeWebView.postMessage(JSON.stringify({
                        action: action,
                        data: data
                    }));
                }
            } catch (e) {
                console.log('App communication not available');
            }
        }
        
        // Enhanced functions with app communication
        const originalHandlePrint = handlePrint;
        const originalHandleShare = handleShare;
        const originalHandleDownload = handleDownload;
        
        handlePrint = function() {
            notifyApp('print', {
                receiptId: <?php echo $receipt['id']; ?>,
                patientName: '<?php echo addslashes($receipt['patient_name']); ?>'
            });
            originalHandlePrint();
        };
        
        handleShare = function() {
            notifyApp('share', {
                receiptId: <?php echo $receipt['id']; ?>,
                url: window.location.href
            });
            originalHandleShare();
        };
        
        handleDownload = function() {
            notifyApp('download', {
                receiptId: <?php echo $receipt['id']; ?>
            });
            originalHandleDownload();
        };
    </script>
</body>
</html>