<?php
require_once '../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if patient is logged in
if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
    header('Location: patient_login.php');
    exit;
}

$patientId = $_SESSION['patient_id'];
$patientType = $_SESSION['patient_type'];
$doctorId = $_SESSION['patient_doctor_id'];

// Fetch patient details
if ($patientType === 'adult') {
    $stmt = $pdo->prepare("SELECT * FROM adults WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$patientId, $doctorId]);
    $patient = $stmt->fetch();
    
    // Get linked kids
    $stmt = $pdo->prepare("SELECT * FROM kids WHERE parent_id = ? AND doctor_id = ?");
    $stmt->execute([$patientId, $doctorId]);
    $linkedKids = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT k.*, a.name as parent_name, a.phone_number as parent_phone 
                           FROM kids k 
                           JOIN adults a ON k.parent_id = a.id 
                           WHERE k.id = ? AND k.doctor_id = ?");
    $stmt->execute([$patientId, $doctorId]);
    $patient = $stmt->fetch();
    $linkedKids = [];
}

// Get doctor details
$stmt = $pdo->prepare("SELECT doctor_name, phone_number FROM doctors WHERE id = ?");
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

// Fetch lab reports for patient
$labReports = [];
$stmt = $pdo->prepare("
    SELECT * FROM lab_reports 
    WHERE patient_type = ? AND patient_id = ? AND doctor_id = ?
    ORDER BY upload_date DESC
");
$stmt->execute([$patientType, $patientId, $doctorId]);
$labReports = $stmt->fetchAll();

// Fetch next visit appointments
$appointments = [];
$stmt = $pdo->prepare("
    SELECT * FROM next_visit_appointments 
    WHERE patient_type = ? AND patient_id = ? AND doctor_id = ? AND status = 'scheduled'
    ORDER BY next_visit_date ASC
");
$stmt->execute([$patientType, $patientId, $doctorId]);
$appointments = $stmt->fetchAll();

// If adult, fetch kids' data
$kidsData = [];
if ($patientType === 'adult' && !empty($linkedKids)) {
    foreach ($linkedKids as $kid) {
        // Lab reports
        $stmt = $pdo->prepare("
            SELECT * FROM lab_reports 
            WHERE patient_type = 'kid' AND patient_id = ? AND doctor_id = ?
            ORDER BY upload_date DESC
        ");
        $stmt->execute([$kid['id'], $doctorId]);
        $kidLabReports = $stmt->fetchAll();
        
        // Appointments
        $stmt = $pdo->prepare("
            SELECT * FROM next_visit_appointments 
            WHERE patient_type = 'kid' AND patient_id = ? AND doctor_id = ? AND status = 'scheduled'
            ORDER BY next_visit_date ASC
        ");
        $stmt->execute([$kid['id'], $doctorId]);
        $kidAppointments = $stmt->fetchAll();
        
        $kidsData[] = [
            'info' => $kid,
            'lab_reports' => $kidLabReports,
            'appointments' => $kidAppointments
        ];
    }
}

// Get prescription history
$stmt = $pdo->prepare("
    SELECT er.*, COUNT(ri.id) as medicine_count
    FROM e_receipts er
    LEFT JOIN receipt_items ri ON er.id = ri.receipt_id
    WHERE er.patient_type = ? AND er.patient_id = ? AND er.doctor_id = ?
    GROUP BY er.id
    ORDER BY er.created_at DESC
    LIMIT 10
");
$stmt->execute([$patientType, $patientId, $doctorId]);
$prescriptions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .section-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            margin-bottom: 30px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-upcoming {
            background: #fbbf24;
            color: #78350f;
        }
        .status-overdue {
            background: #ef4444;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-blue-800 via-blue-600 to-blue-800 text-white p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="../iconbgr_b.png" alt="Logo" class="h-12 w-auto">
                <h1 class="text-2xl font-bold">Patient Portal</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($patient['name']); ?></span>
                <a href="patient_logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <!-- Patient Information Section -->
        <div class="section-card mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold mb-2">
                        <i class="fas fa-user-circle mr-3"></i><?php echo htmlspecialchars($patient['name']); ?>
                    </h2>
                    <p class="text-blue-100">
                        <i class="fas fa-hospital-user mr-2"></i>Doctor: <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                    </p>
                    <p class="text-blue-100">
                        <i class="fas fa-phone mr-2"></i>Contact: <?php echo htmlspecialchars($patient['phone_number'] ?? $patient['parent_phone']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <p class="text-sm">Patient Type</p>
                        <p class="text-2xl font-bold"><?php echo ucfirst($patientType); ?></p>
                    </div>
                </div>
            </div>
            <?php if (!empty($patient['allergies'])): ?>
            <div class="mt-4 bg-red-500 bg-opacity-30 border-2 border-red-300 rounded-lg p-3">
                <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Allergies:</p>
                <p><?php echo htmlspecialchars($patient['allergies']); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Total Lab Reports -->
            <div class="bg-white rounded-lg shadow-lg p-6 card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Lab Reports</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo count($labReports); ?></p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-full">
                        <i class="fas fa-flask text-3xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="bg-white rounded-lg shadow-lg p-6 card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Appointments</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo count($appointments); ?></p>
                    </div>
                    <div class="bg-green-100 p-4 rounded-full">
                        <i class="fas fa-calendar-check text-3xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <!-- Total Prescriptions -->
            <div class="bg-white rounded-lg shadow-lg p-6 card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Prescriptions</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo count($prescriptions); ?></p>
                    </div>
                    <div class="bg-purple-100 p-4 rounded-full">
                        <i class="fas fa-prescription text-3xl text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-calendar-alt text-green-600 mr-3"></i>Your Upcoming Appointments
            </h3>
            <?php if (!empty($appointments)): ?>
                <div class="space-y-3">
                    <?php foreach ($appointments as $apt): 
                        $visitDate = new DateTime($apt['next_visit_date']);
                        $today = new DateTime();
                        $daysUntil = $today->diff($visitDate)->days;
                        $isOverdue = $visitDate < $today;
                    ?>
                    <div class="border-l-4 <?php echo $isOverdue ? 'border-red-500' : 'border-green-500'; ?> bg-gray-50 p-4 rounded">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-bold text-lg"><?php echo $visitDate->format('F d, Y'); ?></p>
                                <p class="text-gray-600 text-sm">
                                    <?php if ($isOverdue): ?>
                                        <span class="status-badge status-overdue">Overdue</span>
                                    <?php else: ?>
                                        <span class="status-badge status-upcoming"><?php echo $daysUntil; ?> days remaining</span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($apt['notes'])): ?>
                                <p class="text-gray-700 mt-2"><i class="fas fa-sticky-note mr-2"></i><?php echo htmlspecialchars($apt['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <?php if ($apt['sms_sent']): ?>
                                <p class="text-sm text-green-600"><i class="fas fa-check-circle mr-1"></i>Reminder Sent</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No upcoming appointments scheduled.</p>
            <?php endif; ?>
        </div>

        <!-- Lab Reports -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-flask text-blue-600 mr-3"></i>Your Lab Reports
            </h3>
            <?php if (!empty($labReports)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($labReports as $report): ?>
                    <div class="border rounded-lg p-4 hover:shadow-lg transition card">
                        <div class="flex items-start justify-between mb-3">
                            <div class="bg-blue-100 p-3 rounded-lg">
                                <i class="fas fa-file-medical text-2xl text-blue-600"></i>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($report['upload_date'])); ?></span>
                        </div>
                        <h4 class="font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($report['document_name']); ?></h4>
                        <?php if (!empty($report['notes'])): ?>
                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($report['notes']); ?></p>
                        <?php endif; ?>
                        <a href="../lab_reports/<?php echo htmlspecialchars($report['file_path']); ?>" 
                           target="_blank" 
                           class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 rounded">
                            <i class="fas fa-download mr-2"></i>Download
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No lab reports available.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Prescriptions -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-prescription text-purple-600 mr-3"></i>Recent Prescriptions
            </h3>
            <?php if (!empty($prescriptions)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diagnosis</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Medicines</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($prescriptions as $rx): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($rx['created_at'])); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($rx['diagnosis'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4"><?php echo $rx['medicine_count']; ?> items</td>
                                <td class="px-6 py-4 font-bold">Rs. <?php echo number_format($rx['total_amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No prescription history available.</p>
            <?php endif; ?>
        </div>

        <!-- Kids Data (if adult patient) -->
        <?php if ($patientType === 'adult' && !empty($kidsData)): ?>
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-child text-pink-600 mr-3"></i>Linked Children Accounts
            </h3>
            <?php foreach ($kidsData as $kidData): ?>
            <div class="border-2 border-pink-200 rounded-lg p-4 mb-4">
                <h4 class="text-xl font-bold text-pink-600 mb-3">
                    <i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($kidData['info']['name']); ?>
                </h4>
                
                <!-- Kid's Appointments -->
                <div class="mb-4">
                    <h5 class="font-bold text-gray-700 mb-2"><i class="fas fa-calendar mr-2"></i>Appointments</h5>
                    <?php if (!empty($kidData['appointments'])): ?>
                        <div class="space-y-2">
                            <?php foreach ($kidData['appointments'] as $apt): 
                                $visitDate = new DateTime($apt['next_visit_date']);
                                $today = new DateTime();
                                $daysUntil = $today->diff($visitDate)->days;
                                $isOverdue = $visitDate < $today;
                            ?>
                            <div class="bg-pink-50 border-l-4 <?php echo $isOverdue ? 'border-red-500' : 'border-pink-500'; ?> p-3 rounded">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-bold"><?php echo $visitDate->format('F d, Y'); ?></p>
                                        <?php if ($isOverdue): ?>
                                            <span class="status-badge status-overdue">Overdue</span>
                                        <?php else: ?>
                                            <span class="status-badge status-upcoming"><?php echo $daysUntil; ?> days</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No appointments scheduled.</p>
                    <?php endif; ?>
                </div>

                <!-- Kid's Lab Reports -->
                <div>
                    <h5 class="font-bold text-gray-700 mb-2"><i class="fas fa-flask mr-2"></i>Lab Reports</h5>
                    <?php if (!empty($kidData['lab_reports'])): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($kidData['lab_reports'] as $report): ?>
                            <div class="bg-pink-50 border rounded-lg p-3">
                                <p class="font-bold text-sm"><?php echo htmlspecialchars($report['document_name']); ?></p>
                                <p class="text-xs text-gray-600 mb-2"><?php echo date('M d, Y', strtotime($report['upload_date'])); ?></p>
                                <a href="../lab_reports/<?php echo htmlspecialchars($report['file_path']); ?>" 
                                   target="_blank" 
                                   class="text-sm bg-pink-600 hover:bg-pink-700 text-white px-3 py-1 rounded inline-block">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No lab reports available.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>