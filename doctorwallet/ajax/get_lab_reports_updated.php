<?php
// Updated version of get_lab_reports.php with laboratory user support
require_once '../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is authenticated (either doctor or lab user)
$is_doctor = false;
$is_lab_user = false;
$user_id = null;

if (isset($_SESSION['doctor_id'])) {
    $is_doctor = true;
    $user_id = $_SESSION['doctor_id'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'doctor') {
    $is_doctor = true;
    $user_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $is_doctor = true;
    $user_id = $_SESSION['id'];
} elseif (isset($_SESSION['lab_user_id']) && isset($_SESSION['lab_user_type']) && $_SESSION['lab_user_type'] === 'laboratory') {
    $is_lab_user = true;
    $user_id = $_SESSION['lab_user_id'];
}

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized', 
        'debug' => [
            'message' => 'No user ID in session',
            'session_keys' => array_keys($_SESSION)
        ]
    ]);
    exit;
}

$patient_type = $_GET['patient_type'] ?? '';
$patient_id = $_GET['patient_id'] ?? '';

if (empty($patient_type) || empty($patient_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid parameters', 
        'debug' => 'Missing patient_type or patient_id'
    ]);
    exit;
}

try {
    // For doctors, filter by doctor_id
    // For lab users, show all reports for the patient
    if ($is_doctor) {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                document_name,
                original_filename,
                file_path,
                file_type,
                file_size,
                notes,
                upload_date
            FROM lab_reports 
            WHERE doctor_id = ? 
            AND patient_type = ? 
            AND patient_id = ?
            ORDER BY upload_date DESC
        ");
        
        $stmt->execute([$user_id, $patient_type, $patient_id]);
    } else {
        // Lab users can see all reports for any patient
        $stmt = $pdo->prepare("
            SELECT 
                id,
                document_name,
                original_filename,
                file_path,
                file_type,
                file_size,
                notes,
                upload_date
            FROM lab_reports 
            WHERE patient_type = ? 
            AND patient_id = ?
            ORDER BY upload_date DESC
        ");
        
        $stmt->execute([$patient_type, $patient_id]);
    }
    
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the query results
    error_log("Lab Reports Query - User Type: " . ($is_doctor ? 'Doctor' : 'Lab User') . ", User ID: $user_id, Type: $patient_type, Patient ID: $patient_id, Count: " . count($documents));
    
    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'count' => count($documents),
        'debug' => [
            'user_type' => $is_doctor ? 'doctor' : 'lab_user',
            'user_id' => $user_id,
            'patient_type' => $patient_type,
            'patient_id' => $patient_id
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Lab Reports Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'debug' => [
            'user_id' => $user_id,
            'patient_type' => $patient_type,
            'patient_id' => $patient_id
        ]
    ]);
}
?>