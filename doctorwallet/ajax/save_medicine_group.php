<?php
// File: ajax/save_medicine_group.php
// IMPORTANT: No whitespace or output before this opening tag

// Start output buffering to capture any unwanted output
ob_start();

require_once '../config.php';
requireDoctor();

// Clean any unwanted output
ob_clean();

// Set JSON headers FIRST
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Turn off all error display
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    ob_end_flush();
    exit;
}

try {
    $doctor_id = $_SESSION['user_id'];
    
    if (empty($doctor_id)) {
        throw new Exception('Doctor ID not found in session');
    }
    
    $group_name = trim($_POST['group_name'] ?? '');
    $medicines = $_POST['medicines'] ?? [];
    
    // Validate input
    if (empty($group_name)) {
        throw new Exception('Group name is required');
    }
    
    if (empty($medicines) || !is_array($medicines)) {
        throw new Exception('At least one medicine is required');
    }
    
    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'medicine_groups'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Medicine groups table not found. Please run database migration first.');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if group name already exists for this doctor
    $stmt = $pdo->prepare("SELECT id FROM medicine_groups WHERE doctor_id = ? AND group_name = ? AND is_active = 1");
    $stmt->execute([$doctor_id, $group_name]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        throw new Exception('Group name already exists. Please choose a different name.');
    }
    
    // Create medicine group
    $stmt = $pdo->prepare("INSERT INTO medicine_groups (doctor_id, group_name, description, created_at) VALUES (?, ?, ?, NOW())");
    $result = $stmt->execute([$doctor_id, $group_name, 'Auto-created medicine group']);
    
    if (!$result) {
        $pdo->rollBack();
        throw new Exception('Failed to create medicine group');
    }
    
    $group_id = $pdo->lastInsertId();
    
    if (!$group_id) {
        $pdo->rollBack();
        throw new Exception('Failed to get group ID after creation');
    }
    
    // Prepare statement for adding medicines to group
    $stmt = $pdo->prepare("INSERT INTO medicine_group_items (group_id, medicine_id, tablets_per_dose, dosage, total_tablets, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    $sort_order = 1;
    $added_count = 0;
    $skipped_count = 0;
    
    foreach ($medicines as $medicine) {
        try {
            $medicine_id = intval($medicine['medicine_id'] ?? 0);
            $tablets_per_dose = floatval($medicine['tablets_per_dose'] ?? 1.0);
            $dosage = trim($medicine['dosage'] ?? 'Bd');
            $total_tablets = intval($medicine['total_tablets'] ?? 10);
            
            // Validate medicine data
            if ($medicine_id <= 0) {
                $skipped_count++;
                continue;
            }
            
            if ($tablets_per_dose <= 0) $tablets_per_dose = 1.0;
            if (empty($dosage)) $dosage = 'Bd';
            if ($total_tablets <= 0) $total_tablets = 10;
            
            // Validate medicine exists and belongs to doctor
            $check_stmt = $pdo->prepare("SELECT id FROM medicines WHERE id = ? AND doctor_id = ?");
            $check_stmt->execute([$medicine_id, $doctor_id]);
            if (!$check_stmt->fetch()) {
                $skipped_count++;
                continue;
            }
            
            // Add medicine to group
            $result = $stmt->execute([$group_id, $medicine_id, $tablets_per_dose, $dosage, $total_tablets, $sort_order]);
            if ($result) {
                $added_count++;
                $sort_order++;
            } else {
                $skipped_count++;
            }
            
        } catch (Exception $me) {
            $skipped_count++;
            continue;
        }
    }
    
    if ($added_count === 0) {
        $pdo->rollBack();
        throw new Exception('No valid medicines were added to the group. Please check your medicine selection.');
    }
    
    // Commit transaction
    $pdo->commit();
    
    $response = [
        'success' => true, 
        'message' => "Medicine group '$group_name' saved successfully",
        'group_id' => $group_id,
        'medicines_added' => $added_count
    ];
    
    if ($skipped_count > 0) {
        $response['warning'] = "$skipped_count medicines were skipped due to validation errors";
    }
    
    // Clean output buffer and send JSON
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Clean output buffer and send error JSON
    ob_clean();
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>