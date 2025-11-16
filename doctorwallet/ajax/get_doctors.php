<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT id, doctor_name, phone_number, slmc_no 
        FROM doctors 
        ORDER BY doctor_name ASC
    ");
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'doctors' => $doctors
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch doctors: ' . $e->getMessage()
    ]);
}
?>