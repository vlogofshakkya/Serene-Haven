<?php
// File: ../ajax/get_patient_details.php
require_once '../config.php';
requireDoctor();

header('Content-Type: application/json');

$patient_type = $_GET['type'] ?? '';
$patient_id = $_GET['id'] ?? '';
$doctor_id = $_SESSION['user_id'];

$response = ['allergies' => '', 'age' => null, 'error' => null];

try {
    if (empty($patient_type) || empty($patient_id)) {
        throw new Exception('Missing patient type or ID');
    }
    
    if ($patient_type === 'adult') {
        $stmt = $pdo->prepare("
            SELECT name, phone_number, nic_number, allergies, birthday, age 
            FROM adults 
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$patient_id, $doctor_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            $finalAge = null;
            $calculatedAge = null;
            
            // First, try to calculate age from birthday if available
            if (!empty($patient['birthday'])) {
                try {
                    $birthDate = new DateTime($patient['birthday']);
                    $today = new DateTime();
                    $calculatedAge = $today->diff($birthDate)->y;
                    
                    // Validate calculated age (should be between 0 and 150)
                    if ($calculatedAge >= 0 && $calculatedAge <= 150) {
                        $finalAge = $calculatedAge;
                    }
                } catch (Exception $e) {
                    error_log("Error calculating age from birthday: " . $e->getMessage());
                }
            }
            
            // Use database age if it's valid and we don't have a calculated age, or if it's more recent
            if (isset($patient['age']) && is_numeric($patient['age']) && $patient['age'] > 0 && $patient['age'] <= 150) {
                $finalAge = (int)$patient['age'];
            }
            
            // Update database with calculated age if we calculated one and database doesn't have valid age
            if ($calculatedAge !== null && (!isset($patient['age']) || $patient['age'] <= 0 || $patient['age'] > 150)) {
                try {
                    $updateStmt = $pdo->prepare("UPDATE adults SET age = ? WHERE id = ?");
                    $updateStmt->execute([$calculatedAge, $patient_id]);
                } catch (Exception $e) {
                    error_log("Error updating adult age: " . $e->getMessage());
                }
            }
            
            $response = [
                'name' => $patient['name'] ?? '',
                'phone_number' => $patient['phone_number'] ?? '',
                'nic_number' => $patient['nic_number'] ?? '',
                'allergies' => $patient['allergies'] ?? '',
                'birthday' => $patient['birthday'] ?? '',
                'age' => $finalAge,
                'error' => null
            ];
            
            // Debug logging
            error_log("Adult patient details - ID: $patient_id, DB Age: " . ($patient['age'] ?? 'null') . ", Birthday: " . ($patient['birthday'] ?? 'null') . ", Calculated: $calculatedAge, Final: $finalAge");
            
        } else {
            $response['error'] = 'Adult patient not found or not authorized';
        }
        
    } elseif ($patient_type === 'kid') {
        $stmt = $pdo->prepare("
            SELECT k.name, k.allergies, k.birthday, k.age, a.phone_number, a.name as parent_name
            FROM kids k 
            JOIN adults a ON k.parent_id = a.id 
            WHERE k.id = ? AND k.doctor_id = ?
        ");
        $stmt->execute([$patient_id, $doctor_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            $finalAge = null;
            $calculatedAge = null;
            
            // First, try to calculate age from birthday if available
            if (!empty($patient['birthday'])) {
                try {
                    $birthDate = new DateTime($patient['birthday']);
                    $today = new DateTime();
                    $calculatedAge = $today->diff($birthDate)->y;
                    
                    // Validate calculated age (should be between 0 and 18 for kids)
                    if ($calculatedAge >= 0 && $calculatedAge <= 25) {
                        $finalAge = $calculatedAge;
                    }
                } catch (Exception $e) {
                    error_log("Error calculating age from birthday: " . $e->getMessage());
                }
            }
            
            // Use database age if it's valid and we don't have a calculated age
            if (isset($patient['age']) && is_numeric($patient['age']) && $patient['age'] > 0 && $patient['age'] <= 25) {
                $finalAge = (int)$patient['age'];
            }
            
            // Update database with calculated age if we calculated one and database doesn't have valid age
            if ($calculatedAge !== null && (!isset($patient['age']) || $patient['age'] <= 0 || $patient['age'] > 25)) {
                try {
                    $updateStmt = $pdo->prepare("UPDATE kids SET age = ? WHERE id = ?");
                    $updateStmt->execute([$calculatedAge, $patient_id]);
                } catch (Exception $e) {
                    error_log("Error updating kid age: " . $e->getMessage());
                }
            }
            
            $response = [
                'name' => $patient['name'] ?? '',
                'phone_number' => $patient['phone_number'] ?? '',
                'allergies' => $patient['allergies'] ?? '',
                'birthday' => $patient['birthday'] ?? '',
                'age' => $finalAge,
                'parent_name' => $patient['parent_name'] ?? '',
                'error' => null
            ];
            
            // Debug logging
            error_log("Kid patient details - ID: $patient_id, DB Age: " . ($patient['age'] ?? 'null') . ", Birthday: " . ($patient['birthday'] ?? 'null') . ", Calculated: $calculatedAge, Final: $finalAge");
            
        } else {
            $response['error'] = 'Kid patient not found or not authorized';
        }
        
    } else {
        $response['error'] = 'Invalid patient type';
    }
    
} catch (Exception $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
    error_log("Error in get_patient_details.php: " . $e->getMessage());
}

// Debug logging
error_log("Final response: " . json_encode($response));

echo json_encode($response);
?>