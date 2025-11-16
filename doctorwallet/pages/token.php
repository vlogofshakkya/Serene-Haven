<?php
require_once '../config.php';
requireStaff();

$message = '';
$error = '';
date_default_timezone_set('Asia/Colombo');

// Get staff member's linked doctor
$stmt = $pdo->prepare("SELECT id, doctor_name FROM doctors WHERE staff_member_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    die('Error: No doctor linked to this staff member');
}

$doctor_id = $doctor['id'];

// Handle token creation
if ($_POST && isset($_POST['create_token'])) {
    $patient_type = $_POST['patient_type'];
    $patient_id = $_POST['patient_id'];
    
    if ($patient_id && $patient_type) {
        try {
            $pdo->beginTransaction();
            
            // Get today's date
            $today = date('Y-m-d');
            
            // Get the last token number for today
            $stmt = $pdo->prepare("SELECT MAX(CAST(token_number AS UNSIGNED)) as last_token FROM tokens WHERE doctor_id = ? AND token_date = ?");
            $stmt->execute([$doctor_id, $today]);
            $result = $stmt->fetch();
            
            $next_number = ($result && $result['last_token']) ? $result['last_token'] + 1 : 1;
            $token_number = str_pad($next_number, 4, '0', STR_PAD_LEFT);
            
            // Create token
            $stmt = $pdo->prepare("INSERT INTO tokens (token_number, patient_type, patient_id, doctor_id, staff_id, token_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$token_number, $patient_type, $patient_id, $doctor_id, $_SESSION['user_id'], $today]);
            
            $pdo->commit();
            $message = "Token #$token_number created successfully!";
            
            // Set session for printing
            $_SESSION['last_token'] = [
                'number' => $token_number,
                'patient_type' => $patient_type,
                'patient_id' => $patient_id,
                'doctor_name' => $doctor['doctor_name']
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error creating token: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select a patient';
    }
}

// Get all patients for the doctor
$adults = [];
$stmt = $pdo->prepare("SELECT id, name, phone_number FROM adults WHERE doctor_id = ? ORDER BY name");
$stmt->execute([$doctor_id]);
$adults = $stmt->fetchAll();

$kids = [];
$stmt = $pdo->prepare("SELECT k.id, k.name, a.phone_number, a.name as parent_name FROM kids k LEFT JOIN adults a ON k.parent_id = a.id WHERE k.doctor_id = ? ORDER BY k.name");
$stmt->execute([$doctor_id]);
$kids = $stmt->fetchAll();

// Get today's tokens
$stmt = $pdo->prepare("
    SELECT t.*, 
           CASE 
               WHEN t.patient_type = 'adult' THEN a.name
               WHEN t.patient_type = 'kid' THEN k.name
           END as patient_name
    FROM tokens t
    LEFT JOIN adults a ON t.patient_type = 'adult' AND t.patient_id = a.id
    LEFT JOIN kids k ON t.patient_type = 'kid' AND t.patient_id = k.id
    WHERE t.doctor_id = ? AND t.token_date = CURDATE()
    ORDER BY CAST(t.token_number AS UNSIGNED) ASC
");
$stmt->execute([$doctor_id]);
$tokens = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Management - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-content { 
                font-family: 'Arial', sans-serif;
                text-align: center;
            }
        }
        
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: none;
        }
        
        .autocomplete-suggestion {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        
        .autocomplete-suggestion:hover,
        .autocomplete-suggestion.selected {
            background-color: #f3f4f6;
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
    <nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4 no-print">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Token Management</h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm">Doctor: <?php echo htmlspecialchars($doctor['doctor_name']); ?></span>
                <a href="../index.php" class="bg-blue-800 hover:bg-blue-900 px-4 py-2 rounded">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6 no-print">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
                <?php if (isset($_SESSION['last_token'])): ?>
                    <button onclick="printToken()" class="ml-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-print mr-2"></i>Print Token
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Token Creation Form -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Create New Token</h2>
                
                <form method="POST" id="tokenForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Search Patient *</label>
                            <div class="autocomplete-container">
                                <input 
                                    type="text" 
                                    id="patient_search_input" 
                                    placeholder="Type patient name or phone..." 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    autocomplete="off"
                                />
                                <div id="patient_autocomplete_suggestions" class="autocomplete-suggestions"></div>
                            </div>
                            
                            <input type="hidden" id="selected_patient_id" name="patient_id" required />
                            <input type="hidden" id="selected_patient_type" name="patient_type" required />
                            
                            <div id="selected_patient_display" style="display: none;" class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="font-semibold text-blue-900" id="patient_display_name"></div>
                                        <div class="text-sm text-blue-700" id="patient_display_details"></div>
                                    </div>
                                    <button type="button" id="clear_patient_btn" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="create_token" 
                                class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg text-lg">
                            <i class="fas fa-plus-circle mr-2"></i>Create Token
                        </button>
                    </div>
                </form>
            </div>

            <!-- Today's Tokens -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Today's Tokens</h2>
                
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    <?php if (empty($tokens)): ?>
                        <p class="text-gray-500 text-center py-4">No tokens created today</p>
                    <?php else: ?>
                        <?php foreach ($tokens as $token): ?>
                            <div class="p-3 border rounded-lg <?php echo $token['status'] === 'waiting' ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200'; ?>">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="font-bold text-lg text-blue-600">#<?php echo $token['token_number']; ?></span>
                                        <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($token['patient_name']); ?></span>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded <?php echo $token['status'] === 'waiting' ? 'bg-yellow-200 text-yellow-800' : 'bg-gray-200 text-gray-800'; ?>">
                                        <?php echo ucfirst($token['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Token Area -->
    <div id="printArea" class="hidden print-content">
        <!-- Token content will be generated here -->
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
        const allPatients = <?php echo json_encode(array_merge(
            array_map(function($p) { return ['id' => $p['id'], 'type' => 'adult', 'name' => $p['name'], 'phone' => $p['phone_number']]; }, $adults),
            array_map(function($p) { return ['id' => $p['id'], 'type' => 'kid', 'name' => $p['name'], 'phone' => $p['phone_number'], 'parent' => $p['parent_name']]; }, $kids)
        )); ?>;

        let searchTimeout = null;
        let selectedSuggestionIndex = -1;

        $('#patient_search_input').on('input', function() {
            const searchTerm = $(this).val().trim().toLowerCase();
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            if (searchTerm.length < 1) {
                hideSuggestions();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchPatients(searchTerm);
            }, 300);
        });

        $('#patient_search_input').on('keydown', function(e) {
            const suggestions = $('#patient_autocomplete_suggestions .autocomplete-suggestion');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, suggestions.length - 1);
                updateSuggestionSelection();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
                updateSuggestionSelection();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedSuggestionIndex >= 0 && suggestions[selectedSuggestionIndex]) {
                    $(suggestions[selectedSuggestionIndex]).click();
                }
            } else if (e.key === 'Escape') {
                hideSuggestions();
            }
        });

        function searchPatients(searchTerm) {
            const filtered = allPatients.filter(p => 
                p.name.toLowerCase().includes(searchTerm) || 
                (p.phone && p.phone.includes(searchTerm)) ||
                (p.parent && p.parent.toLowerCase().includes(searchTerm))
            );
            
            displaySuggestions(filtered);
        }

        function displaySuggestions(patients) {
            const suggestionsDiv = $('#patient_autocomplete_suggestions');
            
            if (patients.length === 0) {
                suggestionsDiv.html('<div class="autocomplete-suggestion">No patients found</div>').show();
                return;
            }
            
            let html = '';
            patients.forEach(patient => {
                const badge = patient.type === 'adult' ? 
                    '<span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Adult</span>' : 
                    '<span class="ml-2 text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">Kid</span>';
                
                const parentInfo = patient.parent ? ` (Parent: ${patient.parent})` : '';
                const phoneInfo = patient.phone ? ` - ${patient.phone}` : '';
                
                html += `
                    <div class="autocomplete-suggestion" data-patient='${JSON.stringify(patient)}'>
                        ${patient.name}${badge}${phoneInfo}${parentInfo}
                    </div>
                `;
            });
            
            suggestionsDiv.html(html).show();
            selectedSuggestionIndex = -1;
            
            suggestionsDiv.find('.autocomplete-suggestion').on('click', function() {
                const patient = $(this).data('patient');
                selectPatient(patient);
            });
        }

        function updateSuggestionSelection() {
            const suggestions = $('#patient_autocomplete_suggestions .autocomplete-suggestion');
            suggestions.removeClass('selected');
            if (selectedSuggestionIndex >= 0) {
                $(suggestions[selectedSuggestionIndex]).addClass('selected');
            }
        }

        function hideSuggestions() {
            $('#patient_autocomplete_suggestions').hide();
            selectedSuggestionIndex = -1;
        }

        function selectPatient(patient) {
            $('#selected_patient_id').val(patient.id);
            $('#selected_patient_type').val(patient.type);
            $('#patient_search_input').val(patient.name);
            
            const badge = patient.type === 'adult' ? 
                '<span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Adult</span>' : 
                '<span class="ml-2 text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">Kid</span>';
            
            $('#patient_display_name').html(patient.name + badge);
            
            let details = '';
            if (patient.phone) details += `Phone: ${patient.phone}`;
            if (patient.parent) details += ` | Parent: ${patient.parent}`;
            $('#patient_display_details').text(details);
            
            $('#selected_patient_display').show();
            hideSuggestions();
        }

        $('#clear_patient_btn').on('click', function() {
            $('#selected_patient_id').val('');
            $('#selected_patient_type').val('');
            $('#patient_search_input').val('');
            $('#selected_patient_display').hide();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.autocomplete-container').length) {
                hideSuggestions();
            }
        });

        function printToken() {
            <?php if (isset($_SESSION['last_token'])): ?>
            const tokenData = <?php echo json_encode($_SESSION['last_token']); ?>;
            
            const printContent = `
                <div style="padding: 40px; text-align: center;">
                    <h1 style="font-size: 24px; margin-bottom: 20px;">Doctor Wallet</h1>
                    <h2 style="font-size: 20px; margin-bottom: 10px;">Dr. ${tokenData.doctor_name}</h2>
                    <div style="border: 3px solid #000; padding: 30px; margin: 20px 0;">
                        <h3 style="font-size: 18px; margin-bottom: 15px;">Token Number</h3>
                        <div style="font-size: 48px; font-weight: bold; margin: 20px 0;">#${tokenData.number}</div>
                    </div>
                    <p style="font-size: 16px; margin-top: 20px;">Date: ${new Date().toLocaleDateString()}</p>
                    <p style="font-size: 14px; margin-top: 10px;">Please wait for your turn</p>
                </div>
            `;
            
            $('#printArea').html(printContent).removeClass('hidden');
            window.print();
            $('#printArea').addClass('hidden');
            <?php unset($_SESSION['last_token']); ?>
            <?php endif; ?>
        }

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