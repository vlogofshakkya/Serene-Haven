<?php
// File: ../ajax/get_patients.php (DEBUG VERSION)
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

try {
    require_once '../config.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Config file error: ' . $e->getMessage()]);
    exit;
}

// Clean any output that might have occurred
ob_clean();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Debug: Log the request
error_log("get_patients.php called with GET parameters: " . print_r($_GET, true));
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not authenticated in get_patients.php");
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $doctor_id = $_SESSION['user_id'];
    
    error_log("Search term: '$search', Doctor ID: $doctor_id");
    
    // Return empty array if search term is too short
    if (strlen($search) < 1) {
        echo json_encode([]);
        exit;
    }
    
    $patients = [];
    
    // Test database connection
    if (!isset($pdo)) {
        throw new Exception("PDO connection not available");
    }
    
    // Search in adults table
    $sql_adults = "
        SELECT 
            id, 
            name, 
            phone_number, 
            nic_number,
            'adult' as patient_type,
            allergies,
            age,
            birthday
        FROM adults 
        WHERE doctor_id = ? 
        AND (name LIKE ? OR phone_number LIKE ? OR nic_number LIKE ?)
        ORDER BY name ASC
        LIMIT 10
    ";
    
    error_log("Adults SQL: $sql_adults");
    
    $stmt = $pdo->prepare($sql_adults);
    $searchTerm = '%' . $search . '%';
    
    error_log("Search term with wildcards: '$searchTerm'");
    
    $stmt->execute([$doctor_id, $searchTerm, $searchTerm, $searchTerm]);
    $adults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($adults) . " adults");
    
    foreach ($adults as $adult) {
        $display = $adult['name'];
        if (!empty($adult['phone_number'])) {
            $display .= ' - ' . $adult['phone_number'];
        }
        if (!empty($adult['nic_number'])) {
            $display .= ' (NIC: ' . $adult['nic_number'] . ')';
        }
        
        $patients[] = [
            'id' => (int)$adult['id'],
            'type' => 'adult',
            'name' => $adult['name'] ?? '',
            'display' => $display,
            'phone_number' => $adult['phone_number'] ?? '',
            'nic_number' => $adult['nic_number'] ?? '',
            'allergies' => $adult['allergies'] ?? '',
            'age' => $adult['age'] ?? '',
            'birthday' => $adult['birthday'] ?? ''
        ];
    }
    
    // Search in kids table with parent information
    $sql_kids = "
        SELECT 
            k.id, 
            k.name as kid_name, 
            k.allergies,
            k.age,
            k.birthday,
            a.name as parent_name, 
            a.phone_number as parent_phone,
            a.id as parent_id,
            'kid' as patient_type
        FROM kids k
        INNER JOIN adults a ON k.parent_id = a.id
        WHERE k.doctor_id = ?
        AND (k.name LIKE ? OR a.name LIKE ? OR a.phone_number LIKE ?)
        ORDER BY k.name ASC
        LIMIT 10
    ";
    
    error_log("Kids SQL: $sql_kids");
    
    $stmt = $pdo->prepare($sql_kids);
    $stmt->execute([$doctor_id, $searchTerm, $searchTerm, $searchTerm]);
    $kids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($kids) . " kids");
    
    foreach ($kids as $kid) {
        $display = $kid['kid_name'] . ' - Parent: ' . $kid['parent_name'];
        if (!empty($kid['parent_phone'])) {
            $display .= ' - ' . $kid['parent_phone'];
        }
        
        $patients[] = [
            'id' => (int)$kid['id'],
            'type' => 'kid',
            'name' => $kid['kid_name'] ?? '',
            'display' => $display,
            'phone_number' => $kid['parent_phone'] ?? '',
            'nic_number' => '',
            'allergies' => $kid['allergies'] ?? '',
            'age' => $kid['age'] ?? '',
            'birthday' => $kid['birthday'] ?? '',
            'parent_name' => $kid['parent_name'] ?? '',
            'parent_id' => (int)$kid['parent_id']
        ];
    }
    
    // Limit total results
    $patients = array_slice($patients, 0, 10);
    
    error_log("Total patients found: " . count($patients));
    error_log("Returning patients: " . json_encode($patients));
    
    echo json_encode($patients);
    
} catch (PDOException $e) {
    error_log("Database error in get_patients.php: " . $e->getMessage());
    error_log("PDO Error Info: " . print_r($e->errorInfo, true));
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in get_patients.php: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>