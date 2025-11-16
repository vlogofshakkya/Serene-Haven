<?php
require_once '../config.php';
requireDoctor();

header('Content-Type: application/json');

try {
    $doctor_id = $_SESSION['user_id'];
    
    // Get the next waiting token
    $stmt = $pdo->prepare("
        SELECT t.id, t.token_number, t.patient_type, t.patient_id,
               CASE 
                   WHEN t.patient_type = 'adult' THEN a.name
                   WHEN t.patient_type = 'kid' THEN k.name
               END as patient_name
        FROM tokens t
        LEFT JOIN adults a ON t.patient_type = 'adult' AND t.patient_id = a.id
        LEFT JOIN kids k ON t.patient_type = 'kid' AND t.patient_id = k.id
        WHERE t.doctor_id = ? 
        AND t.token_date = CURDATE()
        AND t.status = 'waiting'
        ORDER BY CAST(t.token_number AS UNSIGNED) ASC
        LIMIT 1
    ");
    $stmt->execute([$doctor_id]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($token) {
        echo json_encode([
            'success' => true,
            'token' => $token
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'token' => null
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}