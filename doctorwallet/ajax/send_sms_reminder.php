<?php
/**
 * AJAX Handler: Send SMS Reminders
 * File: ajax/send_sms_reminder.php
 * FIXED: Collation issues resolved with proper casting and collation handling
 */

require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

/**
 * Format phone number for text.lk API
 */
function formatPhoneNumber($phone) {
    if (empty($phone)) {
        return '';
    }
    
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 1) === '0') {
        $phone = '94' . substr($phone, 1);
    } elseif (strlen($phone) === 9) {
        $phone = '94' . $phone;
    } elseif (substr($phone, 0, 2) === '94' && strlen($phone) === 11) {
        $phone = $phone;
    } elseif (substr($phone, 0, 3) === '+94') {
        $phone = substr($phone, 1);
    }
    
    return $phone;
}

/**
 * Send SMS via text.lk API
 */
function sendSMSViaTextLK($number, $message, $senderId, $apiKey) {
    $apiUrl = 'https://app.text.lk/api/v3/sms/send';
    
    try {
        $cleanNumber = formatPhoneNumber($number);
        
        if (empty($cleanNumber) || strlen($cleanNumber) < 11) {
            return [
                'success' => false,
                'error' => 'Invalid phone number format'
            ];
        }
        
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
                'error' => 'Network error: ' . $curlError
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
                'error' => "API Error (HTTP $httpCode): " . ($result['message'] ?? $response)
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
function logSMSToDB($pdo, $doctorId, $number, $message, $status, $response) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (doctor_id, phone_number, message, status, response, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$doctorId, $number, $message, $status, $response]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging SMS: " . $e->getMessage());
        return false;
    }
}

try {
    $sendType = $_POST['send_type'] ?? 'single';
    $appointmentIds = $_POST['appointment_ids'] ?? [];
    
    // Get appointments to process
    if ($sendType === 'today') {
        // Get today's appointments - COLLATION FIX APPLIED
        $stmt = $pdo->prepare("
            SELECT 
                appointment_id,
                doctor_id,
                patient_type,
                patient_id,
                receipt_id,
                next_visit_date,
                reminder_days_before,
                sms_send_date,
                sms_sent,
                sms_sent_at,
                status,
                notes,
                created_at,
                doctor_name,
                doctor_phone,
                patient_name,
                phone_number,
                nic_number,
                total_units,
                used_units,
                remaining_units,
                auto_send_reminders,
                config_active,
                sender_id,
                api_key,
                days_until_visit,
                send_status
            FROM sms_pending_today 
            WHERE BINARY send_status = BINARY 'Send Today'
            ORDER BY next_visit_date ASC
        ");
        $stmt->execute();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($sendType === 'all') {
        // Get all pending appointments (today + overdue) - COLLATION FIX APPLIED
        $stmt = $pdo->prepare("
            SELECT 
                appointment_id,
                doctor_id,
                patient_type,
                patient_id,
                receipt_id,
                next_visit_date,
                reminder_days_before,
                sms_send_date,
                sms_sent,
                sms_sent_at,
                status,
                notes,
                created_at,
                doctor_name,
                doctor_phone,
                patient_name,
                phone_number,
                nic_number,
                total_units,
                used_units,
                remaining_units,
                auto_send_reminders,
                config_active,
                sender_id,
                api_key,
                days_until_visit,
                send_status
            FROM sms_pending_today 
            WHERE BINARY send_status IN (BINARY 'Send Today', BINARY 'Overdue')
            ORDER BY 
                CASE 
                    WHEN BINARY send_status = BINARY 'Overdue' THEN 1 
                    ELSE 2 
                END,
                next_visit_date ASC
        ");
        $stmt->execute();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif (!empty($appointmentIds)) {
        // Get specific appointments - COLLATION FIX APPLIED
        $placeholders = str_repeat('?,', count($appointmentIds) - 1) . '?';
        
        // Convert appointment IDs to integers for safe comparison
        $appointmentIds = array_map('intval', $appointmentIds);
        
        $stmt = $pdo->prepare("
            SELECT 
                appointment_id,
                doctor_id,
                patient_type,
                patient_id,
                receipt_id,
                next_visit_date,
                reminder_days_before,
                sms_send_date,
                sms_sent,
                sms_sent_at,
                status,
                notes,
                created_at,
                doctor_name,
                doctor_phone,
                patient_name,
                phone_number,
                nic_number,
                total_units,
                used_units,
                remaining_units,
                auto_send_reminders,
                config_active,
                sender_id,
                api_key,
                days_until_visit,
                send_status
            FROM sms_pending_today 
            WHERE appointment_id IN ($placeholders)
        ");
        $stmt->execute($appointmentIds);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'No appointments specified']);
        exit;
    }
    
    if (empty($appointments)) {
        echo json_encode(['success' => false, 'error' => 'No appointments found to process']);
        exit;
    }
    
    // Process each appointment
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    foreach ($appointments as $appt) {
        $appointmentId = $appt['appointment_id'];
        $patientName = $appt['patient_name'] ?? 'Unknown Patient';
        $phoneNumber = $appt['phone_number'] ?? '';
        $doctorName = $appt['doctor_name'] ?? 'Doctor';
        
        // Validation checks
        $result = [
            'appointment_id' => $appointmentId,
            'patient_name' => $patientName,
            'status' => 'failed',
            'error' => null
        ];
        
        // Check if SMS config exists
        if (empty($appt['config_active']) || $appt['config_active'] != 1) {
            $result['error'] = 'No SMS configuration';
            $results[] = $result;
            $failCount++;
            
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET status = 'sms_failed', 
                    notes = CONCAT(COALESCE(notes, ''), ' | SMS failed: No SMS configuration found'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$appointmentId]);
            continue;
        }
        
        // Check if auto-send is enabled
        if (empty($appt['auto_send_reminders']) || $appt['auto_send_reminders'] != 1) {
            $result['error'] = 'Auto-send disabled for this doctor';
            $results[] = $result;
            $failCount++;
            continue;
        }
        
        // Check SMS units
        if (!isset($appt['remaining_units']) || $appt['remaining_units'] <= 0) {
            $result['error'] = 'No SMS units remaining';
            $results[] = $result;
            $failCount++;
            
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET status = 'sms_failed', 
                    notes = CONCAT(COALESCE(notes, ''), ' | SMS failed: Insufficient SMS units'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$appointmentId]);
            continue;
        }
        
        // Check phone number
        if (empty($phoneNumber)) {
            $result['error'] = 'No phone number available';
            $results[] = $result;
            $failCount++;
            
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET status = 'sms_failed', 
                    notes = CONCAT(COALESCE(notes, ''), ' | SMS failed: No phone number available'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$appointmentId]);
            continue;
        }
        
        // Check API credentials
        if (empty($appt['sender_id']) || empty($appt['api_key'])) {
            $result['error'] = 'Incomplete SMS configuration (missing sender ID or API key)';
            $results[] = $result;
            $failCount++;
            continue;
        }
        
        // Format SMS message
        $visitDate = date('d M Y', strtotime($appt['next_visit_date']));
        $message = "Dr. {$doctorName}, Dear {$patientName}, Clinic visit date is {$visitDate}. Please visit the clinic and get your consultation & medicines.";
        
        // Send SMS
        $smsResult = sendSMSViaTextLK(
            $phoneNumber, 
            $message, 
            $appt['sender_id'], 
            $appt['api_key']
        );
        
        if ($smsResult['success']) {
            // Mark as sent
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET sms_sent = 1, 
                    sms_sent_at = NOW(),
                    status = 'scheduled',
                    notes = CONCAT(COALESCE(notes, ''), ' | SMS sent successfully by staff on ', NOW()),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$appointmentId]);
            
            // Log SMS (this triggers the increment_sms_units trigger)
            logSMSToDB($pdo, $appt['doctor_id'], $phoneNumber, $message, 'sent', $smsResult['response']);
            
            $result['status'] = 'sent';
            $result['message_id'] = $smsResult['message_id'] ?? null;
            $successCount++;
            
        } else {
            // Mark as failed
            $errorMsg = $smsResult['error'] ?? 'Unknown error';
            $updateStmt = $pdo->prepare("
                UPDATE next_visit_appointments 
                SET status = 'sms_failed',
                    notes = CONCAT(COALESCE(notes, ''), ' | SMS failed: ', ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$errorMsg, $appointmentId]);
            
            // Log failed SMS
            logSMSToDB($pdo, $appt['doctor_id'], $phoneNumber, $message, 'failed', $errorMsg);
            
            $result['error'] = $errorMsg;
            $failCount++;
        }
        
        $results[] = $result;
        
        // Small delay to avoid rate limiting (0.5 seconds)
        usleep(500000);
    }
    
    // Calculate summary
    $summary = [
        'total' => count($results),
        'sent' => $successCount,
        'failed' => $failCount
    ];
    
    // Generate success message
    $message = "Processed {$summary['total']} SMS reminder(s). ";
    $message .= "✓ Sent: {$summary['sent']}";
    if ($summary['failed'] > 0) {
        $message .= " | ✗ Failed: {$summary['failed']}";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'results' => $results,
        'summary' => $summary
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in send_sms_reminder.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in send_sms_reminder.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send SMS: ' . $e->getMessage()
    ]);
}
?>