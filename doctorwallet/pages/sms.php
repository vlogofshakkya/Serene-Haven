<?php
require_once '../config.php';
requireDoctor();

$user = getCurrentUser();
$doctorId = $user['id'];
$doctorName = $user['doctor_name'];

// Get doctor's SMS configuration
$stmt = $pdo->prepare("
    SELECT dsc.*, ssi.sender_id, ssi.api_key, ssi.is_active as sender_active
    FROM doctor_sms_config dsc
    JOIN sms_sender_ids ssi ON dsc.sender_id = ssi.id
    WHERE dsc.doctor_id = ? AND dsc.is_active = 1
");
$stmt->execute([$doctorId]);
$smsConfig = $stmt->fetch();

// Calculate remaining units
$remainingUnits = 0;
$smsEnabled = false;
if ($smsConfig && $smsConfig['sender_active']) {
    $remainingUnits = $smsConfig['total_units'] - $smsConfig['used_units'];
    $smsEnabled = $remainingUnits > 0;
}

// Get patients with phone numbers
$stmt = $pdo->prepare("
    SELECT 'adult' as type, id, name, phone_number, '' as parent_name
    FROM adults 
    WHERE doctor_id = ? AND phone_number IS NOT NULL AND phone_number != ''
    UNION ALL
    SELECT 'kid' as type, k.id, k.name, a.phone_number, a.name as parent_name
    FROM kids k 
    JOIN adults a ON k.parent_id = a.id 
    WHERE k.doctor_id = ? AND a.phone_number IS NOT NULL AND a.phone_number != ''
    ORDER BY name
");
$stmt->execute([$doctorId, $doctorId]);
$patients = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Service - Doctor Wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="icon.svg" color="#0F2E44">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .patient-checkbox {
            transform: scale(1.2);
        }
        .template-btn {
            transition: all 0.3s ease;
        }
        .template-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
<body class="bg-gray-100 min-h-screen" style="visibility: hidden;">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold">SMS Service</h1>
            </div>
            <div class="flex items-center space-x-4">
                <!-- SMS Units Display -->
                <?php if ($smsConfig): ?>
                    <div class="bg-white bg-opacity-20 px-4 py-2 rounded-lg">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-sms"></i>
                            <div>
                                <div class="text-xs opacity-75">SMS Units</div>
                                <div class="font-bold <?php echo $remainingUnits <= 10 ? 'text-yellow-300' : ''; ?>">
                                    <?php echo number_format($remainingUnits); ?> / <?php echo number_format($smsConfig['total_units']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <span>Welcome, <?php echo htmlspecialchars($user['doctor_name']); ?></span>
                <a href="../index.php" class="hover:bg-blue-700 px-3 py-2 rounded">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <!-- SMS Configuration Status -->
        <?php if (!$smsConfig): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl mr-4"></i>
                    <div>
                        <p class="font-semibold text-yellow-800">SMS Service Not Configured</p>
                        <p class="text-yellow-700 text-sm">Please contact the administrator to set up SMS service for your account.</p>
                    </div>
                </div>
            </div>
        <?php elseif (!$smsEnabled): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-ban text-red-400 text-2xl mr-4"></i>
                    <div>
                        <p class="font-semibold text-red-800">SMS Units Depleted</p>
                        <p class="text-red-700 text-sm">You have used all your SMS units. Please contact the administrator to add more units.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($remainingUnits <= 10): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-yellow-400 text-2xl mr-4"></i>
                    <div>
                        <p class="font-semibold text-yellow-800">Low SMS Units</p>
                        <p class="text-yellow-700 text-sm">You have only <?php echo $remainingUnits; ?> SMS units remaining. Please contact the administrator to add more.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">SMS Service</h2>
                    <p class="text-gray-600">Send SMS messages to your patients via text.lk</p>
                    <?php if ($smsConfig): ?>
                        <p class="text-sm text-gray-500 mt-1">Sender ID: <span class="font-semibold"><?php echo htmlspecialchars($smsConfig['sender_id']); ?></span></p>
                    <?php endif; ?>
                </div>
                <div class="bg-gradient-to-r from-cyan-500 to-blue-500 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-sms mr-2"></i>
                    <span class="font-semibold">Powered by text.lk</span>
                </div>
            </div>
        </div>

        <!-- SMS Form -->
        <div class="bg-white rounded-lg shadow p-6 <?php echo !$smsEnabled ? 'opacity-50 pointer-events-none' : ''; ?>">
            <form id="smsForm">
                <!-- Patient Selection -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-lg font-semibold text-gray-700">Select Patients</label>
                        <div class="flex space-x-2">
                            <button type="button" id="selectAll" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-check-all mr-1"></i>Select All
                            </button>
                            <button type="button" id="clearAll" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-times mr-1"></i>Clear All
                            </button>
                        </div>
                    </div>
                    
                    <!-- Patient Filter -->
                    <div class="mb-4">
                        <input type="text" id="patientFilter" placeholder="Search patients..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Patients List -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4">
                        <?php foreach ($patients as $patient): 
                            $formattedNumber = formatPhoneNumber($patient['phone_number']);
                        ?>
                            <div class="patient-item p-3 border border-gray-200 rounded-lg hover:bg-blue-50">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" class="patient-checkbox mr-3 text-blue-600" 
                                           value="<?php echo $formattedNumber; ?>" 
                                           data-name="<?php echo htmlspecialchars($patient['name']); ?>"
                                           data-type="<?php echo $patient['type']; ?>">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-800">
                                            <?php echo htmlspecialchars($patient['name']); ?>
                                            <?php if ($patient['type'] === 'kid'): ?>
                                                <span class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded ml-2">Child</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo $formattedNumber; ?>
                                            <?php if ($patient['parent_name']): ?>
                                                <span class="text-xs">(Parent: <?php echo htmlspecialchars($patient['parent_name']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Selected Numbers Display -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Selected Numbers (<span id="selectedCount">0</span>)</label>
                        <textarea id="selectedNumbers" readonly 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                rows="3" placeholder="Selected phone numbers will appear here..."></textarea>
                    </div>
                </div>

                <!-- Message Templates -->
                <div class="mb-6">
                    <label class="block text-lg font-semibold text-gray-700 mb-4">Message Templates</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <button type="button" class="template-btn bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg text-left"
                                data-template="From Dr. <?php echo htmlspecialchars($doctorName); ?>: Dear Patient, This is a reminder for your appointment at our clinic. Please arrive 15 minutes early. Thank you.">
                            <i class="fas fa-calendar-alt mb-2 text-lg"></i>
                            <div class="font-semibold">Appointment Reminder</div>
                            <div class="text-xs opacity-75">Appointment notification</div>
                        </button>

                        <button type="button" class="template-btn bg-green-500 hover:bg-green-600 text-white p-4 rounded-lg text-left"
                                data-template="From Dr. <?php echo htmlspecialchars($doctorName); ?>: Dear Patient, Your prescription is ready for collection. Please visit our clinic during working hours. Thank you.">
                            <i class="fas fa-prescription-bottle-alt mb-2 text-lg"></i>
                            <div class="font-semibold">Prescription Ready</div>
                            <div class="text-xs opacity-75">Medicine collection notice</div>
                        </button>

                        <button type="button" class="template-btn bg-purple-500 hover:bg-purple-600 text-white p-4 rounded-lg text-left"
                                data-template="From Dr. <?php echo htmlspecialchars($doctorName); ?>: Dear Patient, Please remember to take your medications as prescribed. If you have any side effects, contact us immediately.">
                            <i class="fas fa-pills mb-2 text-lg"></i>
                            <div class="font-semibold">Medication Reminder</div>
                            <div class="text-xs opacity-75">Take medicine reminder</div>
                        </button>

                        <button type="button" class="template-btn bg-orange-500 hover:bg-orange-600 text-white p-4 rounded-lg text-left"
                                data-template="From Dr. <?php echo htmlspecialchars($doctorName); ?>: Dear Patient, Your test results are ready. Please contact our clinic to schedule a follow-up appointment.">
                            <i class="fas fa-flask mb-2 text-lg"></i>
                            <div class="font-semibold">Test Results</div>
                            <div class="text-xs opacity-75">Lab results notification</div>
                        </button>

                        <button type="button" class="template-btn bg-red-500 hover:bg-red-600 text-white p-4 rounded-lg text-left"
                                data-template="From Dr. <?php echo htmlspecialchars($doctorName); ?>: URGENT: Please contact our clinic immediately regarding your health matter. Call us at your earliest convenience.">
                            <i class="fas fa-exclamation-triangle mb-2 text-lg"></i>
                            <div class="font-semibold">Urgent Notice</div>
                            <div class="text-xs opacity-75">Emergency communication</div>
                        </button>

                        <button type="button" class="template-btn bg-yellow-500 hover:bg-yellow-600 text-white p-4 rounded-lg text-left"
                                data-template="From Dr. <?php echo htmlspecialchars($doctorName); ?>: Dear Patient, Thank you for visiting our clinic. If you have any concerns about your treatment, please don't hesitate to contact us.">
                            <i class="fas fa-heart mb-2 text-lg"></i>
                            <div class="font-semibold">Thank You</div>
                            <div class="text-xs opacity-75">Post-visit message</div>
                        </button>
                    </div>
                </div>

                <!-- Message Text Area -->
                <div class="mb-6">
                    <label class="block text-lg font-semibold text-gray-700 mb-2">Message Content</label>
                    <textarea id="messageContent" name="message" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                            rows="5" placeholder="Type your message here..." maxlength="160"></textarea>
                    <div class="flex justify-between mt-2">
                        <span class="text-sm text-gray-500">Characters: <span id="charCount">0</span>/160</span>
                        <button type="button" id="clearMessage" class="text-sm text-red-500 hover:text-red-700">
                            <i class="fas fa-trash mr-1"></i>Clear Message
                        </button>
                    </div>
                </div>

                <!-- Send Button -->
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        SMS will be sent via text.lk service
                        <?php if ($smsEnabled): ?>
                            <span class="ml-2 font-semibold">(<?php echo $remainingUnits; ?> units available)</span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" id="sendSMS" 
                            class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-8 py-3 rounded-lg font-semibold transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                            <?php echo !$smsEnabled ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane mr-2"></i>Send SMS
                    </button>
                </div>
            </form>
        </div>

        <!-- SMS History -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent SMS History</h3>
            <div id="smsHistory">
                <!-- SMS history will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="text-gray-700">Sending SMS...</span>
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
        const smsEnabled = <?php echo $smsEnabled ? 'true' : 'false'; ?>;
        const remainingUnits = <?php echo $remainingUnits; ?>;

        $(document).ready(function() {
            // Patient filter functionality
            $('#patientFilter').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('.patient-item').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Select/Clear All functionality
            $('#selectAll').click(function() {
                $('.patient-checkbox:visible').prop('checked', true);
                updateSelectedNumbers();
            });

            $('#clearAll').click(function() {
                $('.patient-checkbox').prop('checked', false);
                updateSelectedNumbers();
            });

            // Update selected numbers when checkbox changes
            $(document).on('change', '.patient-checkbox', function() {
                updateSelectedNumbers();
                
                var messageContent = $('#messageContent').val();
                if (messageContent.includes('From Dr. <?php echo htmlspecialchars($doctorName); ?>:')) {
                    $('.template-btn').each(function() {
                        var originalTemplate = $(this).data('template');
                        var currentPersonalized = personalizeTemplate(originalTemplate);
                        
                        if (messageContent === currentPersonalized || 
                            messageContent.replace(/Dear \w+/, 'Dear Patient') === originalTemplate) {
                            $('#messageContent').val(personalizeTemplate(originalTemplate));
                            updateCharCount();
                            return false;
                        }
                    });
                }
            });

            // Template button clicks
            $('.template-btn').click(function() {
                var template = $(this).data('template');
                var personalizedTemplate = personalizeTemplate(template);
                $('#messageContent').val(personalizedTemplate);
                updateCharCount();
            });

            // Character count update
            $('#messageContent').on('input', function() {
                updateCharCount();
            });

            // Clear message button
            $('#clearMessage').click(function() {
                $('#messageContent').val('');
                updateCharCount();
            });

            // Form submission
            $('#smsForm').submit(function(e) {
                e.preventDefault();
                
                if (!smsEnabled) {
                    alert('SMS service is not available. Please contact administrator.');
                    return;
                }
                
                sendSMS();
            });

            // Functions
            function personalizeTemplate(template) {
                var checkedBoxes = $('.patient-checkbox:checked');
                
                if (checkedBoxes.length === 1) {
                    var patientName = checkedBoxes.first().data('name');
                    return template.replace('Dear Patient', 'Dear ' + patientName);
                } else {
                    return template;
                }
            }
            
            function updateSelectedNumbers() {
                var selectedNumbers = [];
                var selectedNames = [];
                
                $('.patient-checkbox:checked').each(function() {
                    selectedNumbers.push($(this).val());
                    selectedNames.push($(this).data('name'));
                });
                
                $('#selectedNumbers').val(selectedNumbers.join(', '));
                $('#selectedCount').text(selectedNumbers.length);
                
                // Check if selected count exceeds remaining units
                if (smsEnabled && selectedNumbers.length > remainingUnits) {
                    $('#selectedCount').addClass('text-red-600 font-bold');
                } else {
                    $('#selectedCount').removeClass('text-red-600 font-bold');
                }
                
                $('#sendSMS').prop('disabled', selectedNumbers.length === 0 || $('#messageContent').val().trim() === '' || !smsEnabled);
            }

            function updateCharCount() {
                var length = $('#messageContent').val().length;
                $('#charCount').text(length);
                
                if (length > 160) {
                    $('#charCount').addClass('text-red-500');
                } else {
                    $('#charCount').removeClass('text-red-500');
                }
                
                updateSelectedNumbers();
            }

            function sendSMS() {
                var selectedNumbers = [];
                $('.patient-checkbox:checked').each(function() {
                    selectedNumbers.push($(this).val());
                });
                
                if (selectedNumbers.length === 0) {
                    alert('Please select at least one patient.');
                    return;
                }
                
                if (selectedNumbers.length > remainingUnits) {
                    alert('You have only ' + remainingUnits + ' SMS units remaining. Please reduce the number of recipients or contact administrator to add more units.');
                    return;
                }
                
                var message = $('#messageContent').val().trim();
                if (message === '') {
                    alert('Please enter a message.');
                    return;
                }
                
                if (message.length > 160) {
                    alert('Message is too long. Please keep it under 160 characters.');
                    return;
                }
                
                $('#loadingModal').removeClass('hidden');
                
                $.ajax({
                    url: '../ajax/send_sms.php',
                    method: 'POST',
                    data: {
                        numbers: selectedNumbers,
                        message: message
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#loadingModal').addClass('hidden');
                        
                        if (response.success) {
                            alert('SMS sent successfully to ' + response.sent_count + ' recipients!\nRemaining units will be updated.');
                            // Reload page to update unit count
                            location.reload();
                        } else {
                            alert('Error sending SMS: ' + response.error);
                        }
                    },
                    error: function() {
                        $('#loadingModal').addClass('hidden');
                        alert('Error sending SMS. Please try again.');
                    }
                });
            }

            function loadSMSHistory() {
                $.get('../ajax/get_sms_history.php', function(data) {
                    $('#smsHistory').html(data);
                });
            }

            // Load SMS history on page load
            loadSMSHistory();
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

<?php
function formatPhoneNumber($phone) {
    if (empty($phone)) {
        return '';
    }
    
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 1) === '0') {
        $phone = '+94' . substr($phone, 1);
    }
    elseif (strlen($phone) === 9 && !str_starts_with($phone, '+94')) {
        $phone = '+94' . $phone;
    }
    elseif (str_starts_with($phone, '94') && !str_starts_with($phone, '+94')) {
        $phone = '+' . $phone;
    }
    
    return $phone;
}
?>