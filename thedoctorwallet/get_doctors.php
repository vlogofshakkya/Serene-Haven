<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Database configuration
define('DB_HOST', 'sql306.infinityfree.com');
define('DB_USER', 'if0_39781227');
define('DB_PASS', 'docwallet');
define('DB_NAME', 'if0_39781227_doc');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Set charset to utf8
$conn->set_charset("utf8");

try {
    $data = [];
    
    // Fetch doctors from doctors table
    $doctorQuery = "SELECT 
        id,
        doctor_name as name,
        phone_number as phone,
        slmc_no as specialty,
        'doctor' as type
    FROM doctors 
    ORDER BY doctor_name ASC";
    
    $doctorResult = $conn->query($doctorQuery);
    
    if ($doctorResult) {
        while ($row = $doctorResult->fetch_assoc()) {
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'Unknown',
                'specialty' => $row['specialty'] ? 'SLMC: ' . $row['specialty'] : null,
                'phone' => $row['phone'] ?? null,
                'email' => null, // Not in doctors table
                'address' => null, // Not in doctors table
                'clinic_name' => null, // Not in doctors table
                'type' => 'doctor'
            ];
        }
    }
    
    // Fetch staff from staff table
    $staffQuery = "SELECT 
        id,
        name,
        phone_number as phone,
        'staff' as type
    FROM staff 
    ORDER BY name ASC";
    
    $staffResult = $conn->query($staffQuery);
    
    if ($staffResult) {
        while ($row = $staffResult->fetch_assoc()) {
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'Unknown',
                'specialty' => 'Staff Member', // Default for staff
                'phone' => $row['phone'] ?? null,
                'email' => null, // Not in staff table
                'address' => null, // Not in staff table
                'clinic_name' => null, // Not in staff table
                'type' => 'staff'
            ];
        }
    }
    
    // Sort all data by name
    usort($data, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => count($data)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
}

$conn->close();
?>