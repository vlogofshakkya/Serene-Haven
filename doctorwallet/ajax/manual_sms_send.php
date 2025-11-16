<?php
require_once '../config.php';
requireDoctor();

$user = getCurrentUser();
$doctorId = $user['id'];

// Get pending reminders count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM pending_sms_reminders
    WHERE doctor_id = ?
");
$stmt->execute([$doctorId]);
$pendingCount = $stmt->fetch()['count'];

// Get recent SMS log
$stmt = $pdo->prepare("
    SELECT * FROM sms_logs 
    WHERE doctor_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$doctorId]);
$recentSMS = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send SMS Reminders - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold">SMS Reminder Trigger</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span>Welcome, <?php echo htmlspecialchars($user['doctor_name']); ?></span>
                <a href="../index.php" class="hover:bg-blue-700 px-3 py-2 rounded">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <!-- Alert Box -->
        <div id="alertBox" class="hidden mb-6"></div>

        <!-- Trigger Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Manual SMS Reminder Trigger</h2>
                    <p class="text-gray-600 mt-2">Click the button below to manually send SMS reminders for pending appointments</p>
                </div>
                <div class="bg-blue-100 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $pendingCount; ?></div>
                    <div class="text-sm text-gray-600">Pending Reminders</div>
                </div>
            </div>

            <div class="flex items-center justify-center space-x-4">
                <button id="triggerBtn" 
                        class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-8 py-4 rounded-lg font-semibold text-lg transition duration-300 shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send SMS Reminders Now
                </button>
                
                <button id="refreshBtn" 
                        onclick="location.reload()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-4 rounded-lg font-semibold">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
            </div>

            <!-- Progress Section -->
            <div id="progressSection" class="hidden mt-6">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <div class="flex items-center">
                        <div class="spinner mr-4"></div>
                        <div>
                            <p class="font-semibold text-blue-800">Sending SMS reminders...</p>
                            <p class="text-sm text-blue-600" id="progressText">Please wait while we process the reminders</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div id="resultsSection" class="hidden mt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Results</h3>
                <div id="resultsContent"></div>
            </div>
        </div>

        <!-- Recent SMS Log -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Recent SMS Log</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recentSMS)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No SMS logs found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentSMS as $log): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d M Y H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($log['phone_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php echo htmlspecialchars(substr($log['message'], 0, 50)) . '...'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($log['status'] === 'sent'): ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>Sent
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                <i class="fas fa-times-circle mr-1"></i>Failed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Log Viewer -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">SMS Reminder Log File</h3>
            <div class="bg-gray-800 text-green-400 p-4 rounded font-mono text-sm overflow-x-auto max-h-96">
                <pre id="logContent">Loading log file...</pre>
            </div>
            <button onclick="loadLogFile()" class="mt-3 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                <i class="fas fa-sync-alt mr-2"></i>Refresh Log
            </button>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Load log file on page load
        loadLogFile();

        // Trigger SMS sending
        $('#triggerBtn').click(function() {
            if (!confirm('Are you sure you want to send SMS reminders now?')) {
                return;
            }

            $('#triggerBtn').prop('disabled', true);
            $('#progressSection').removeClass('hidden');
            $('#resultsSection').addClass('hidden');
            $('#alertBox').addClass('hidden');

            $.ajax({
                url: '../ajax/send_visit_reminders.php',
                type: 'POST',
                dataType: 'json',
                timeout: 120000, // 2 minutes timeout
                success: function(response) {
                    console.log('Response:', response);
                    displayResults(response);
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    showAlert('error', 'Error: '