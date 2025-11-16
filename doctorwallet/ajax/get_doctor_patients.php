<?php
require_once '../config.php';

header('Content-Type: application/json');

$doctor_id = $_GET['doctor_id'] ?? '';

if (empty($doctor_id) || !is_numeric($doctor_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid doctor ID'
    ]);
    exit;
}

try {
    $patients = [];
    
    // Get adults
    $stmt = $pdo->prepare("
        SELECT id, name, phone_number, nic_number, 'adult' as patient_type
        FROM adults 
        WHERE doctor_id = ? 
        ORDER BY name ASC
    ");
    $stmt->execute([$doctor_id]);
    $adults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get kids
    $stmt = $pdo->prepare("
        SELECT k.id, k.name, k.age, a.name as parent_name, a.phone_number, 'kid' as patient_type
        FROM kids k
        JOIN adults a ON k.parent_id = a.id
        WHERE k.doctor_id = ?
        ORDER BY k.name ASC
    ");
    $stmt->execute([$doctor_id]);
    $kids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $patients = array_merge($adults, $kids);
    
    echo json_encode([
        'success' => true,
        'patients' => $patients
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch patients: ' . $e->getMessage()
    ]);
}
?>