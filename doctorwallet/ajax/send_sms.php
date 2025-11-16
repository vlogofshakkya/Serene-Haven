<?php
require_once '../config.php';
requireDoctor();

header('Content-Type: application/json');

try {
    $user = getCurrentUser();
    $doctorId = $user['id'];
    
    // Get doctor's SMS configuration
    $stmt = $pdo->prepare("
        SELECT dsc.*, ssi.sender_id, ssi.api_key, ssi.is_active as sender_active
        FROM doctor_sms_config dsc
        JOIN sms_sender_ids ssi ON dsc.sender_id = ssi.id
        WHERE dsc.doctor_id = ? AND dsc.is_active = 1
    ");
    $stmt->execute([$doctorId]);
    $smsConfig = $stmt->fetch();
    
    if (!$smsConfig) {
        echo json_encode(['success' => false, 'error' => 'SMS service not configured for your account. Please contact administrator.']);
        exit;
    }
    
    if (!$smsConfig['sender_active']) {
        echo json_encode(['success' => false, 'error' => 'SMS sender ID is not active. Please contact administrator.']);
        exit;
    }
    
    // Calculate remaining units
    $remainingUnits = $smsConfig['total_units'] - $smsConfig['used_units'];
    
    if ($remainingUnits <= 0) {
        echo json_encode(['success' => false, 'error' => 'You have no SMS units remaining. Please contact administrator to add more units.']);
        exit;
    }
    
    // Get POST data
    $numbers = $_POST['numbers'] ?? [];
    $message = trim($_POST['message'] ?? '');
    
    // Validate input
    if (empty($numbers) || !is_array($numbers)) {
        echo json_encode(['success' => false, 'error' => 'No phone numbers provided']);
        exit;
    }
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit;
    }
    
    if (strlen($message) > 160) {
        echo json_encode(['success' => false, 'error' => 'Message too long (max 160 characters)']);
        exit;
    }
    
    // Check if requested count exceeds remaining units
    if (count($numbers) > $remainingUnits) {
        echo json_encode([
            'success' => false, 
            'error' => 'Insufficient SMS units. You have ' . $remainingUnits . ' units remaining but trying to send to ' . count($numbers) . ' recipients.'
        ]);
        exit;
    }
    
    // Clean and validate phone numbers
    $validNumbers = [];
    foreach ($numbers as $number) {
        $cleanNumber = formatPhoneNumber($number);
        if (!empty($cleanNumber)) {
            $validNumbers[] = $cleanNumber;
        }
    }
    
    if (empty($validNumbers)) {
        echo json_encode(['success' => false, 'error' => 'No valid phone numbers found']);
        exit;
    }
    
    $sentCount = 0;
    $failedNumbers = [];
    $successNumbers = [];
    
    // Send SMS to each number using doctor's configured sender ID
    foreach ($validNumbers as $number) {
        $result = sendSMSViaTextLK($number, $message, $smsConfig['sender_id'], $smsConfig['api_key']);
        
        if ($result['success']) {
            $sentCount++;
            $successNumbers[] = $number;
            
            // Log successful SMS to database
            logSMS($doctorId, $number, $message, 'sent', $result['response']);
        } else {
            $failedNumbers[] = [
                'number' => $number,
                'error' => $result['error']
            ];
            
            // Log failed SMS to database
            logSMS($doctorId, $number, $message, 'failed', $result['error']);
        }
    }
    
    if ($sentCount > 0) {
        echo json_encode([
            'success' => true,
            'sent_count' => $sentCount,
            'total_count' => count($validNumbers),
            'failed_count' => count($failedNumbers),
            'failed_numbers' => $failedNumbers,
            'remaining_units' => $remainingUnits - $sentCount
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send SMS to any recipients',
            'failed_numbers' => $failedNumbers
        ]);
    }
    
} catch (Exception $e) {
    error_log("SMS sending error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Send SMS via text.lk API using configured sender ID
 */
function sendSMSViaTextLK($number, $message, $senderId, $apiKey) {
    $apiUrl = 'https://app.text.lk/api/v3/sms/send';
    
    try {
        // Remove + from number format for text.lk
        $cleanNumber = str_replace('+', '', $number);
        
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
                'error' => 'CURL Error: ' . $curlError
            ];
        }
        
        $result = json_decode($response, true);
        
        // Log the response for debugging
        error_log("text.lk API Response: " . $response);
        error_log("text.lk HTTP Code: " . $httpCode);
        
        if (isset($result['status']) && strtolower($result['status']) == 'success') {
            return [
                'success' => true,
                'response' => $response,
                'message_id' => $result['data']['id'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => "Failed to send SMS: $response",
                'details' => $response
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ];
    }
}

/**
 * Log SMS to database (trigger will auto-increment used_units)
 */
function logSMS($doctorId, $number, $message, $status, $response) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (doctor_id, phone_number, message, status, response, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$doctorId, $number, $message, $status, $response]);
        
    } catch (PDOException $e) {
        error_log("Error logging SMS: " . $e->getMessage());
    }
}

/**
 * Format phone number for text.lk (format: 94753379745)
 */
function formatPhoneNumber($phone) {
    if (empty($phone)) {
        return '';
    }
    
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 1) === '0') {
        $phone = '94' . substr($phone, 1);
    }
    elseif (strlen($phone) === 9 && !str_starts_with($phone, '94')) {
        $phone = '94' . $phone;
    }
    elseif (str_starts_with($phone, '+94')) {
        $phone = substr($phone, 1);
    }
    
    return $phone;
}
?>