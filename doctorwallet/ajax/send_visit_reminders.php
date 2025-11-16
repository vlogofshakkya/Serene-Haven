<?php
/**
 * AJAX Script: Send Next Visit SMS Reminders (Manual Trigger)
 * File: ajax/send_visit_reminders.php
 */

require_once __DIR__ . '/../config.php';

// Check if user is logged in as doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$logFile = __DIR__ . '/../logs/sms_reminder_log.txt';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    error_log($message);
}

try {
    $doctorId = $_SESSION['user_id'];
    logMessage("=== SMS Reminder Process Started by Doctor ID: $doctorId ===");
    
    // Get appointments that need reminders for this doctor
    $stmt = $pdo->prepare("
        SELECT * FROM pending_sms_reminders 
        WHERE doctor_id = ?
        ORDER BY reminder_send_date ASC
    ");
    $stmt->execute([$doctorId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($appointments) . " appointments needing reminders");
    
    $results = [
        'success' => true,
        'total' => count($appointments),
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
        'details' => []
    ];
    
    foreach ($appointments as $appt) {
        $appointmentId = $appt['id'];
        $patientName = $appt['patient_name'];
        $phoneNumber = $appt['phone_number'];
        
        logMessage("Processing appointment ID: $appointmentId for $patientName");
        
        // Check if auto-send is enabled
        if (!$appt['auto_send_reminders']) {
            logMessage("Skipped: Auto-send disabled");
            $results['skipped']++;
            $results['details'][] = [
                'appointment_id' => $appointmentId,
                'patient' => $patientName,
                'status' => 'skipped',
                'reason' => 'Auto-send disabled'
            ];
            continue;
        }
        
        // Check SMS units
        $remainingUnits = $appt['remaining_units'];
        if ($remainingUnits <= 0) {
            logMessage("Skipped: No SMS units");
            
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET status = 'sms_failed', 
                    notes = 'Insufficient SMS units',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$appointmentId]);
            
            $results['skipped']++;
            $results['details'][] = [
                'appointment_id' => $appointmentId,
                'patient' => $patientName,
                'status' => 'skipped',
                'reason' => 'No SMS units remaining'
            ];
            continue;
        }
        
        // Validate phone number
        if (empty($phoneNumber)) {
            logMessage("Skipped: No phone number");
            $results['skipped']++;
            $results['details'][] = [
                'appointment_id' => $appointmentId,
                'patient' => $patientName,
                'status' => 'skipped',
                'reason' => 'No phone number'
            ];
            continue;
        }
        
        // Format SMS message
        $visitDateFormatted = date('d M Y', strtotime($appt['next_visit_date']));
        $message = "Dr. {$appt['doctor_name']}, Dear {$patientName}, Clinic visit date is {$visitDateFormatted}. Please visit the clinic and get your consultation & medicines.";
        
        logMessage("Sending SMS to $phoneNumber");
        
        // Send SMS
        $smsResult = sendSMSViaTextLK(
            $phoneNumber, 
            $message, 
            $appt['sender_id'], 
            $appt['api_key']
        );
        
        if ($smsResult['success']) {
            // Mark SMS as sent
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET sms_sent = 1, 
                    sms_sent_at = NOW(),
                    status = 'scheduled',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$appointmentId]);
            
            // Log SMS (trigger will auto-increment used_units)
            logSMS($pdo, $appt['doctor_id'], $phoneNumber, $message, 'sent', $smsResult['response']);
            
            logMessage("SUCCESS: SMS sent to $patientName");
            $results['sent']++;
            $results['details'][] = [
                'appointment_id' => $appointmentId,
                'patient' => $patientName,
                'phone' => $phoneNumber,
                'status' => 'sent',
                'message_id' => $smsResult['message_id'] ?? null
            ];
            
        } else {
            // Mark as failed
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET status = 'sms_failed',
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$smsResult['error'], $appointmentId]);
            
            // Log failed SMS
            logSMS($pdo, $appt['doctor_id'], $phoneNumber, $message, 'failed', $smsResult['error']);
            
            logMessage("FAILED: " . $smsResult['error']);
            $results['failed']++;
            $results['details'][] = [
                'appointment_id' => $appointmentId,
                'patient' => $patientName,
                'phone' => $phoneNumber,
                'status' => 'failed',
                'error' => $smsResult['error']
            ];
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 seconds
    }
    
    $results['message'] = "Sent: {$results['sent']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}";
    logMessage("Summary: " . $results['message']);
    logMessage("=== SMS Reminder Process Completed ===\n");
    
    echo json_encode($results);
    
} catch (Exception $e) {
    $error = "ERROR: " . $e->getMessage();
    logMessage($error);
    logMessage("=== SMS Reminder Process Failed ===\n");
    
    echo json_encode([
        'success' => false,
        'error' => $error
    ]);
}

/**
 * Send SMS via text.lk API
 */
function sendSMSViaTextLK($number, $message, $senderId, $apiKey) {
    $apiUrl = 'https://app.text.lk/api/v3/sms/send';
    
    try {
        $cleanNumber = formatPhoneNumber($number);
        $cleanNumber = str_replace('+', '', $cleanNumber);
        
        logMessage("Formatted number: $cleanNumber");
        
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
        
        logMessage("API Response (HTTP $httpCode): $response");
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $curlError
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode == 200 && isset($result['status']) && strtolower($result['status']) == 'success') {
            return [
                'success' => true,
                'response' => $response,
                'message_id' => $result['data']['id'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => "API Error (HTTP $httpCode): " . ($result['message'] ?? $response),
                'http_code' => $httpCode
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
 * Log SMS to database
 */
function logSMS($pdo, $doctorId, $number, $message, $status, $response) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (doctor_id, phone_number, message, status, response, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$doctorId, $number, $message, $status, $response]);
    } catch (PDOException $e) {
        logMessage("Error logging SMS: " . $e->getMessage());
    }
}

/**
 * Format phone number for text.lk (format: 94XXXXXXXXX without +)
 */
function formatPhoneNumber($phone) {
    if (empty($phone)) {
        return '';
    }
    
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle different formats
    if (substr($phone, 0, 1) === '0') {
        // 0771234567 -> 94771234567
        $phone = '94' . substr($phone, 1);
    } elseif (strlen($phone) === 9) {
        // 771234567 -> 94771234567
        $phone = '94' . $phone;
    } elseif (substr($phone, 0, 2) === '94' && strlen($phone) === 11) {
        // Already in correct format: 94771234567
        $phone = $phone;
    } elseif (substr($phone, 0, 3) === '+94') {
        // +94771234567 -> 94771234567
        $phone = substr($phone, 1);
    }
    
    return $phone;
}
?>