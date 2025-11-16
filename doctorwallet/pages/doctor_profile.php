<?php
session_start();
require_once '../config.php';

// Check if user has profile access
if (!isset($_SESSION['profile_logged_in']) || $_SESSION['profile_logged_in'] !== true) {
    header('Location: profile_login.php');
    exit;
}

// Check if we have doctor_id from profile login
if (!isset($_SESSION['doctor_id'])) {
    session_destroy();
    header('Location: profile_login.php');
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

// Handle logout - clear only profile session
if (isset($_GET['logout'])) {
    // Only destroy profile-related session variables
    unset($_SESSION['profile_logged_in']);
    unset($_SESSION['doctor_id']);
    header('Location: profile_login.php');
    exit;
}

// Handle doctor details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doctor'])) {
    $doctor_name = trim($_POST['doctor_name']);
    $phone_number = trim($_POST['phone_number']);
    $slmc_no = trim($_POST['slmc_no']);
    
    $update_stmt = $pdo->prepare("UPDATE doctors SET doctor_name = ?, phone_number = ?, slmc_no = ? WHERE id = ?");
    if ($update_stmt->execute([$doctor_name, $phone_number, $slmc_no, $doctor_id])) {
        $success_message = "Doctor details updated successfully!";
    } else {
        $error_message = "Failed to update doctor details.";
    }
}

// Get doctor information
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    session_destroy();
    header('Location: profile_login.php');
    exit;
}

// Get comprehensive doctor-specific statistics
$stats = [];

// Doctor's total patients (adults and kids treated by this doctor)
$stmt = $pdo->prepare("SELECT 
    (SELECT COUNT(*) FROM adults WHERE doctor_id = ?) + 
    (SELECT COUNT(*) FROM kids WHERE doctor_id = ?) as total");
$stmt->execute([$doctor_id, $doctor_id]);
$stats['total_patients'] = $stmt->fetch()['total'];

// Doctor's adult patients
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM adults WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$stats['adult_patients'] = $stmt->fetch()['count'];

// Doctor's kid patients
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM kids WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$stats['kid_patients'] = $stmt->fetch()['count'];

// Doctor's medicines
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$stats['total_medicines'] = $stmt->fetch()['count'];

// Doctor's medicines in stock
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE doctor_id = ? AND current_stock > 0");
$stmt->execute([$doctor_id]);
$stats['medicines_in_stock'] = $stmt->fetch()['count'];

// Doctor's low stock medicines (FIXED: Only count medicines that are low but not out of stock)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE doctor_id = ? AND current_stock <= notify_threshold AND current_stock > 0");
$stmt->execute([$doctor_id]);
$stats['low_stock_medicines'] = $stmt->fetch()['count'];

// Doctor's out of stock medicines
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE doctor_id = ? AND current_stock = 0");
$stmt->execute([$doctor_id]);
$stats['out_of_stock'] = $stmt->fetch()['count'];

// Doctor's receipts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM e_receipts WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$stats['total_receipts'] = $stmt->fetch()['count'];

// Doctor's today receipts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM e_receipts WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$stats['today_receipts'] = $stmt->fetch()['count'];

// Doctor's weekly receipts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM e_receipts WHERE doctor_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE())");
$stmt->execute([$doctor_id]);
$stats['week_receipts'] = $stmt->fetch()['count'];

// Doctor's monthly receipts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM e_receipts WHERE doctor_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->execute([$doctor_id]);
$stats['month_receipts'] = $stmt->fetch()['count'];

// Doctor's total income
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM e_receipts WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$stats['total_income'] = $stmt->fetch()['total'];

// Doctor's monthly income
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM e_receipts WHERE doctor_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->execute([$doctor_id]);
$stats['monthly_income'] = $stmt->fetch()['total'];

// Doctor's today income
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM e_receipts WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$stats['today_income'] = $stmt->fetch()['total'];

// Get doctor's recent patients (last 15) - FIXED: Better query structure
$stmt = $pdo->prepare("
    SELECT * FROM (
        (SELECT a.name, a.phone_number as phone, 'Adult' as type, er.created_at 
         FROM adults a 
         JOIN e_receipts er ON er.patient_id = a.id AND er.patient_type = 'adult' 
         WHERE a.doctor_id = ? AND er.doctor_id = ?
         ORDER BY er.created_at DESC LIMIT 8)
        UNION ALL
        (SELECT k.name, a.phone_number as phone, 'Kid' as type, er.created_at 
         FROM kids k 
         JOIN adults a ON k.parent_id = a.id 
         JOIN e_receipts er ON er.patient_id = k.id AND er.patient_type = 'kid'
         WHERE k.doctor_id = ? AND er.doctor_id = ?
         ORDER BY er.created_at DESC LIMIT 7)
    ) AS combined_patients
    ORDER BY created_at DESC LIMIT 15
");
$stmt->execute([$doctor_id, $doctor_id, $doctor_id, $doctor_id]);
$recent_patients = $stmt->fetchAll();

// Get doctor's top prescribed medicines
$stmt = $pdo->prepare("
    SELECT m.drug_name as medicine_name, SUM(ri.quantity_issued) as prescribed_count,
           ROUND(AVG(ri.amount), 2) as avg_cost, COUNT(ri.id) as prescription_count
    FROM receipt_items ri 
    JOIN medicines m ON ri.medicine_id = m.id 
    JOIN e_receipts er ON ri.receipt_id = er.id
    WHERE m.doctor_id = ? AND er.doctor_id = ?
    GROUP BY m.id, m.drug_name 
    ORDER BY prescribed_count DESC 
    LIMIT 10
");
$stmt->execute([$doctor_id, $doctor_id]);
$top_medicines = $stmt->fetchAll();

// Get doctor's monthly income trend (last 12 months)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month_year,
        MONTHNAME(created_at) as month, 
        COUNT(*) as receipt_count,
        SUM(total_amount) as income,
        AVG(total_amount) as avg_receipt_amount
    FROM e_receipts 
    WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), MONTHNAME(created_at)
    ORDER BY created_at ASC
");
$stmt->execute([$doctor_id]);
$monthly_trend = $stmt->fetchAll();

// Get doctor's medicine categories distribution - FIXED: Better categorization
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN LOWER(drug_name) LIKE '%paracetamol%' OR LOWER(drug_name) LIKE '%aspirin%' OR LOWER(drug_name) LIKE '%ibuprofen%' OR LOWER(drug_name) LIKE '%pain%' THEN 'Pain Relief'
            WHEN LOWER(drug_name) LIKE '%antibiotic%' OR LOWER(drug_name) LIKE '%amoxicillin%' OR LOWER(drug_name) LIKE '%penicillin%' OR LOWER(drug_name) LIKE '%cephalex%' THEN 'Antibiotics'
            WHEN LOWER(drug_name) LIKE '%vitamin%' OR LOWER(drug_name) LIKE '%supplement%' OR LOWER(drug_name) LIKE '%calcium%' OR LOWER(drug_name) LIKE '%iron%' THEN 'Vitamins & Supplements'
            WHEN LOWER(drug_name) LIKE '%cough%' OR LOWER(drug_name) LIKE '%cold%' OR LOWER(drug_name) LIKE '%flu%' THEN 'Cold & Cough'
            WHEN LOWER(drug_name) LIKE '%diabetes%' OR LOWER(drug_name) LIKE '%insulin%' OR LOWER(drug_name) LIKE '%metformin%' THEN 'Diabetes Care'
            WHEN LOWER(drug_name) LIKE '%pressure%' OR LOWER(drug_name) LIKE '%hypertension%' OR LOWER(drug_name) LIKE '%amlodipine%' THEN 'Blood Pressure'
            ELSE 'Other Medicines'
        END as category, 
        COUNT(*) as count,
        SUM(current_stock) as total_stock,
        ROUND(AVG(price_per_tablet), 2) as avg_price
    FROM medicines 
    WHERE doctor_id = ?
    GROUP BY category 
    ORDER BY count DESC 
    LIMIT 8
");
$stmt->execute([$doctor_id]);
$medicine_categories = $stmt->fetchAll();

// Get patient demographics - FIXED: Handle NULL ages better
$stmt = $pdo->prepare("
    SELECT 
        'Adults' as category,
        COUNT(*) as count,
        ROUND(AVG(CASE 
            WHEN age IS NOT NULL AND age > 0 THEN age 
            WHEN birthday IS NOT NULL THEN YEAR(CURDATE()) - YEAR(birthday)
            ELSE NULL 
        END), 1) as avg_age
    FROM adults WHERE doctor_id = ?
    UNION ALL
    SELECT 
        'Kids' as category,
        COUNT(*) as count,
        ROUND(AVG(CASE 
            WHEN age IS NOT NULL AND age > 0 THEN age 
            WHEN birthday IS NOT NULL THEN YEAR(CURDATE()) - YEAR(birthday)
            ELSE NULL 
        END), 1) as avg_age
    FROM kids WHERE doctor_id = ?
");
$stmt->execute([$doctor_id, $doctor_id]);
$patient_demographics = $stmt->fetchAll();

// Get daily prescription pattern (last 30 days)
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as prescriptions,
        SUM(total_amount) as daily_income,
        COUNT(DISTINCT CONCAT(patient_type, '-', patient_id)) as unique_patients
    FROM e_receipts 
    WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
");
$stmt->execute([$doctor_id]);
$daily_pattern = $stmt->fetchAll();

// FIXED: Get medicine stock alerts with proper logic and priority ordering
$stmt = $pdo->prepare("
    SELECT 
        drug_name, 
        current_stock, 
        notify_threshold, 
        DATEDIFF(expiry_date, CURDATE()) as days_to_expiry,
        price_per_tablet * current_stock as stock_value,
        expiry_date,
        CASE 
            WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 'EXPIRED'
            WHEN current_stock = 0 THEN 'OUT_OF_STOCK'
            WHEN current_stock <= notify_threshold THEN 'LOW_STOCK'
            WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'EXPIRING_CRITICAL'
            WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 'EXPIRING_SOON'
            ELSE 'OK'
        END as alert_type
    FROM medicines 
    WHERE doctor_id = ? 
    AND (
        current_stock = 0 OR 
        current_stock <= notify_threshold OR 
        (expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY))
    )
    ORDER BY 
        CASE 
            WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1
            WHEN current_stock = 0 THEN 2
            WHEN current_stock <= notify_threshold THEN 3
            WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 4
            WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 5
            ELSE 6
        END,
        expiry_date ASC,
        current_stock ASC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$stock_alerts = $stmt->fetchAll();

// Calculate growth metrics - FIXED: Handle division by zero
$growth_metrics = [];

// Patient growth (this month vs last month)
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM adults WHERE doctor_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) +
        (SELECT COUNT(*) FROM kids WHERE doctor_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) as this_month,
        (SELECT COUNT(*) FROM adults WHERE doctor_id = ? AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) +
        (SELECT COUNT(*) FROM kids WHERE doctor_id = ? AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) as last_month
");
$stmt->execute([$doctor_id, $doctor_id, $doctor_id, $doctor_id]);
$patient_growth = $stmt->fetch();
$growth_metrics['patient_growth'] = $patient_growth['last_month'] > 0 ? 
    round((($patient_growth['this_month'] - $patient_growth['last_month']) / $patient_growth['last_month']) * 100, 1) : 
    ($patient_growth['this_month'] > 0 ? 100 : 0);

// Income growth - FIXED: Handle division by zero
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(total_amount), 0) FROM e_receipts WHERE doctor_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) as this_month,
        (SELECT COALESCE(SUM(total_amount), 0) FROM e_receipts WHERE doctor_id = ? AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) as last_month
");
$stmt->execute([$doctor_id, $doctor_id]);
$income_growth = $stmt->fetch();
$growth_metrics['income_growth'] = $income_growth['last_month'] > 0 ? 
    round((($income_growth['this_month'] - $income_growth['last_month']) / $income_growth['last_month']) * 100, 1) : 
    ($income_growth['this_month'] > 0 ? 100 : 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?> - Practice Dashboard</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">

<!-- Microsoft tile icon -->
<meta name="msapplication-TileImage" content="../icon.png">
<meta name="msapplication-TileColor" content="#0F2E44">

<!-- Theme color for mobile browsers -->
<meta name="theme-color" content="#0F2E44">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .stat-card { background: linear-gradient(135deg, var(--from-color), var(--to-color)); }
        .animate-number { transition: all 0.5s ease; }
        @media print { .no-print { display: none !important; } }

/* Modern Premium Smooth Transitions */
body {
    margin: 0;
    padding: 0;
    position: relative;
    overflow-x: hidden;
}

/* Gradient overlay for smooth transition */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, rgba(99, 102, 241, 0.03) 0%, rgba(0, 0, 0, 0.4) 100%);
    opacity: 0;
    pointer-events: none;
    z-index: 9998;
    transition: opacity 0.6s cubic-bezier(0.65, 0, 0.35, 1);
    backdrop-filter: blur(0px);
}

/* Animated gradient overlay */
body::after {
    content: '';
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%);
    opacity: 0;
    pointer-events: none;
    z-index: 9999;
    transform: scale(0);
    transition: all 0.7s cubic-bezier(0.65, 0, 0.35, 1);
}

/* Exit animation */
body.page-transitioning-out {
    animation: smoothOut 0.7s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    pointer-events: none;
}

body.page-transitioning-out::before {
    opacity: 1;
    backdrop-filter: blur(20px);
}

body.page-transitioning-out::after {
    opacity: 1;
    transform: scale(2);
}

/* Enter animation */
body.page-transitioning-in {
    animation: smoothIn 0.8s cubic-bezier(0.65, 0, 0.35, 1) forwards;
}

body.page-transitioning-in::after {
    animation: pulseIn 0.8s cubic-bezier(0.65, 0, 0.35, 1);
}

/* Smooth exit with elastic feel */
@keyframes smoothOut {
    0% {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0deg);
        filter: blur(0px) brightness(1);
    }
    100% {
        opacity: 0;
        transform: translateY(-40px) scale(0.95) rotateX(2deg);
        filter: blur(10px) brightness(0.8);
    }
}

/* Smooth enter with bounce */
@keyframes smoothIn {
    0% {
        opacity: 0;
        transform: translateY(50px) scale(0.94) rotateX(-2deg);
        filter: blur(15px) brightness(0.7);
    }
    60% {
        opacity: 0.8;
        transform: translateY(-5px) scale(1.01) rotateX(0deg);
        filter: blur(2px) brightness(0.95);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0deg);
        filter: blur(0px) brightness(1);
    }
}

/* Pulse effect */
@keyframes pulseIn {
    0%, 100% {
        opacity: 0;
        transform: scale(0);
    }
    50% {
        opacity: 1;
        transform: scale(1.5);
    }
}

/* Prevent scroll during transition */
body.page-transitioning-out,
body.page-transitioning-in {
    overflow: hidden;
    height: 100vh;
}

/* Stagger content animation */
body.page-transitioning-in * {
    animation: contentSlideIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
}

body.page-transitioning-in *:nth-child(1) { animation-delay: 0.1s; }
body.page-transitioning-in *:nth-child(2) { animation-delay: 0.15s; }
body.page-transitioning-in *:nth-child(3) { animation-delay: 0.2s; }
body.page-transitioning-in *:nth-child(4) { animation-delay: 0.25s; }

@keyframes contentSlideIn {
    0% {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Smooth links hover effect */
a {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

a:hover {
    transform: translateY(-2px);
}
    </style>
</head>
<body class="bg-gray-50 min-h-screen" style="visibility: hidden;">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-lg no-print">
        <div class="container mx-auto px-6 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <div class="bg-white bg-opacity-20 p-4 rounded-full backdrop-blur-sm">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></h1>
                        <p class="text-blue-100 mt-1">Practice Analytics & Management Dashboard</p>
                        <p class="text-blue-100 text-sm">SLMC: <?php echo htmlspecialchars($doctor['slmc_no'] ?? 'Not Set'); ?> | ID: <?php echo $doctor['id']; ?></p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button onclick="toggleEditMode()" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg backdrop-blur-sm transition duration-200 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Profile
                    </button>
                    <button onclick="window.print()" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg backdrop-blur-sm transition duration-200 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print Report
                    </button>
                    <a href="?logout=1" class="bg-red-500 bg-opacity-80 hover:bg-opacity-100 text-white px-4 py-2 rounded-lg transition duration-200 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <div class="flex">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?php echo $success_message; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <?php echo $error_message; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Profile Modal -->
        <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 no-print">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Edit Doctor Details</h3>
                        <button onclick="toggleEditMode()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Doctor Name</label>
                            <input type="text" name="doctor_name" value="<?php echo htmlspecialchars($doctor['doctor_name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone_number" value="<?php echo htmlspecialchars($doctor['phone_number']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">SLMC Number</label>
                            <input type="text" name="slmc_no" value="<?php echo htmlspecialchars($doctor['slmc_no'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" name="update_doctor" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">Update</button>
                            <button type="button" onclick="toggleEditMode()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 transition duration-200">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Key Performance Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="kpiGrid">
            <div class="stat-card text-white rounded-xl p-6 card-hover" style="--from-color: #4F46E5; --to-color: #7C3AED;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100">Total Patients</p>
                        <p class="text-3xl font-bold animate-number" id="totalPatients"><?php echo $stats['total_patients']; ?></p>
                        <p class="text-sm text-blue-100 mt-2">
                            <span class="inline-flex items-center">
                                <?php if ($growth_metrics['patient_growth'] >= 0): ?>
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    +<?php echo $growth_metrics['patient_growth']; ?>%
                                <?php else: ?>
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 112 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <?php echo $growth_metrics['patient_growth']; ?>%
                                <?php endif; ?>
                                vs last month
                            </span>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex space-x-4 text-sm">
                    <span>Adults: <?php echo $stats['adult_patients']; ?></span>
                    <span>Kids: <?php echo $stats['kid_patients']; ?></span>
                </div>
            </div>

            <div class="stat-card text-white rounded-xl p-6 card-hover" style="--from-color: #16A34A; --to-color: #22C55E;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">Monthly Revenue</p>
                        <p class="text-3xl font-bold animate-number">Rs.<?php echo number_format($stats['monthly_income']); ?></p>
                        <p class="text-sm text-green-100 mt-2">
                            <span class="inline-flex items-center">
                                <?php if ($growth_metrics['income_growth'] >= 0): ?>
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    +<?php echo $growth_metrics['income_growth']; ?>%
                                <?php else: ?>
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 112 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <?php echo $growth_metrics['income_growth']; ?>%
                                <?php endif; ?>
                                vs last month
                            </span>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-sm">
                    <span>Today: Rs.<?php echo number_format($stats['today_income']); ?></span>
                </div>
            </div>

            <div class="stat-card text-white rounded-xl p-6 card-hover" style="--from-color: #DC2626; --to-color: #EF4444;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100">Prescriptions</p>
                        <p class="text-3xl font-bold animate-number"><?php echo $stats['total_receipts']; ?></p>
                        <p class="text-sm text-red-100 mt-2">
                            <span>Today: <?php echo $stats['today_receipts']; ?> | Week: <?php echo $stats['week_receipts']; ?></span>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a1 1 0 102 0V3h4v1a1 1 0 102 0V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm8 8a1 1 0 100-2H8a1 1 0 100 2h4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-sm">
                    <span>This Month: <?php echo $stats['month_receipts']; ?></span>
                </div>
            </div>

            <div class="stat-card text-white rounded-xl p-6 card-hover" style="--from-color: #7C2D12; --to-color: #EA580C;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100">Medicines</p>
                        <p class="text-3xl font-bold animate-number"><?php echo $stats['total_medicines']; ?></p>
                        <p class="text-sm text-orange-100 mt-2">
                            <?php if ($stats['low_stock_medicines'] > 0 || $stats['out_of_stock'] > 0): ?>
                                <span class="text-yellow-200">⚠️ <?php echo ($stats['low_stock_medicines'] + $stats['out_of_stock']); ?> need attention</span>
                            <?php else: ?>
                                <span class="text-green-200">✅ Stock levels good</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-sm">
                    <span>In Stock: <?php echo $stats['medicines_in_stock']; ?> | Out: <?php echo $stats['out_of_stock']; ?></span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Income Trend Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Revenue Trend (12 Months)</h3>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm text-gray-500">Live Data</span>
                    </div>
                </div>
                <?php if (count($monthly_trend) > 0): ?>
                <div style="height: 300px;">
                    <canvas id="incomeChart"></canvas>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-4 text-center border-t pt-4">
                    <div>
                        <p class="text-sm text-gray-600">Peak Month</p>
                        <p class="font-semibold text-green-600">
                            Rs.<?php 
                                $maxMonth = array_reduce($monthly_trend, function($max, $month) {
                                    return $month['income'] > $max['income'] ? $month : $max;
                                }, ['income' => 0, 'month' => 'N/A']);
                                echo number_format($maxMonth['income']);
                            ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Average</p>
                        <p class="font-semibold text-blue-600">
                            Rs.<?php echo number_format(array_sum(array_column($monthly_trend, 'income')) / count($monthly_trend)); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">This Month</p>
                        <p class="font-semibold text-purple-600">Rs.<?php echo number_format($stats['monthly_income']); ?></p>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p>No revenue data available yet</p>
                    <p class="text-sm">Start creating prescriptions to see analytics</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Daily Activity Pattern -->
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Daily Activity (Last 30 Days)</h3>
                    <select class="text-sm border border-gray-300 rounded px-2 py-1" onchange="updateActivityChart(this.value)">
                        <option value="prescriptions">Prescriptions</option>
                        <option value="income" default>Income</option>
                        <option value="patients">Unique Patients</option>
                    </select>
                </div>
                <?php if (count($daily_pattern) > 0): ?>
                <div style="height: 300px;">
                    <canvas id="dailyChart"></canvas>
                </div>
                <div class="mt-4 grid grid-cols-4 gap-2 text-center border-t pt-4">
                    <div>
                        <p class="text-xs text-gray-600">Avg/Day</p>
                        <p class="font-semibold text-blue-600"><?php echo round(array_sum(array_column($daily_pattern, 'prescriptions')) / count($daily_pattern), 1); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Peak Day</p>
                        <p class="font-semibold text-green-600"><?php echo max(array_column($daily_pattern, 'prescriptions')); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Total Patients</p>
                        <p class="font-semibold text-purple-600"><?php echo array_sum(array_column($daily_pattern, 'unique_patients')); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Active Days</p>
                        <p class="font-semibold text-orange-600"><?php echo count(array_filter($daily_pattern, function($day) { return $day['prescriptions'] > 0; })); ?></p>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p>No daily activity data yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Medicine Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Medicine Categories -->
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Medicine Categories</h3>
                <div class="space-y-4">
                    <?php if (count($medicine_categories) > 0): ?>
                        <?php foreach ($medicine_categories as $index => $category): ?>
                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg border border-indigo-100">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-white font-bold text-sm"><?php echo $index + 1; ?></span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($category['category']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $category['count']; ?> medicines</p>
                                    <p class="text-xs text-gray-500">Avg: Rs.<?php echo $category['avg_price']; ?>/tablet</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-700"><?php echo $category['total_stock']; ?></p>
                                <p class="text-xs text-gray-500">in stock</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <p>No medicine categories available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Prescribed Medicines -->
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Most Prescribed</h3>
                <div class="space-y-4 max-h-80 overflow-y-auto">
                    <?php if (count($top_medicines) > 0): ?>
                        <?php foreach ($top_medicines as $index => $medicine): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-white text-sm font-bold"><?php echo $index + 1; ?></span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($medicine['medicine_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $medicine['prescribed_count']; ?> tablets prescribed</p>
                                    <p class="text-xs text-gray-500"><?php echo $medicine['prescription_count']; ?> prescriptions | Avg: Rs.<?php echo $medicine['avg_cost']; ?></p>
                                </div>
                            </div>
                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-500 h-2 rounded-full" style="width: <?php echo min(100, ($medicine['prescribed_count'] / $top_medicines[0]['prescribed_count']) * 100); ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <p>No prescription data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Patient Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Recent Patients -->
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Recent Patients</h3>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php if (count($recent_patients) > 0): ?>
                        <?php foreach ($recent_patients as $patient): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-4">
                                    <span class="text-white font-bold"><?php echo strtoupper(substr($patient['name'], 0, 2)); ?></span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($patient['name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($patient['phone']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($patient['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded-full font-medium"><?php echo $patient['type']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <p>No recent patient visits</p>
                            <p class="text-sm">Start adding patients to see activity</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Patient Demographics -->
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Patient Demographics</h3>
                
                <!-- Patient Type Distribution -->
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-700 mb-4">Patient Distribution</h4>
                    <div class="space-y-4">
                        <?php foreach ($patient_demographics as $demo): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-4 h-4 rounded-full mr-3 <?php echo $demo['category'] === 'Adults' ? 'bg-blue-500' : 'bg-green-500'; ?>"></div>
                                <span class="font-medium text-gray-700"><?php echo $demo['category']; ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-2xl font-bold text-gray-800"><?php echo $demo['count']; ?></span>
                                <p class="text-sm text-gray-500">Avg age: <?php echo $demo['avg_age'] ?? 'N/A'; ?></p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="<?php echo $demo['category'] === 'Adults' ? 'bg-blue-500' : 'bg-green-500'; ?> h-2 rounded-full" 
                                 style="width: <?php echo $stats['total_patients'] > 0 ? ($demo['count'] / $stats['total_patients']) * 100 : 0; ?>%"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="border-t pt-6">
                    <h4 class="text-lg font-semibold text-gray-700 mb-4">Quick Statistics</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg text-center">
                            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['today_receipts']; ?></p>
                            <p class="text-sm text-blue-600">Today's Visits</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg text-center">
                            <p class="text-2xl font-bold text-green-600">Rs.<?php echo number_format($stats['today_income']); ?></p>
                            <p class="text-sm text-green-600">Today's Income</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg text-center">
                            <p class="text-2xl font-bold text-purple-600"><?php echo $stats['week_receipts']; ?></p>
                            <p class="text-sm text-purple-600">This Week</p>
                        </div>
                        <div class="bg-orange-50 p-4 rounded-lg text-center">
                            <p class="text-2xl font-bold text-orange-600"><?php echo $stats['month_receipts']; ?></p>
                            <p class="text-sm text-orange-600">This Month</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8 card-hover">
            <h3 class="text-2xl font-bold text-gray-800 mb-8 text-center">Practice Performance Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
                    <div class="bg-blue-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-3xl font-bold text-blue-600 mb-2">
                        Rs.<?php echo $stats['total_receipts'] > 0 ? number_format($stats['total_income'] / $stats['total_receipts'], 0) : '0'; ?>
                    </p>
                    <p class="text-sm text-gray-600">Avg Revenue per Visit</p>
                    <p class="text-xs text-gray-500 mt-1">Per prescription</p>
                </div>

                <div class="text-center p-6 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                    <div class="bg-green-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <p class="text-3xl font-bold text-green-600 mb-2">
                        <?php echo $growth_metrics['income_growth'] >= 0 ? '+' : ''; ?><?php echo $growth_metrics['income_growth']; ?>%
                    </p>
                    <p class="text-sm text-gray-600">Monthly Growth</p>
                    <p class="text-xs text-gray-500 mt-1">Revenue trend</p>
                </div>

                <div class="text-center p-6 bg-gradient-to-r from-purple-50 to-violet-50 rounded-xl">
                    <div class="bg-purple-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <p class="text-3xl font-bold text-purple-600 mb-2">
                        <?php echo $stats['total_patients'] > 0 ? round($stats['total_receipts'] / $stats['total_patients'], 1) : '0'; ?>
                    </p>
                    <p class="text-sm text-gray-600">Avg Visits per Patient</p>
                    <p class="text-xs text-gray-500 mt-1">Patient loyalty</p>
                </div>

                <div class="text-center p-6 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl">
                    <div class="bg-orange-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <p class="text-3xl font-bold text-orange-600 mb-2">
                        <?php 
                        $practice_days = max(1, (time() - strtotime($doctor['created_at'])) / 86400);
                        echo round($stats['total_receipts'] / $practice_days * 30, 0); 
                        ?>
                    </p>
                    <p class="text-sm text-gray-600">Monthly Visit Rate</p>
                    <p class="text-xs text-gray-500 mt-1">Projected visits</p>
                </div>
            </div>
        </div>

        <!-- Footer with Report Information -->
        <div class="bg-white rounded-xl shadow-lg p-6 text-center">
            <div class="flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-lg font-semibold text-gray-700">Practice Dashboard Generated Successfully</span>
            </div>
            <p class="text-gray-600 mb-2">Report generated on <?php echo date('F d, Y \a\t H:i:s'); ?></p>
            <p class="text-sm text-gray-500">
                This comprehensive dashboard shows analytics for Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?>'s medical practice
            </p>
            <div class="mt-4 flex justify-center space-x-6 text-sm text-gray-500">
                <span>Total Patients: <?php echo $stats['total_patients']; ?></span>
                <span>Total Revenue: Rs.<?php echo number_format($stats['total_income']); ?></span>
                <span>Active Since: <?php echo date('M Y', strtotime($doctor['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white mt-12 shadow-2xl print-hide" style="border-radius: 50px 50px 0 0;">
        <div class="container mx-auto px-6 py-8">
            <div class="text-center">
                <p class="text-indigo-300 text-sm mb-2">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript for Charts and Interactions -->
    <script>
        let incomeChart, dailyChart;
        const dailyData = <?php echo json_encode(array_reverse($daily_pattern)); ?>;
        
        // Initialize Charts
        function initCharts() {
            // Income Trend Chart
            <?php if (count($monthly_trend) > 0): ?>
            const incomeCtx = document.getElementById('incomeChart').getContext('2d');
            incomeChart = new Chart(incomeCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($monthly_trend, 'month')) . "'"; ?>],
                    datasets: [{
                        label: 'Revenue (Rs.)',
                        data: [<?php echo implode(',', array_column($monthly_trend, 'income')); ?>],
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        pointBackgroundColor: 'rgb(34, 197, 94)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                callback: function(value) {
                                    return 'Rs.' + value.toLocaleString();
                                }
                            }
                        },
                        x: { grid: { color: 'rgba(0,0,0,0.05)' } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: Rs.' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    elements: { point: { hoverBackgroundColor: 'rgb(34, 197, 94)' } }
                }
            });
            <?php endif; ?>
            
            // Daily Activity Chart
            <?php if (count($daily_pattern) > 0): ?>
            const dailyCtx = document.getElementById('dailyChart').getContext('2d');
            dailyChart = new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: dailyData.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                    datasets: [{
                        label: 'Prescriptions',
                        data: dailyData.map(d => d.prescriptions),
                        backgroundColor: 'rgba(99, 102, 241, 0.8)',
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    const dataPoint = dailyData[context.dataIndex];
                                    return [
                                        'Income: Rs.' + dataPoint.daily_income.toLocaleString(),
                                        'Patients: ' + dataPoint.unique_patients
                                    ];
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        }

        // Update Daily Chart based on selection
        function updateActivityChart(type) {
            if (!dailyChart) return;
            
            let data, label, color;
            switch(type) {
                case 'income':
                    data = dailyData.map(d => d.daily_income);
                    label = 'Daily Income (Rs.)';
                    color = 'rgba(34, 197, 94, 0.8)';
                    break;
                case 'patients':
                    data = dailyData.map(d => d.unique_patients);
                    label = 'Unique Patients';
                    color = 'rgba(168, 85, 247, 0.8)';
                    break;
                default:
                    data = dailyData.map(d => d.prescriptions);
                    label = 'Prescriptions';
                    color = 'rgba(99, 102, 241, 0.8)';
            }
            
            dailyChart.data.datasets[0].data = data;
            dailyChart.data.datasets[0].label = label;
            dailyChart.data.datasets[0].backgroundColor = color;
            dailyChart.data.datasets[0].borderColor = color.replace('0.8', '1');
            dailyChart.update('active');
        }

        // Toggle Edit Mode
        function toggleEditMode() {
            const modal = document.getElementById('editModal');
            modal.classList.toggle('hidden');
        }

        // Auto-refresh functionality (simplified - no server endpoint needed)
        async function refreshData() {
            // This would refresh data from server if needed
            // For now, just log that refresh was attempted
            console.log('Dashboard data refresh completed');
        }

        // Animate number changes
        function updateNumberWithAnimation(elementId, newValue) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const currentValue = parseInt(element.textContent) || 0;
            const increment = (newValue - currentValue) / 30;
            let current = currentValue;
            
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
                    element.textContent = newValue;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.round(current);
                }
            }, 50);
        }

        // Print optimizations
        function optimizeForPrint() {
            // Add print-specific styles
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                    .card-hover { box-shadow: none; }
                    .gradient-bg { background: #4F46E5 !important; }
                    .stat-card { background: linear-gradient(135deg, var(--from-color), var(--to-color)) !important; }
                }
            `;
            document.head.appendChild(style);
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            optimizeForPrint();
            
            // Auto-refresh every 5 minutes
            setInterval(refreshData, 300000);
        });

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                toggleEditMode();
            }
        });

        // Add smooth scrolling to page
        document.documentElement.style.scrollBehavior = 'smooth';

// Modern smooth page transition system
document.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', e => {
        // Only handle internal links
        if (link.hostname === window.location.hostname && 
            !link.target && 
            !link.classList.contains('no-transition') &&
            !link.hash) {
            
            e.preventDefault();
            const targetUrl = link.href;
            
            // Prevent multiple clicks
            if (document.body.classList.contains('page-transitioning-out')) {
                return;
            }
            
            // Start smooth exit transition
            document.body.classList.add('page-transitioning-out');
            
            // Add subtle haptic feel with smooth timing
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 700);
        }
    });
});

// Smooth enter animation on page load
window.addEventListener('DOMContentLoaded', () => {
    // Prevent flash of unstyled content
    document.body.style.visibility = 'visible';
    document.body.classList.add('page-transitioning-in');
    
    setTimeout(() => {
        document.body.classList.remove('page-transitioning-in');
    }, 800);
});

// Handle browser navigation
window.addEventListener('pageshow', (e) => {
    if (e.persisted) {
        document.body.classList.remove('page-transitioning-out', 'page-transitioning-in');
    }
});

// Prevent flash on initial load
document.body.style.visibility = 'hidden';
    </script>
</body>
</html>