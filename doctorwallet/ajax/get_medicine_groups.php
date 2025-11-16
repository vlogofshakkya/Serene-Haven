<?php
// File: ajax/get_medicine_groups.php
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
    $doctor_id = $_SESSION['user_id'];
    
    if (empty($doctor_id)) {
        throw new Exception('Doctor ID not found in session');
    }
    
    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'medicine_groups'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Medicine groups table not found. Please run database migration first.');
    }
    
    // Get all medicine groups for the doctor with error handling
    $sql = "
        SELECT mg.id, mg.group_name, mg.description, mg.created_at,
               COALESCE(COUNT(mgi.id), 0) as medicine_count
        FROM medicine_groups mg
        LEFT JOIN medicine_group_items mgi ON mg.id = mgi.group_id AND mgi.is_active = 1
        WHERE mg.doctor_id = ? AND mg.is_active = 1
        GROUP BY mg.id, mg.group_name, mg.description, mg.created_at
        ORDER BY mg.group_name ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare SQL statement');
    }
    
    $result = $stmt->execute([$doctor_id]);
    if (!$result) {
        throw new Exception('Failed to execute query');
    }
    
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => true, 
        'groups' => $groups,
        'total' => count($groups)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
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