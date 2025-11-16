<?php
require_once '../config.php';
requireDoctor();

// Get filter parameters
$patient_filter = $_GET['patient'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query conditions
$conditions = ['e.doctor_id = ?'];
$params = [$_SESSION['user_id']];

if ($patient_filter) {
    $conditions[] = "(
        (e.patient_type = 'adult' AND a.name LIKE ?) OR 
        (e.patient_type = 'kid' AND k.name LIKE ?)
    )";
    $params[] = "%$patient_filter%";
    $params[] = "%$patient_filter%";
}

if ($date_from) {
    $conditions[] = "DATE(e.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(e.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $conditions);

// Get patient reports
$stmt = $pdo->prepare("
    SELECT e.*, 
           CASE 
               WHEN e.patient_type = 'adult' THEN a.name
               ELSE k.name
           END as patient_name,
           CASE 
               WHEN e.patient_type = 'adult' THEN a.phone_number
               ELSE ap.phone_number
           END as patient_phone,
           CASE 
               WHEN e.patient_type = 'kid' THEN ap.name
               ELSE NULL
           END as parent_name
    FROM e_receipts e
    LEFT JOIN adults a ON e.patient_type = 'adult' AND e.patient_id = a.id
    LEFT JOIN kids k ON e.patient_type = 'kid' AND e.patient_id = k.id
    LEFT JOIN adults ap ON e.patient_type = 'kid' AND k.parent_id = ap.id
    WHERE $where_clause
    ORDER BY e.created_at DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get summary statistics
$total_patients = count(array_unique(array_map(function($r) {
    return $r['patient_type'] . '_' . $r['patient_id'];
}, $reports)));

$total_revenue = array_sum(array_column($reports, 'total_amount'));
$total_receipts = count($reports);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Reports - Doctor Wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
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
<body class="bg-gray-100 min-h-screen" style="visibility: hidden;">
<!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4">
  <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-4">

    <!-- Left side (title) -->
    <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Patient Reports</h1>

    <!-- Center menu (responsive stack) -->
    <div class="flex flex-wrap justify-center md:justify-center gap-3">
      <!-- Patients Link -->
      <a href="patients.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-user-injured"></i>
        <span>Patients</span>
      </a>

      <!-- Medicines Link -->
      <a href="medicines.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-pills"></i>
        <span>Medicines</span>
      </a>

      <!-- Drug dispenser Link -->
      <a href="drug_dispenser.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-stethoscope"></i>
        <span>Drug dispenser</span>
      </a>

      <!-- File upload Link -->
      <a href="file_upload.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-file-upload"></i>
        <span>File upload</span>
      </a>

      <!-- All receipts Link -->
      <a href="receipts.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-receipt"></i>
        <span>All receipts</span>
      </a>

      <!-- Print Section Link -->
      <a href="print_section.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-pink-500 to-pink-600 hover:from-pink-600 hover:to-pink-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-print text-sm"></i>
        <span>Print Section</span>
      </a>
    </div>

    <!-- Right side -->
    <div class="flex justify-center md:justify-end">
      <a href="../index.php" class="bg-blue-800 hover:bg-blue-900 px-4 py-2 rounded text-sm md:text-base">
        Back to Dashboard
      </a>
    </div>
  </div>
</nav>

    <div class="container mx-auto p-6">
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Filter Reports</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Patient Name</label>
                    <input type="text" name="patient" value="<?php echo htmlspecialchars($patient_filter); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="Search by patient name">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        Filter
                    </button>
                    <a href="reports.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Patients</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $total_patients; ?></p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Receipts</h3>
                <p class="text-3xl font-bold text-green-600"><?php echo $total_receipts; ?></p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Revenue</h3>
                <p class="text-3xl font-bold text-purple-600"><?php echo formatCurrency($total_revenue); ?></p>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Patient Reports</h2>
                <?php if (!empty($reports)): ?>
                    <button onclick="printReports()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        Print Reports
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if (empty($reports)): ?>
                <div class="text-center text-gray-500 py-8">
                    <div class="text-4xl mb-4">📊</div>
                    <p>No reports found for the selected criteria</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto" id="reportsTable">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Date</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Patient</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Phone</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Symptoms</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Amount</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm">
                                        <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="font-medium"><?php echo htmlspecialchars($report['patient_name']); ?></div>
                                        <?php if ($report['parent_name']): ?>
                                            <div class="text-sm text-gray-500">Parent: <?php echo htmlspecialchars($report['parent_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <?php echo htmlspecialchars($report['patient_phone'] ?: 'N/A'); ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($report['symptoms']); ?>">
                                            <?php echo htmlspecialchars($report['symptoms']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 font-medium text-green-600">
                                        <?php echo formatCurrency($report['total_amount']); ?>
                                    </td>
                                    <td class="px-4 py-2">
                                        <button onclick="viewReportDetails(<?php echo $report['id']; ?>)" 
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Details Modal -->
    <div id="reportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-full overflow-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-semibold">Report Details</h3>
                <button onclick="closeReportModal()" class="text-red-500 hover:text-red-700 text-2xl font-bold">&times;</button>
            </div>
            <div id="reportContent" class="p-4">
                <!-- Report content will be loaded here -->
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

    <script>
    function viewReportDetails(receiptId) {
        $('#reportContent').html('<div class="text-center py-4">Loading...</div>');
        $('#reportModal').removeClass('hidden');
        
        $.get('../ajax/get_receipt_details.php', { id: receiptId }, function(data) {
            $('#reportContent').html(data);
        }).fail(function() {
            $('#reportContent').html('<div class="text-center py-4 text-red-500">Error loading report details</div>');
        });
    }

    function closeReportModal() {
        $('#reportModal').addClass('hidden');
    }

    function printReports() {
        window.print();
    }

    // Close modal on outside click
    $('#reportModal').click(function(e) {
        if (e.target === this) {
            closeReportModal();
        }
    });

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

    <style>
    @media print {
        nav, .no-print { display: none !important; }
        body { background: white !important; }
        .shadow { box-shadow: none !important; }
        .rounded-lg { border-radius: 0 !important; }
    }
    </style>
</body>
</html>