<?php
/**
 * AJAX Handler: Get Pending SMS Reminders
 * File: ajax/get_pending_sms.php
 * FIXED: Collation issues resolved with proper casting and binary comparison
 */

require_once __DIR__ . '/../config.php';

// Check if user is logged in (staff or doctor can access)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    // Get all pending SMS reminders using the view with explicit column selection
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
        ORDER BY 
            CASE 
                WHEN BINARY send_status = BINARY 'Overdue' THEN 1
                WHEN BINARY send_status = BINARY 'Send Today' THEN 2
                ELSE 3
            END,
            sms_send_date ASC,
            next_visit_date ASC
    ");
    $stmt->execute();
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $today = date('Y-m-d');
    $stats = [
        'total' => 0,
        'today' => 0,
        'overdue' => 0,
        'sent_today' => 0
    ];
    
    // Count reminders by status
    foreach ($reminders as $reminder) {
        $stats['total']++;
        
        $sendStatus = $reminder['send_status'] ?? '';
        
        // Use strict comparison to avoid collation issues
        if (strcmp($sendStatus, 'Send Today') === 0) {
            $stats['today']++;
        } elseif (strcmp($sendStatus, 'Overdue') === 0) {
            $stats['overdue']++;
        }
    }
    
    // Get count of SMS sent today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM next_visit_appointments 
        WHERE sms_sent = 1 
        AND DATE(sms_sent_at) = CURDATE()
    ");
    $stmt->execute();
    $sentResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['sent_today'] = intval($sentResult['count'] ?? 0);
    
    // Return response
    echo json_encode([
        'success' => true,
        'reminders' => $reminders,
        'statistics' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_pending_sms.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error while loading SMS reminders'
    ]);
} catch (Exception $e) {
    error_log("Error in get_pending_sms.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load SMS reminders'
    ]);
}
?>