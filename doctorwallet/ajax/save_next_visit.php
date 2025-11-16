<?php
/**
 * AJAX Handler: Save Next Visit Appointment
 * File: ajax/save_next_visit.php
 */

require_once __DIR__ . '/../config.php';

// Check if user is logged in as doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $doctorId = $_SESSION['user_id'];
    
    // Get POST data
    $patientType = trim($_POST['patient_type'] ?? '');
    $patientId = intval($_POST['patient_id'] ?? 0);
    $nextVisitDate = trim($_POST['next_visit_date'] ?? '');
    $receiptId = !empty($_POST['receipt_id']) ? intval($_POST['receipt_id']) : null;
    $reminderDaysBefore = intval($_POST['reminder_days_before'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate required fields
    if (empty($patientType) || $patientId <= 0 || empty($nextVisitDate)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields',
            'debug' => [
                'patientType' => $patientType,
                'patientId' => $patientId,
                'nextVisitDate' => $nextVisitDate
            ]
        ]);
        exit;
    }
    
    // Validate patient type
    if (!in_array($patientType, ['adult', 'kid'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid patient type']);
        exit;
    }
    
    // Validate date format and future date
    $dateObj = DateTime::createFromFormat('Y-m-d', $nextVisitDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $nextVisitDate) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }
    
    $today = new DateTime('today');
    if ($dateObj <= $today) {
        echo json_encode(['success' => false, 'error' => 'Next visit date must be in the future']);
        exit;
    }
    
    // Validate reminder days
    if ($reminderDaysBefore < 0 || $reminderDaysBefore > 30) {
        echo json_encode(['success' => false, 'error' => 'Reminder days must be between 0 and 30']);
        exit;
    }
    
    // Verify patient exists
    $patientTable = ($patientType === 'adult') ? 'adults' : 'kids';
    $stmt = $pdo->prepare("SELECT id, name FROM {$patientTable} WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$patientId, $doctorId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        echo json_encode(['success' => false, 'error' => 'Patient not found']);
        exit;
    }
    
    // Check for existing appointment on the same date
    $stmt = $pdo->prepare("
        SELECT id FROM next_visit_appointments 
        WHERE doctor_id = ? 
        AND patient_type = ? 
        AND patient_id = ? 
        AND next_visit_date = ?
        AND status IN ('scheduled', 'sms_failed')
    ");
    $stmt->execute([$doctorId, $patientType, $patientId, $nextVisitDate]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing appointment instead of creating duplicate
        $stmt = $pdo->prepare("
            UPDATE next_visit_appointments 
            SET receipt_id = ?,
                reminder_days_before = ?,
                notes = ?,
                sms_sent = 0,
                sms_sent_at = NULL,
                status = 'scheduled',
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$receiptId, $reminderDaysBefore, $notes, $existing['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Existing appointment updated successfully',
            'appointment_id' => $existing['id'],
            'next_visit_date' => $nextVisitDate,
            'updated' => true
        ]);
        exit;
    }
    
    // Insert new appointment
    $stmt = $pdo->prepare("
        INSERT INTO next_visit_appointments 
        (doctor_id, patient_type, patient_id, receipt_id, next_visit_date, reminder_days_before, notes, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    
    $result = $stmt->execute([
        $doctorId,
        $patientType,
        $patientId,
        $receiptId,
        $nextVisitDate,
        $reminderDaysBefore,
        $notes
    ]);
    
    if ($result) {
        $appointmentId = $pdo->lastInsertId();
        
        // Check SMS configuration
        $stmt = $pdo->prepare("
            SELECT dsc.*, 
                   (dsc.total_units - dsc.used_units) as remaining_units
            FROM doctor_sms_config dsc
            WHERE dsc.doctor_id = ? AND dsc.is_active = 1
        ");
        $stmt->execute([$doctorId]);
        $smsConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $smsStatus = 'disabled';
        if ($smsConfig) {
            if ($smsConfig['remaining_units'] > 0 && $smsConfig['auto_send_reminders']) {
                $smsStatus = 'enabled';
            } elseif ($smsConfig['remaining_units'] <= 0) {
                $smsStatus = 'no_units';
            } elseif (!$smsConfig['auto_send_reminders']) {
                $smsStatus = 'auto_disabled';
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Next visit appointment scheduled successfully',
            'appointment_id' => $appointmentId,
            'next_visit_date' => $nextVisitDate,
            'patient_name' => $patient['name'],
            'reminder_days_before' => $reminderDaysBefore,
            'sms_status' => $smsStatus,
            'remaining_units' => $smsConfig['remaining_units'] ?? 0,
            'updated' => false
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save appointment']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in save_next_visit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in save_next_visit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}
?>