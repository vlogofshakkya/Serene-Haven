<?php
// File: ../ajax/get_patient_reports.php
require_once '../config.php';
requireDoctor();

$patient_type = $_GET['type'] ?? '';
$patient_id = $_GET['id'] ?? '';

try {
    // Get patient basic info first
    if ($patient_type === 'adult') {
        $stmt = $pdo->prepare("SELECT name, phone_number, allergies, birthday FROM adults WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT k.name, a.phone_number, k.allergies, k.birthday FROM kids k JOIN adults a ON k.parent_id = a.id WHERE k.id = ?");
    }
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    // Get patient's prescription history
    $stmt = $pdo->prepare("
        SELECT 
            er.id,
            er.symptoms,
            er.diagnosis,
            er.total_amount,
            er.created_at,
            d.doctor_name,
            COUNT(ri.id) as medicine_count
        FROM e_receipts er
        JOIN doctors d ON er.doctor_id = d.id
        LEFT JOIN receipt_items ri ON er.id = ri.receipt_id
        WHERE er.patient_type = ? AND er.patient_id = ?
        GROUP BY er.id
        ORDER BY er.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_type, $patient_id]);
    $reports = $stmt->fetchAll();
    
    // Get patient's recent investigations
    $stmt = $pdo->prepare("
        SELECT 
            pi.id,
            pi.investigation_date,
            pi.investigation_type,
            pi.test_data,
            pi.notes,
            pi.created_at
        FROM patient_investigations pi
        WHERE pi.patient_type = ? AND pi.patient_id = ?
        ORDER BY pi.investigation_date DESC, pi.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$patient_type, $patient_id]);
    $investigations = $stmt->fetchAll();
    
    if (!empty($reports) || !empty($investigations) || $patient) {
        echo '<div class="border-b pb-3 mb-3">';
        echo '<h4 class="font-semibold text-gray-800">' . htmlspecialchars($patient['name']) . '</h4>';
        echo '<p class="text-sm text-gray-600">Contact: ' . htmlspecialchars($patient['phone_number']) . '</p>';
        
        // Display age information
        if ($patient['birthday']) {
            $dob = new DateTime($patient['birthday']);
            $now = new DateTime();
            $age = $now->diff($dob);
            
            if ($patient_type === 'adult') {
                // For adults: show age in years only
                $age_display = $age->y . ' years';
            } else {
                // For kids: show age in years and months
                if ($age->y > 0) {
                    $age_display = $age->y . ' years, ' . $age->m . ' months';
                } else {
                    $age_display = $age->m . ' months';
                }
            }
            echo '<p class="text-sm text-gray-600">Age: ' . $age_display . '</p>';
        }
        
        if ($patient['allergies']) {
            echo '<p class="text-sm text-red-600 font-medium">Allergies: ' . htmlspecialchars($patient['allergies']) . '</p>';
        }
        echo '</div>';
        
        // Recent Investigations Section
        if (!empty($investigations)) {
            echo '<div class="mb-4">';
            echo '<h5 class="font-semibold text-gray-800 mb-2 flex items-center">';
            echo '<i class="fas fa-flask mr-2 text-blue-600"></i>Recent Investigations';
            echo '</h5>';
            echo '<div class="space-y-2">';
            
            foreach ($investigations as $investigation) {
                $testData = json_decode($investigation['test_data'], true);
                $investigationTitle = getInvestigationTitle($investigation['investigation_type']);
                
                echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3">';
                echo '<div class="flex justify-between items-start mb-2">';
                echo '<span class="text-sm font-medium text-blue-800">' . $investigationTitle . '</span>';
                echo '<span class="text-xs text-gray-500">' . date('M d, Y', strtotime($investigation['investigation_date'])) . '</span>';
                echo '</div>';
                
                // Show key values for the investigation
                if (!empty($testData)) {
                    echo '<div class="grid grid-cols-2 gap-2 text-xs">';
                    $count = 0;
                    foreach ($testData as $key => $value) {
                        if (!empty($value) && $count < 4) { // Show only first 4 values
                            echo '<div class="flex justify-between">';
                            echo '<span class="text-gray-600">' . htmlspecialchars($key) . ':</span>';
                            echo '<span class="font-medium">' . htmlspecialchars($value) . '</span>';
                            echo '</div>';
                            $count++;
                        }
                    }
                    if (count(array_filter($testData)) > 4) {
                        echo '<div class="col-span-2 text-center text-gray-500 text-xs">... and more</div>';
                    }
                    echo '</div>';
                }
                
                if ($investigation['notes']) {
                    echo '<div class="mt-2 text-xs text-gray-600">';
                    echo '<span class="font-medium">Notes:</span> ' . htmlspecialchars(substr($investigation['notes'], 0, 100));
                    if (strlen($investigation['notes']) > 100) echo '...';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        // Prescription Reports Section
        if (!empty($reports)) {
            echo '<div class="space-y-3">';
            echo '<h5 class="font-semibold text-gray-800 flex items-center">';
            echo '<i class="fas fa-prescription-bottle-alt mr-2 text-green-600"></i>Prescription History';
            echo '</h5>';
            
            foreach ($reports as $report) {
                echo '<div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50">';
                echo '<div class="flex justify-between items-start mb-2">';
                echo '<span class="text-xs text-blue-600 font-medium">Receipt #' . $report['id'] . '</span>';
                echo '<span class="text-xs text-gray-500">' . date('M d, Y', strtotime($report['created_at'])) . '</span>';
                echo '</div>';
                
                echo '<div class="text-sm">';
                echo '<p class="font-medium text-gray-800">Symptoms:</p>';
                echo '<p class="text-gray-600 text-xs mb-2">' . htmlspecialchars(substr($report['symptoms'], 0, 100)) . (strlen($report['symptoms']) > 100 ? '...' : '') . '</p>';
                
                if ($report['diagnosis']) {
                    echo '<p class="font-medium text-gray-800">Diagnosis:</p>';
                    echo '<p class="text-gray-600 text-xs mb-2">' . htmlspecialchars(substr($report['diagnosis'], 0, 100)) . (strlen($report['diagnosis']) > 100 ? '...' : '') . '</p>';
                }
                
                echo '<div class="flex justify-between items-center">';
                echo '<span class="text-xs text-gray-500">' . $report['medicine_count'] . ' medicine(s)</span>';
                echo '<span class="text-xs font-medium text-green-600">Rs. ' . number_format($report['total_amount'], 2) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<div class="text-center py-4">';
            echo '<p class="text-gray-500">No prescription history found</p>';
            echo '</div>';
        }
    } else {
        echo '<div class="text-center py-8 text-gray-500">No patient data found</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="text-center py-8 text-red-600">Error loading patient reports: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Helper function to get investigation titles
function getInvestigationTitle($type) {
    $titles = [
        'fbc' => 'Full Blood Count',
        'sugar' => 'Sugar',
        'vitals' => 'Vitals',
        'lipid_profile' => 'Lipid Profile',
        'renal_function' => 'Renal Function',
        'electrolytes' => 'Electrolytes',
        'liver_function' => 'Liver Function',
        'inflammatory_markers' => 'Inflammatory Markers',
        'urine_report' => 'Urine Report',
        'thyroid_function' => 'Thyroid Function'
    ];
    return $titles[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
?>