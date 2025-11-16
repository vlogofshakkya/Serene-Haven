<?php
/**
 * Staff SMS Reminder Management Page
 * File: pages/staff_sms_reminders.php
 */

require_once '../config.php';

// Check if user is logged in as staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$staffUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Reminders - Staff Dashboard</title>
    <link rel="icon" type="image/png" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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
<body class="bg-gray-100" style="visibility: hidden;">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold">SMS Reminder Management</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm">Staff: <?php echo htmlspecialchars($staffUser['name']); ?></span>
                <a href="../index.php" class="hover:text-blue-200">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <!-- Total Pending -->
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Pending</p>
                        <p class="text-3xl font-bold text-blue-600" id="totalPending">0</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-clock text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <!-- Send Today -->
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Send Today</p>
                        <p class="text-3xl font-bold text-green-600" id="sendToday">0</p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-calendar-check text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <!-- Overdue -->
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Overdue</p>
                        <p class="text-3xl font-bold text-red-600" id="overdue">0</p>
                    </div>
                    <div class="bg-red-100 p-3 rounded-full">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                </div>
            </div>

            <!-- Sent Today -->
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Sent Today</p>
                        <p class="text-3xl font-bold text-purple-600" id="sentToday">0</p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-check-circle text-2xl text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="pulse-dot inline-block w-3 h-3 bg-green-500 rounded-full"></span>
                    <span class="text-gray-700 font-medium">Auto-refresh every 30 seconds</span>
                </div>
                
                <div class="flex flex-wrap gap-3">
                    <button id="refreshBtn" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium transition flex items-center space-x-2">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh Now</span>
                    </button>
                    
                    <button id="sendTodayBtn" 
                            class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition flex items-center space-x-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send Today's SMS</span>
                    </button>
                    
                    <button id="sendAllBtn" 
                            class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition flex items-center space-x-2">
                        <i class="fas fa-broadcast-tower"></i>
                        <span>Send All Pending</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- SMS Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800">Pending SMS Reminders</h2>
                <p class="text-sm text-gray-600 mt-1">List of patients scheduled to receive SMS reminders</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b-2 border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <input type="checkbox" id="selectAll" class="rounded">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Phone</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Doctor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Visit Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Send Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Days Left</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody id="smsTableBody" class="divide-y divide-gray-200">
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                <div class="spinner mx-auto mb-3"></div>
                                Loading SMS reminders...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Send Progress Modal -->
        <div id="sendProgressModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-2xl font-bold text-gray-800">Sending SMS Reminders</h3>
                        <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Progress</span>
                            <span class="text-sm font-medium text-gray-700" id="progressText">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                            <div id="progressBar" class="bg-blue-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div id="progressDetails" class="space-y-2 max-h-96 overflow-y-auto">
                        <!-- Progress items will be added here -->
                    </div>
                    
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-green-600" id="successCount">0</p>
                                <p class="text-xs text-gray-600">Sent</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-red-600" id="failedCount">0</p>
                                <p class="text-xs text-gray-600">Failed</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-600" id="totalCount">0</p>
                                <p class="text-xs text-gray-600">Total</p>
                            </div>
                        </div>
                    </div>
                </div>
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
    let autoRefreshInterval;
    
    // Load SMS reminders
    function loadSMSReminders() {
        $.ajax({
            url: '../ajax/get_pending_sms.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateStatistics(response.statistics);
                    renderSMSTable(response.reminders);
                } else {
                    showError('Failed to load SMS reminders');
                }
            },
            error: function() {
                showError('Network error while loading SMS reminders');
            }
        });
    }
    
    // Update statistics
    function updateStatistics(stats) {
        $('#totalPending').text(stats.total);
        $('#sendToday').text(stats.today);
        $('#overdue').text(stats.overdue);
        $('#sentToday').text(stats.sent_today);
    }
    
    // Render SMS table
    function renderSMSTable(reminders) {
        const tbody = $('#smsTableBody');
        tbody.empty();
        
        if (reminders.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                        <p class="text-lg font-medium">No pending SMS reminders!</p>
                        <p class="text-sm">All reminders have been sent.</p>
                    </td>
                </tr>
            `);
            return;
        }
        
        reminders.forEach(function(reminder) {
            const statusBadge = getStatusBadge(reminder.send_status);
            const daysLeftBadge = getDaysLeftBadge(reminder.days_until_visit);
            
            const row = $(`
                <tr class="hover:bg-gray-50 transition" data-id="${reminder.appointment_id}">
                    <td class="px-4 py-3">
                        <input type="checkbox" class="sms-checkbox rounded" value="${reminder.appointment_id}">
                    </td>
                    <td class="px-4 py-3">${statusBadge}</td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">${reminder.patient_name}</div>
                        <div class="text-xs text-gray-500">${reminder.patient_type === 'adult' ? 'Adult' : 'Child'}</div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-gray-700">
                            <i class="fas fa-phone text-blue-500 mr-1"></i>
                            ${reminder.phone_number || 'N/A'}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm text-gray-900">${reminder.doctor_name}</div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm font-medium text-gray-700">${formatDate(reminder.next_visit_date)}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-gray-600">${formatDate(reminder.sms_send_date)}</span>
                    </td>
                    <td class="px-4 py-3">${daysLeftBadge}</td>
                    <td class="px-4 py-3">
                        <button class="send-single-btn bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm" 
                                data-id="${reminder.appointment_id}">
                            <i class="fas fa-paper-plane mr-1"></i> Send
                        </button>
                    </td>
                </tr>
            `);
            
            tbody.append(row);
        });
        
        // Attach event handlers
        $('.send-single-btn').on('click', function() {
            const appointmentId = $(this).data('id');
            sendSingleSMS(appointmentId);
        });
    }
    
    // Get status badge HTML
    function getStatusBadge(status) {
        const badges = {
            'Send Today': '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Send Today</span>',
            'Overdue': '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Overdue</span>',
            'Future': '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">Future</span>'
        };
        return badges[status] || status;
    }
    
    // Get days left badge
    function getDaysLeftBadge(days) {
        if (days < 0) {
            return `<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">${Math.abs(days)} days ago</span>`;
        } else if (days === 0) {
            return '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">Today</span>';
        } else if (days === 1) {
            return '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">Tomorrow</span>';
        } else {
            return `<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">${days} days</span>`;
        }
    }
    
    // Format date
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
    // Send single SMS
    function sendSingleSMS(appointmentId) {
        if (!confirm('Send SMS reminder to this patient?')) {
            return;
        }
        
        $.ajax({
            url: '../ajax/send_sms_reminder.php',
            type: 'POST',
            data: { appointment_ids: [appointmentId] },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showSuccess(`SMS sent successfully! (${response.results[0].status})`);
                    loadSMSReminders();
                } else {
                    showError(response.error || 'Failed to send SMS');
                }
            },
            error: function() {
                showError('Network error while sending SMS');
            }
        });
    }
    
    // Send today's SMS
    $('#sendTodayBtn').on('click', function() {
        const todayCount = parseInt($('#sendToday').text());
        
        if (todayCount === 0) {
            showError('No SMS reminders scheduled for today');
            return;
        }
        
        if (!confirm(`Send ${todayCount} SMS reminder(s) scheduled for today?`)) {
            return;
        }
        
        sendBulkSMS('today');
    });
    
    // Send all pending SMS
    $('#sendAllBtn').on('click', function() {
        const totalCount = parseInt($('#totalPending').text());
        
        if (totalCount === 0) {
            showError('No pending SMS reminders');
            return;
        }
        
        if (!confirm(`Send ALL ${totalCount} pending SMS reminder(s)?\n\nThis includes overdue and today's reminders.`)) {
            return;
        }
        
        sendBulkSMS('all');
    });
    
    // Send bulk SMS
    function sendBulkSMS(type) {
        showProgressModal();
        
        $.ajax({
            url: '../ajax/send_sms_reminder.php',
            type: 'POST',
            data: { send_type: type },
            dataType: 'json',
            success: function(response) {
                handleBulkSendResponse(response);
            },
            error: function(xhr) {
                hideProgressModal();
                showError('Network error while sending SMS');
            }
        });
    }
    
    // Handle bulk send response
    function handleBulkSendResponse(response) {
        if (!response.success) {
            hideProgressModal();
            showError(response.error || 'Failed to send SMS');
            return;
        }
        
        const results = response.results || [];
        const total = results.length;
        let processed = 0;
        let success = 0;
        let failed = 0;
        
        $('#totalCount').text(total);
        
        results.forEach(function(result, index) {
            setTimeout(function() {
                processed++;
                
                if (result.status === 'sent') {
                    success++;
                    addProgressItem(result.patient_name, 'success', 'SMS sent successfully');
                } else {
                    failed++;
                    addProgressItem(result.patient_name, 'error', result.error || 'Failed');
                }
                
                $('#successCount').text(success);
                $('#failedCount').text(failed);
                
                const progress = Math.round((processed / total) * 100);
                $('#progressBar').css('width', progress + '%');
                $('#progressText').text(progress + '%');
                
                if (processed === total) {
                    setTimeout(function() {
                        loadSMSReminders();
                    }, 2000);
                }
            }, index * 100);
        });
    }
    
    // Show progress modal
    function showProgressModal() {
        $('#progressBar').css('width', '0%');
        $('#progressText').text('0%');
        $('#progressDetails').empty();
        $('#successCount').text('0');
        $('#failedCount').text('0');
        $('#totalCount').text('0');
        $('#sendProgressModal').removeClass('hidden');
    }
    
    // Hide progress modal
    function hideProgressModal() {
        $('#sendProgressModal').addClass('hidden');
    }
    
    // Add progress item
    function addProgressItem(patientName, status, message) {
        const icon = status === 'success' ? 
            '<i class="fas fa-check-circle text-green-500"></i>' : 
            '<i class="fas fa-times-circle text-red-500"></i>';
        
        const item = $(`
            <div class="flex items-start space-x-3 p-3 bg-white rounded-lg border ${status === 'success' ? 'border-green-200' : 'border-red-200'}">
                <div class="flex-shrink-0 mt-0.5">${icon}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">${patientName}</p>
                    <p class="text-xs text-gray-500">${message}</p>
                </div>
            </div>
        `);
        
        $('#progressDetails').prepend(item);
    }
    
    // Close modal
    $('#closeModalBtn').on('click', function() {
        hideProgressModal();
    });
    
    // Refresh button
    $('#refreshBtn').on('click', function() {
        $(this).find('i').addClass('fa-spin');
        loadSMSReminders();
        setTimeout(() => {
            $(this).find('i').removeClass('fa-spin');
        }, 1000);
    });
    
    // Select all checkbox
    $('#selectAll').on('change', function() {
        $('.sms-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    // Show success message
    function showSuccess(message) {
        const alert = $(`
            <div class="fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 flex items-center space-x-3">
                <i class="fas fa-check-circle text-2xl"></i>
                <span>${message}</span>
            </div>
        `);
        $('body').append(alert);
        setTimeout(() => alert.fadeOut(500, function() { $(this).remove(); }), 3000);
    }
    
    // Show error message
    function showError(message) {
        const alert = $(`
            <div class="fixed top-4 right-4 bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 flex items-center space-x-3">
                <i class="fas fa-exclamation-circle text-2xl"></i>
                <span>${message}</span>
            </div>
        `);
        $('body').append(alert);
        setTimeout(() => alert.fadeOut(500, function() { $(this).remove(); }), 5000);
    }
    
    // Auto-refresh every 30 seconds
    function startAutoRefresh() {
        autoRefreshInterval = setInterval(function() {
            loadSMSReminders();
        }, 30000);
    }
    
    // Initialize
    $(document).ready(function() {
        loadSMSReminders();
        startAutoRefresh();
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
</body>
</html>