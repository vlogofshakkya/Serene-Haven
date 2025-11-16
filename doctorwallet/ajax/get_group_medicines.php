<?php
// File: ajax/get_group_medicines.php
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

try {
    $group_id = $_GET['group_id'] ?? '';
    $doctor_id = $_SESSION['user_id'];

    if (empty($group_id)) {
        throw new Exception('Group ID is required');
    }
    
    if (empty($doctor_id)) {
        throw new Exception('Doctor ID not found in session');
    }

    // Validate group_id is numeric
    if (!is_numeric($group_id)) {
        throw new Exception('Invalid group ID format');
    }
    
    $group_id = intval($group_id);

    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'medicine_groups'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Medicine groups table not found. Please run database migration first.');
    }
    
    // Verify group belongs to doctor and get group info
    $stmt = $pdo->prepare("SELECT group_name, description FROM medicine_groups WHERE id = ? AND doctor_id = ? AND is_active = 1");
    $stmt->execute([$group_id, $doctor_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception('Group not found or access denied');
    }
    
    // First, let's check what's in medicine_group_items for this group
    $debug_stmt = $pdo->prepare("SELECT * FROM medicine_group_items WHERE group_id = ? AND is_active = 1");
    $debug_stmt->execute([$group_id]);
    $debug_items = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Debug: Found " . count($debug_items) . " items in medicine_group_items for group " . $group_id);
    foreach ($debug_items as $item) {
        error_log("Debug item: " . json_encode($item));
    }
    
    // Get medicines in the group with complete medicine information
    // FIXED: Removed m.unit_type which doesn't exist in the medicines table
    $sql = "
        SELECT 
            mgi.id, 
            mgi.medicine_id, 
            mgi.tablets_per_dose, 
            mgi.dosage, 
            mgi.total_tablets,
            mgi.sort_order,
            m.drug_name, 
            m.current_stock, 
            m.price_per_tablet,
            m.doctor_id as medicine_doctor_id
        FROM medicine_group_items mgi
        LEFT JOIN medicines m ON mgi.medicine_id = m.id
        WHERE mgi.group_id = ? 
          AND mgi.is_active = 1
        ORDER BY mgi.sort_order ASC, COALESCE(m.drug_name, 'Unknown') ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare medicines query');
    }
    
    $result = $stmt->execute([$group_id]);
    
    if (!$result) {
        throw new Exception('Failed to fetch group medicines');
    }
    
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Debug: Found " . count($medicines) . " medicines after JOIN for group " . $group_id);
    
    // Filter and process results
    $valid_medicines = [];
    foreach ($medicines as $medicine) {
        error_log("Debug medicine: " . json_encode($medicine));
        
        // Check if medicine exists and has valid data
        if ($medicine['drug_name'] && $medicine['medicine_doctor_id'] == $doctor_id) {
            // Ensure proper data types for JavaScript
            $medicine['medicine_id'] = intval($medicine['medicine_id']);
            $medicine['tablets_per_dose'] = floatval($medicine['tablets_per_dose']);
            $medicine['total_tablets'] = intval($medicine['total_tablets']);
            $medicine['current_stock'] = intval($medicine['current_stock']);
            $medicine['price_per_tablet'] = floatval($medicine['price_per_tablet']);
            $medicine['sort_order'] = intval($medicine['sort_order']);
            
            $valid_medicines[] = $medicine;
        } else {
            error_log("Debug: Skipped medicine - drug_name: " . ($medicine['drug_name'] ?? 'NULL') . 
                     ", medicine_doctor_id: " . ($medicine['medicine_doctor_id'] ?? 'NULL') . 
                     ", expected_doctor_id: " . $doctor_id);
        }
    }
    
    // Clean output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => true, 
        'group_name' => $group['group_name'],
        'group_description' => $group['description'] ?? '',
        'medicines' => $valid_medicines,
        'total_medicines' => count($valid_medicines),
        'debug_total_items' => count($debug_items),
        'debug_total_joined' => count($medicines)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error in get_group_medicines.php: " . $e->getMessage());
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