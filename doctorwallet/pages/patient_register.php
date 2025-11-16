<?php
require_once '../config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['patient_logged_in']) && $_SESSION['patient_logged_in'] === true) {
    header('Location: patient_s.php');
    exit;
}

$error = '';
$success = '';
$step = 1; // Registration steps: 1=search, 2=otp, 3=complete
$selectedPatient = null;
$linkedKids = [];

// Handle AJAX patient search
if (isset($_POST['ajax']) && $_POST['ajax'] === 'search_patient') {
    header('Content-Type: application/json');
    $search = trim($_POST['search'] ?? '');
    
    if (strlen($search) < 3) {
        echo json_encode(['success' => false, 'error' => 'Please enter at least 3 characters']);
        exit;
    }
    
    // Search in adults
    $stmt = $pdo->prepare("
        SELECT 'adult' as type, a.id, a.name, a.phone_number, a.doctor_id, d.doctor_name
        FROM adults a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE (a.name LIKE ? OR a.phone_number LIKE ?)
        AND NOT EXISTS (SELECT 1 FROM patient_accounts WHERE patient_type = 'adult' AND patient_id = a.id)
        LIMIT 10
    ");
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $adults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search in kids
    $stmt = $pdo->prepare("
        SELECT 'kid' as type, k.id, k.name, a.phone_number, k.doctor_id, d.doctor_name, a.name as parent_name
        FROM kids k
        JOIN adults a ON k.parent_id = a.id
        JOIN doctors d ON k.doctor_id = d.id
        WHERE (k.name LIKE ? OR a.phone_number LIKE ?)
        AND NOT EXISTS (SELECT 1 FROM patient_accounts WHERE patient_type = 'kid' AND patient_id = k.id)
        LIMIT 10
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $kids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = array_merge($adults, $kids);
    
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        // Step 1: Patient selection
        $patientType = $_POST['patient_type'] ?? '';
        $patientId = $_POST['patient_id'] ?? '';
        
        if (empty($patientType) || empty($patientId)) {
            $error = 'Please select a patient from the search results.';
        } else {
            // Fetch patient details
            if ($patientType === 'adult') {
                $stmt = $pdo->prepare("
                    SELECT a.*, d.doctor_name, d.id as doctor_id
                    FROM adults a
                    JOIN doctors d ON a.doctor_id = d.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$patientId]);
                $selectedPatient = $stmt->fetch();
                
                if ($selectedPatient) {
                    // Check for linked kids
                    $stmt = $pdo->prepare("SELECT * FROM kids WHERE parent_id = ?");
                    $stmt->execute([$patientId]);
                    $linkedKids = $stmt->fetchAll();
                }
            } else {
                $stmt = $pdo->prepare("
                    SELECT k.*, a.name as parent_name, a.phone_number, d.doctor_name, d.id as doctor_id
                    FROM kids k
                    JOIN adults a ON k.parent_id = a.id
                    JOIN doctors d ON k.doctor_id = d.id
                    WHERE k.id = ?
                ");
                $stmt->execute([$patientId]);
                $selectedPatient = $stmt->fetch();
            }
            
            if ($selectedPatient) {
                $_SESSION['registration_patient_type'] = $patientType;
                $_SESSION['registration_patient_id'] = $patientId;
                $_SESSION['registration_patient_data'] = $selectedPatient;
                $_SESSION['registration_linked_kids'] = $linkedKids;
                $step = 2;
            } else {
                $error = 'Patient not found.';
            }
        }
    }
    
    if (isset($_POST['step']) && $_POST['step'] == '2') {
        // Step 2: Send OTP
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
            $step = 2;
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
            $step = 2;
        } else {
            // Generate OTP
            $otp = sprintf('%06d', mt_rand(0, 999999));
            
            // Store OTP and password in session
            $_SESSION['registration_otp'] = $otp;
            $_SESSION['registration_password'] = $password;
            $_SESSION['registration_otp_time'] = time();
            
            // Send OTP via SMS
            $patientData = $_SESSION['registration_patient_data'];
            $phoneNumber = $patientData['phone_number'];
            $doctorId = $patientData['doctor_id'];
            
            // Get doctor's SMS config
            $stmt = $pdo->prepare("
                SELECT dsc.*, ssi.sender_id, ssi.api_key
                FROM doctor_sms_config dsc
                JOIN sms_sender_ids ssi ON dsc.sender_id = ssi.id
                WHERE dsc.doctor_id = ? AND dsc.is_active = 1
            ");
            $stmt->execute([$doctorId]);
            $smsConfig = $stmt->fetch();
            
            if ($smsConfig && ($smsConfig['total_units'] - $smsConfig['used_units']) > 0) {
                $message = "Your Doctor Wallet OTP is: $otp. Valid for 10 minutes. Do not share this code.";
                $smsResult = sendPatientOTP($phoneNumber, $message, $smsConfig['sender_id'], $smsConfig['api_key'], $doctorId);
                
                if ($smsResult['success']) {
                    $step = 3;
                    $success = 'OTP sent to your phone number. Please check your messages.';
                } else {
                    $error = 'Failed to send OTP. Please try again or contact your doctor.';
                    $step = 2;
                }
            } else {
                $error = 'SMS service is not available. Please contact your doctor to register.';
                $step = 2;
            }
        }
    }
    
    if (isset($_POST['step']) && $_POST['step'] == '3') {
        // Step 3: Verify OTP and create account
        $enteredOTP = $_POST['otp'] ?? '';
        
        if (empty($enteredOTP)) {
            $error = 'Please enter the OTP.';
            $step = 3;
        } elseif (!isset($_SESSION['registration_otp'])) {
            $error = 'Session expired. Please start registration again.';
            $step = 1;
        } elseif ((time() - $_SESSION['registration_otp_time']) > 600) { // 10 minutes
            $error = 'OTP expired. Please request a new one.';
            unset($_SESSION['registration_otp']);
            $step = 2;
        } elseif ($enteredOTP !== $_SESSION['registration_otp']) {
            $error = 'Invalid OTP. Please try again.';
            $step = 3;
        } else {
            // OTP verified, create patient account
            try {
                $patientType = $_SESSION['registration_patient_type'];
                $patientId = $_SESSION['registration_patient_id'];
                $patientData = $_SESSION['registration_patient_data'];
                $password = $_SESSION['registration_password'];
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $phoneNumber = $patientData['phone_number'];
                $doctorId = $patientData['doctor_id'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO patient_accounts (patient_type, patient_id, doctor_id, phone_number, password, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$patientType, $patientId, $doctorId, $phoneNumber, $hashedPassword]);
                
                // Clear session registration data
                unset($_SESSION['registration_patient_type']);
                unset($_SESSION['registration_patient_id']);
                unset($_SESSION['registration_patient_data']);
                unset($_SESSION['registration_linked_kids']);
                unset($_SESSION['registration_otp']);
                unset($_SESSION['registration_password']);
                unset($_SESSION['registration_otp_time']);
                
                $success = 'Registration successful! You can now login with your phone number and password.';
                
                // Redirect to login after 2 seconds
                header("refresh:2;url=patient_login.php");
                $step = 4; // Success step
                
            } catch (PDOException $e) {
                $error = 'Registration failed. This account may already exist.';
                $step = 1;
            }
        }
    }
}

// Load session data for display
if ($step >= 2 && isset($_SESSION['registration_patient_data'])) {
    $selectedPatient = $_SESSION['registration_patient_data'];
    $linkedKids = $_SESSION['registration_linked_kids'] ?? [];
}

/**
 * Send OTP via text.lk
 */
function sendPatientOTP($number, $message, $senderId, $apiKey, $doctorId) {
    global $pdo;
    
    $cleanNumber = preg_replace('/[^0-9]/', '', $number);
    if (substr($cleanNumber, 0, 1) === '0') {
        $cleanNumber = '94' . substr($cleanNumber, 1);
    }
    
    $apiUrl = 'https://app.text.lk/api/v3/sms/send';
    
    $postData = json_encode([
        "recipient" => $cleanNumber,
        "sender_id" => $senderId,
        "type" => "plain",
        "message" => $message
    ]);
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['status']) && strtolower($result['status']) == 'success') {
        // Log SMS (will auto-increment used_units via trigger)
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (doctor_id, phone_number, message, status, response, created_at) 
            VALUES (?, ?, ?, 'sent', ?, NOW())
        ");
        $stmt->execute([$doctorId, $number, $message, $response]);
        
        return ['success' => true];
    } else {
        // Log failed SMS
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (doctor_id, phone_number, message, status, response, created_at) 
            VALUES (?, ?, ?, 'failed', ?, NOW())
        ");
        $stmt->execute([$doctorId, $number, $message, $response]);
        
        return ['success' => false, 'error' => $response];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .register-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 10px;
        }
        .step::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: 0;
        }
        .step:first-child::before {
            display: none;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }
        .step.active .step-circle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .step.completed .step-circle {
            background: #10b981;
            color: white;
        }
        .search-result {
            cursor: pointer;
            transition: all 0.3s;
        }
        .search-result:hover {
            background: #f3f4f6;
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <img src="../iconbgr_b.png" alt="Logo" class="h-20 w-auto mx-auto mb-4">
            <h1 class="text-4xl font-bold text-white mb-2">Patient Registration</h1>
            <p class="text-white text-opacity-90">Create your patient portal account</p>
        </div>

        <!-- Registration Card -->
        <div class="register-card rounded-2xl shadow-2xl p-8">
            
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-circle">
                        <?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?>
                    </div>
                    <p class="text-xs font-bold">Search Patient</p>
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-circle">
                        <?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?>
                    </div>
                    <p class="text-xs font-bold">Set Password</p>
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <div class="step-circle">
                        <?php echo $step > 3 ? '<i class="fas fa-check"></i>' : '3'; ?>
                    </div>
                    <p class="text-xs font-bold">Verify OTP</p>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <!-- Step 1: Search Patient -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-search text-blue-600 mr-2"></i>Find Your Patient Record
                </h3>
                <p class="text-gray-600 text-sm mb-4">Search by your name or phone number to find your patient record.</p>
                
                <div class="relative">
                    <input type="text" 
                           id="patientSearch" 
                           placeholder="Enter name or phone number (min 3 characters)"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div id="searchLoader" class="hidden absolute right-3 top-3">
                        <i class="fas fa-spinner fa-spin text-blue-600"></i>
                    </div>
                </div>
                
                <div id="searchResults" class="mt-4 space-y-2"></div>
                
                <form method="POST" id="selectPatientForm" class="hidden">
                    <input type="hidden" name="step" value="1">
                    <input type="hidden" name="patient_type" id="selected_patient_type">
                    <input type="hidden" name="patient_id" id="selected_patient_id">
                </form>
            </div>
            <?php endif; ?>

            <?php if ($step === 2 && $selectedPatient): ?>
            <!-- Step 2: Confirm Patient & Set Password -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-user-check text-green-600 mr-2"></i>Confirm Your Details
                </h3>
                
                <!-- Patient Details Card -->
                <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="font-bold text-lg text-blue-900 mb-2">
                                <i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($selectedPatient['name']); ?>
                            </h4>
                            <p class="text-blue-700 text-sm mb-1">
                                <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($selectedPatient['phone_number']); ?>
                            </p>
                            <p class="text-blue-700 text-sm">
                                <i class="fas fa-user-md mr-2"></i>Dr. <?php echo htmlspecialchars($selectedPatient['doctor_name']); ?>
                            </p>
                        </div>
                        <div class="bg-blue-200 p-3 rounded-full">
                            <i class="fas fa-user-circle text-2xl text-blue-600"></i>
                        </div>
                    </div>
                    
                    <?php if (!empty($linkedKids)): ?>
                    <div class="mt-4 pt-4 border-t border-blue-300">
                        <p class="font-bold text-blue-900 mb-2"><i class="fas fa-child mr-2"></i>Linked Children:</p>
                        <div class="space-y-1">
                            <?php foreach ($linkedKids as $kid): ?>
                            <p class="text-sm text-blue-700">• <?php echo htmlspecialchars($kid['name']); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Password Form -->
                <form method="POST">
                    <input type="hidden" name="step" value="2">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-lock mr-2 text-blue-600"></i>Create Password
                        </label>
                        <input type="password" 
                               name="password" 
                               required 
                               minlength="6"
                               placeholder="Enter password (min 6 characters)"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-lock mr-2 text-blue-600"></i>Confirm Password
                        </label>
                        <input type="password" 
                               name="confirm_password" 
                               required 
                               minlength="6"
                               placeholder="Re-enter password"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            An OTP will be sent to <strong><?php echo htmlspecialchars($selectedPatient['phone_number']); ?></strong> for verification.
                        </p>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 rounded-lg font-bold hover:from-blue-700 hover:to-purple-700 transition duration-300 shadow-lg">
                        <i class="fas fa-paper-plane mr-2"></i>Send OTP
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($step === 3): ?>
            <!-- Step 3: OTP Verification -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-mobile-alt text-purple-600 mr-2"></i>Verify Your Phone Number
                </h3>
                
                <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-4 mb-6 text-center">
                    <i class="fas fa-sms text-4xl text-purple-600 mb-3"></i>
                    <p class="text-gray-700 mb-2">We've sent a 6-digit OTP to:</p>
                    <p class="font-bold text-lg text-purple-900"><?php echo htmlspecialchars($selectedPatient['phone_number']); ?></p>
                    <p class="text-sm text-gray-600 mt-2">Please enter the code to complete registration</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="step" value="3">
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2 text-center">
                            Enter OTP Code
                        </label>
                        <input type="text" 
                               name="otp" 
                               required 
                               maxlength="6"
                               pattern="[0-9]{6}"
                               placeholder="000000"
                               class="w-full px-4 py-4 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-center text-2xl font-bold tracking-widest"
                               autofocus>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-3 rounded-lg font-bold hover:from-purple-700 hover:to-pink-700 transition duration-300 shadow-lg">
                        <i class="fas fa-check-circle mr-2"></i>Verify & Complete Registration
                    </button>
                </form>
                
                <div class="mt-4 text-center">
                    <p class="text-gray-600 text-sm">Didn't receive the code?</p>
                    <button onclick="window.location.reload()" class="text-blue-600 hover:text-blue-800 font-bold text-sm mt-2">
                        <i class="fas fa-redo mr-1"></i>Resend OTP
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($step === 4): ?>
            <!-- Step 4: Success -->
            <div class="text-center py-8">
                <div class="bg-green-100 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check-circle text-5xl text-green-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-4">Registration Successful!</h3>
                <p class="text-gray-600 mb-6">Your patient portal account has been created successfully.</p>
                <p class="text-sm text-gray-500">Redirecting to login page...</p>
            </div>
            <?php endif; ?>

            <!-- Back to Login -->
            <?php if ($step < 4): ?>
            <div class="mt-6 text-center pt-6 border-t border-gray-200">
                <p class="text-gray-600 text-sm">Already have an account?</p>
                <a href="patient_login.php" class="text-blue-600 hover:text-blue-800 font-bold text-sm mt-2 inline-block">
                    <i class="fas fa-sign-in-alt mr-1"></i>Sign In
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        let searchTimeout;
        
        $('#patientSearch').on('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val().trim();
            
            if (searchTerm.length < 3) {
                $('#searchResults').html('');
                return;
            }
            
            $('#searchLoader').removeClass('hidden');
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: 'patient_register.php',
                    method: 'POST',
                    data: {
                        ajax: 'search_patient',
                        search: searchTerm
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#searchLoader').addClass('hidden');
                        
                        if (response.success && response.results.length > 0) {
                            let html = '<div class="border border-gray-300 rounded-lg overflow-hidden">';
                            
                            response.results.forEach(function(patient) {
                                html += `
                                    <div class="search-result p-4 border-b border-gray-200 last:border-b-0" 
                                         data-type="${patient.type}" 
                                         data-id="${patient.id}">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <p class="font-bold text-gray-800">
                                                    <i class="fas fa-user mr-2 text-blue-600"></i>
                                                    ${patient.name}
                                                    ${patient.type === 'kid' ? '<span class="text-xs bg-pink-200 text-pink-800 px-2 py-1 rounded ml-2">Child</span>' : ''}
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-phone mr-2"></i>${patient.phone_number}
                                                </p>
                                                ${patient.parent_name ? `<p class="text-sm text-gray-600"><i class="fas fa-user-friends mr-2"></i>Parent: ${patient.parent_name}</p>` : ''}
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-user-md mr-2"></i>Dr. ${patient.doctor_name}
                                                </p>
                                            </div>
                                            <div>
                                                <i class="fas fa-chevron-right text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            html += '</div>';
                            $('#searchResults').html(html);
                            
                            // Add click handlers
                            $('.search-result').on('click', function() {
                                const type = $(this).data('type');
                                const id = $(this).data('id');
                                
                                $('#selected_patient_type').val(type);
                                $('#selected_patient_id').val(id);
                                $('#selectPatientForm').submit();
                            });
                            
                        } else {
                            $('#searchResults').html(`
                                <div class="text-center p-6 bg-gray-50 rounded-lg">
                                    <i class="fas fa-search text-4xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600">No patients found matching your search.</p>
                                    <p class="text-sm text-gray-500 mt-2">Please check your spelling or contact your doctor's clinic.</p>
                                </div>
                            `);
                        }
                    },
                    error: function() {
                        $('#searchLoader').addClass('hidden');
                        $('#searchResults').html(`
                            <div class="text-center p-6 bg-red-50 rounded-lg">
                                <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-3"></i>
                                <p class="text-red-600">Error searching. Please try again.</p>
                            </div>
                        `);
                    }
                });
            }, 500);
        });
    });
    </script>
</body>
</html>