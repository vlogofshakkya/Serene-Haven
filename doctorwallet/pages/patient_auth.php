<?php
/**
 * Patient Authentication Helper Functions
 * Add these functions to your config.php or create a separate include file
 */

/**
 * Check if patient is logged in, redirect to login if not
 */
function requirePatientLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
        header('Location: patient_login.php');
        exit;
    }
}

/**
 * Get current logged-in patient data
 */
function getCurrentPatient() {
    global $pdo;
    
    if (!isset($_SESSION['patient_id']) || !isset($_SESSION['patient_type'])) {
        return null;
    }
    
    $patientId = $_SESSION['patient_id'];
    $patientType = $_SESSION['patient_type'];
    
    if ($patientType === 'adult') {
        $stmt = $pdo->prepare("SELECT * FROM adults WHERE id = ?");
        $stmt->execute([$patientId]);
        return $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("
            SELECT k.*, a.name as parent_name, a.phone_number as parent_phone
            FROM kids k
            JOIN adults a ON k.parent_id = a.id
            WHERE k.id = ?
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetch();
    }
}

/**
 * Format phone number for Sri Lankan numbers
 */
function formatSriLankanPhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to international format (94xxxxxxxxx)
    if (substr($phone, 0, 1) === '0') {
        $phone = '94' . substr($phone, 1);
    } elseif (strlen($phone) === 9 && !str_starts_with($phone, '94')) {
        $phone = '94' . $phone;
    } elseif (str_starts_with($phone, '+94')) {
        $phone = substr($phone, 1);
    }
    
    return $phone;
}

/**
 * Send SMS via text.lk API
 */
function sendSMS($phoneNumber, $message, $senderId, $apiKey, $doctorId) {
    global $pdo;
    
    $cleanNumber = formatSriLankanPhone($phoneNumber);
    
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
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'Connection error: ' . $curlError
        ];
    }
    
    $result = json_decode($response, true);
    
    // Log SMS
    $status = (isset($result['status']) && strtolower($result['status']) == 'success') ? 'sent' : 'failed';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (doctor_id, phone_number, message, status, response, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$doctorId, $phoneNumber, $message, $status, $response]);
    } catch (PDOException $e) {
        error_log("Error logging SMS: " . $e->getMessage());
    }
    
    if ($status === 'sent') {
        return [
            'success' => true,
            'response' => $response,
            'message_id' => $result['data']['id'] ?? null
        ];
    } else {
        return [
            'success' => false,
            'error' => $response,
            'http_code' => $httpCode
        ];
    }
}

/**
 * Generate random OTP
 */
function generateOTP($length = 6) {
    return sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
}

/**
 * Validate OTP (check expiry and match)
 */
function validateOTP($enteredOTP, $storedOTP, $otpTime, $expiryMinutes = 10) {
    if (empty($enteredOTP) || empty($storedOTP)) {
        return ['valid' => false, 'error' => 'OTP is required'];
    }
    
    if ((time() - $otpTime) > ($expiryMinutes * 60)) {
        return ['valid' => false, 'error' => 'OTP has expired'];
    }
    
    if ($enteredOTP !== $storedOTP) {
        return ['valid' => false, 'error' => 'Invalid OTP'];
    }
    
    return ['valid' => true];
}

/**
 * Get doctor's active SMS configuration
 */
function getDoctorSMSConfig($doctorId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT dsc.*, ssi.sender_id, ssi.api_key, ssi.is_active as sender_active
        FROM doctor_sms_config dsc
        JOIN sms_sender_ids ssi ON dsc.sender_id = ssi.id
        WHERE dsc.doctor_id = ? AND dsc.is_active = 1
    ");
    $stmt->execute([$doctorId]);
    return $stmt->fetch();
}

/**
 * Check if patient account already exists
 */
function patientAccountExists($patientType, $patientId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM patient_accounts 
        WHERE patient_type = ? AND patient_id = ?
    ");
    $stmt->execute([$patientType, $patientId]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

/**
 * Calculate days until appointment
 */
function daysUntilDate($date) {
    $targetDate = new DateTime($date);
    $today = new DateTime();
    $interval = $today->diff($targetDate);
    
    if ($targetDate < $today) {
        return -($interval->days); // Negative for overdue
    }
    
    return $interval->days;
}

/**
 * Format date for display
 */
function formatDisplayDate($date) {
    $dateObj = new DateTime($date);
    return $dateObj->format('F d, Y');
}

/**
 * Sanitize patient input
 */
function sanitizePatientInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Log patient activity
 */
function logPatientActivity($patientAccountId, $activity, $details = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO patient_activity_log (patient_account_id, activity, details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$patientAccountId, $activity, $details]);
    } catch (PDOException $e) {
        error_log("Error logging patient activity: " . $e->getMessage());
    }
}

/**
 * Get patient statistics
 */
function getPatientStats($patientType, $patientId, $doctorId) {
    global $pdo;
    
    // Lab reports count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM lab_reports 
        WHERE patient_type = ? AND patient_id = ? AND doctor_id = ?
    ");
    $stmt->execute([$patientType, $patientId, $doctorId]);
    $labReports = $stmt->fetch()['count'];
    
    // Appointments count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM next_visit_appointments 
        WHERE patient_type = ? AND patient_id = ? AND doctor_id = ? AND status = 'scheduled'
    ");
    $stmt->execute([$patientType, $patientId, $doctorId]);
    $appointments = $stmt->fetch()['count'];
    
    // Prescriptions count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM e_receipts 
        WHERE patient_type = ? AND patient_id = ? AND doctor_id = ?
    ");
    $stmt->execute([$patientType, $patientId, $doctorId]);
    $prescriptions = $stmt->fetch()['count'];
    
    // Total amount spent
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total FROM e_receipts 
        WHERE patient_type = ? AND patient_id = ? AND doctor_id = ?
    ");
    $stmt->execute([$patientType, $patientId, $doctorId]);
    $totalSpent = $stmt->fetch()['total'];
    
    return [
        'lab_reports' => $labReports,
        'appointments' => $appointments,
        'prescriptions' => $prescriptions,
        'total_spent' => $totalSpent
    ];
}
?>