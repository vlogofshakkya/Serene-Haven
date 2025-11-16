<?php
// Save this as: ajax/get_lab_reports.php
require_once '../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Get doctor ID from session - try multiple possible session keys
$doctor_id = null;
if (isset($_SESSION['doctor_id'])) {
    $doctor_id = $_SESSION['doctor_id'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'doctor') {
    $doctor_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $doctor_id = $_SESSION['id'];
}

if (!$doctor_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized', 
        'debug' => [
            'message' => 'No doctor ID in session',
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
    
    $stmt->execute([$doctor_id, $patient_type, $patient_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the query results
    error_log("Lab Reports Query - Doctor: $doctor_id, Type: $patient_type, ID: $patient_id, Count: " . count($documents));
    
    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'count' => count($documents),
        'debug' => [
            'doctor_id' => $doctor_id,
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
            'doctor_id' => $doctor_id,
            'patient_type' => $patient_type,
            'patient_id' => $patient_id
        ]
    ]);
}
?>