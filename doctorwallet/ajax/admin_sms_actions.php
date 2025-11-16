<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_sender':
            addSenderID();
            break;
        
        case 'link_doctor':
            linkDoctor();
            break;
        
        case 'update_units':
            updateUnits();
            break;
        
        case 'toggle_sender_status':
            toggleSenderStatus();
            break;
        
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Admin SMS action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function addSenderID() {
    global $pdo;
    
    $senderId = trim($_POST['sender_id'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($senderId) || empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Sender ID and API Key are required']);
        return;
    }
    
    // Check if sender ID already exists
    $stmt = $pdo->prepare("SELECT id FROM sms_sender_ids WHERE sender_id = ?");
    $stmt->execute([$senderId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Sender ID already exists']);
        return;
    }
    
    // Insert new sender ID
    $stmt = $pdo->prepare("
        INSERT INTO sms_sender_ids (sender_id, api_key, description) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$senderId, $apiKey, $description]);
    
    echo json_encode(['success' => true, 'message' => 'Sender ID added successfully']);
}

function linkDoctor() {
    global $pdo;
    
    $doctorId = intval($_POST['doctor_id'] ?? 0);
    $senderId = intval($_POST['sender_id'] ?? 0);
    $totalUnits = intval($_POST['total_units'] ?? 0);
    
    if ($doctorId <= 0 || $senderId <= 0 || $totalUnits <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid input data']);
        return;
    }
    
    // Check if doctor already has a configuration
    $stmt = $pdo->prepare("SELECT id FROM doctor_sms_config WHERE doctor_id = ?");
    $stmt->execute([$doctorId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Doctor is already linked to a sender ID. Please update the existing configuration.']);
        return;
    }
    
    // Insert new configuration
    $stmt = $pdo->prepare("
        INSERT INTO doctor_sms_config (doctor_id, sender_id, total_units, used_units) 
        VALUES (?, ?, ?, 0)
    ");
    $stmt->execute([$doctorId, $senderId, $totalUnits]);
    
    echo json_encode(['success' => true, 'message' => 'Doctor linked successfully']);
}

function updateUnits() {
    global $pdo;
    
    $configId = intval($_POST['config_id'] ?? 0);
    $updateType = $_POST['update_type'] ?? '';
    $unitsValue = isset($_POST['units_value']) && $_POST['units_value'] !== '' ? intval($_POST['units_value']) : null;
    
    if ($configId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid configuration ID']);
        return;
    }
    
    if (empty($updateType) || $unitsValue === null || $unitsValue < 0) {
        echo json_encode(['success' => false, 'error' => 'Please provide valid units value']);
        return;
    }
    
    // Get current configuration
    $stmt = $pdo->prepare("SELECT total_units, used_units FROM doctor_sms_config WHERE id = ?");
    $stmt->execute([$configId]);
    $config = $stmt->fetch();
    
    if (!$config) {
        echo json_encode(['success' => false, 'error' => 'Configuration not found']);
        return;
    }
    
    $finalTotalUnits = $config['total_units'];
    $finalUsedUnits = $config['used_units'];
    
    if ($updateType === 'set_total') {
        // Set new total units directly (keep used units same)
        if ($unitsValue < $config['used_units']) {
            echo json_encode(['success' => false, 'error' => 'New total units cannot be less than used units (' . $config['used_units'] . ')']);
            return;
        }
        $finalTotalUnits = $unitsValue;
        // Used units stay the same
    } elseif ($updateType === 'add_units') {
        // Subtract from used units (credit back)
        if ($unitsValue > 0) {
            $finalUsedUnits = $config['used_units'] - $unitsValue;
            
            // Make sure used units don't go negative
            if ($finalUsedUnits < 0) {
                $finalUsedUnits = 0;
            }
            
            // Total units stay the same
            $finalTotalUnits = $config['total_units'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Units to add must be greater than 0']);
            return;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid update type']);
        return;
    }
    
    // Update the configuration
    $stmt = $pdo->prepare("
        UPDATE doctor_sms_config 
        SET total_units = ?, 
            used_units = ?,
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$finalTotalUnits, $finalUsedUnits, $configId]);
    
    if ($stmt->rowCount() > 0) {
        $remainingUnits = $finalTotalUnits - $finalUsedUnits;
        echo json_encode([
            'success' => true, 
            'message' => 'Units updated successfully',
            'new_total' => $finalTotalUnits,
            'new_used' => $finalUsedUnits,
            'remaining' => $remainingUnits
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No changes made']);
    }
}

function toggleSenderStatus() {
    global $pdo;
    
    $senderId = intval($_POST['sender_id'] ?? 0);
    $currentStatus = intval($_POST['current_status'] ?? 0);
    
    if ($senderId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid sender ID']);
        return;
    }
    
    $newStatus = $currentStatus ? 0 : 1;
    
    $stmt = $pdo->prepare("
        UPDATE sms_sender_ids 
        SET is_active = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $senderId]);
    
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
}
?>