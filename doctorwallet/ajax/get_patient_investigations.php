<?php
// File: ../ajax/get_patient_investigations.php
require_once '../config.php';
requireDoctor();

$patient_type = $_GET['type'] ?? '';
$patient_id = $_GET['id'] ?? '';

if (empty($patient_type) || empty($patient_id)) {
    echo '<div class="text-center py-8 text-red-600">';
    echo '<div class="text-red-400 mb-2">';
    echo '<i class="fas fa-exclamation-triangle text-2xl"></i>';
    echo '</div>';
    echo 'Invalid patient data provided';
    echo '</div>';
    exit;
}

try {
    // Get patient's investigations
    $stmt = $pdo->prepare("
        SELECT 
            pi.id,
            pi.investigation_date,
            pi.investigation_type,
            pi.test_data,
            pi.notes,
            pi.created_at
        FROM patient_investigations pi
        WHERE pi.patient_type = ? AND pi.patient_id = ? AND pi.doctor_id = ?
        ORDER BY pi.investigation_date DESC, pi.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$patient_type, $patient_id, $_SESSION['user_id']]);
    $investigations = $stmt->fetchAll();
    
    if (!empty($investigations)) {
        foreach ($investigations as $investigation) {
            $testData = json_decode($investigation['test_data'], true) ?: [];
            $investigationTitle = getInvestigationTitle($investigation['investigation_type']);
            $hasTestData = !empty($testData) && array_filter($testData, function($value) { return !empty(trim($value)); });
            
            echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-3">';
            
            // Header
            echo '<div class="flex justify-between items-start mb-3">';
            echo '<div>';
            echo '<h6 class="font-semibold text-gray-800 text-lg">' . htmlspecialchars($investigationTitle) . '</h6>';
            echo '<p class="text-sm text-gray-600">';
            echo '<i class="fas fa-calendar-alt mr-1"></i>';
            echo 'Investigation Date: ' . date('M d, Y', strtotime($investigation['investigation_date']));
            echo '</p>';
            echo '</div>';
            echo '<span class="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded">';
            echo 'Added: ' . date('M d, Y H:i', strtotime($investigation['created_at']));
            echo '</span>';
            echo '</div>';
            
            // Test Data
            if ($hasTestData) {
                echo '<div class="mb-3">';
                echo '<h6 class="text-sm font-medium text-gray-700 mb-2">Test Results:</h6>';
                echo '<div class="grid grid-cols-2 md:grid-cols-3 gap-3">';
                foreach ($testData as $key => $value) {
                    if (!empty(trim($value))) {
                        echo '<div class="bg-white p-3 rounded border shadow-sm">';
                        echo '<div class="text-xs font-medium text-gray-600 uppercase tracking-wide">' . htmlspecialchars(formatParameterName($key)) . '</div>';
                        echo '<div class="text-sm font-semibold text-gray-900 mt-1">' . htmlspecialchars($value) . '</div>';
                        echo '</div>';
                    }
                }
                echo '</div>';
                echo '</div>';
            }
            
            // Notes
            if (!empty(trim($investigation['notes']))) {
                echo '<div class="bg-yellow-50 border border-yellow-200 rounded p-3">';
                echo '<div class="flex items-start">';
                echo '<i class="fas fa-sticky-note text-yellow-600 mr-2 mt-0.5"></i>';
                echo '<div>';
                echo '<div class="text-xs font-medium text-yellow-800 mb-1">Notes:</div>';
                echo '<div class="text-sm text-yellow-700">' . nl2br(htmlspecialchars($investigation['notes'])) . '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            
            // If no test data and no notes
            if (!$hasTestData && empty(trim($investigation['notes']))) {
                echo '<div class="text-gray-500 text-sm italic">';
                echo '<i class="fas fa-info-circle mr-1"></i>';
                echo 'No detailed data recorded for this investigation';
                echo '</div>';
            }
            
            echo '</div>';
        }
    } else {
        echo '<div class="text-center py-8">';
        echo '<div class="text-gray-400 mb-4">';
        echo '<i class="fas fa-flask text-6xl"></i>';
        echo '</div>';
        echo '<h4 class="text-lg font-medium text-gray-600 mb-2">No Previous Investigations</h4>';
        echo '<p class="text-gray-500">This patient has no recorded investigations yet.</p>';
        echo '<p class="text-sm text-gray-400 mt-2">Add new investigation data using the form above</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="text-center py-8 text-red-600">';
    echo '<div class="text-red-400 mb-2">';
    echo '<i class="fas fa-exclamation-triangle text-4xl"></i>';
    echo '</div>';
    echo '<h4 class="text-lg font-medium text-red-600 mb-2">Error Loading Investigations</h4>';
    echo '<p class="text-sm text-red-500">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<button onclick="location.reload()" class="mt-3 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">';
    echo '<i class="fas fa-refresh mr-1"></i>Try Again';
    echo '</button>';
    echo '</div>';
}

// Helper function to get investigation titles
function getInvestigationTitle($type) {
    $titles = [
        'fbc' => 'Full Blood Count (FBC)',
        'sugar' => 'Sugar Profile',
        'vitals' => 'Vital Signs',
        'lipid_profile' => 'Lipid Profile',
        'renal_function' => 'Renal Function',
        'electrolytes' => 'Electrolytes',
        'liver_function' => 'Liver Function',
        'inflammatory_markers' => 'Inflammatory Markers',
        'urine_report' => 'Urine Analysis',
        'thyroid_function' => 'Thyroid Function'
    ];
    return $titles[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

// Helper function to format parameter names
function formatParameterName($parameterCode) {
    $names = [
        // FBC
        'wbc' => 'White Blood Cells',
        'rbc' => 'Red Blood Cells', 
        'hgb' => 'Hemoglobin',
        'hct' => 'Hematocrit',
        'plt' => 'Platelets',
        'neut' => 'Neutrophils',
        'lymph' => 'Lymphocytes',
        
        // Sugar
        'fbg' => 'Fasting Glucose',
        'rbg' => 'Random Glucose',
        'hba1c' => 'HbA1c',
        'pp2hr' => '2hr Post Prandial',
        
        // Vitals
        'bp_sys' => 'BP Systolic',
        'bp_dia' => 'BP Diastolic',
        'hr' => 'Heart Rate',
        'temp' => 'Temperature',
        'rr' => 'Respiratory Rate',
        'spo2' => 'SpO2',
        'weight' => 'Weight',
        'height' => 'Height',
        
        // Lipid Profile
        'tc' => 'Total Cholesterol',
        'hdl' => 'HDL Cholesterol',
        'ldl' => 'LDL Cholesterol',
        'tg' => 'Triglycerides',
        
        // Renal Function
        'bun' => 'BUN',
        'cr' => 'Creatinine',
        'egfr' => 'eGFR',
        'ua' => 'Uric Acid',
        
        // Electrolytes
        'na' => 'Sodium',
        'k' => 'Potassium',
        'cl' => 'Chloride',
        'co2' => 'CO2',
        
        // Liver Function
        'alt' => 'ALT',
        'ast' => 'AST',
        'tbil' => 'Total Bilirubin',
        'alp' => 'Alkaline Phosphatase',
        'alb' => 'Albumin',
        
        // Inflammatory Markers
        'esr' => 'ESR',
        'crp' => 'CRP',
        
        // Urine Report
        'color' => 'Color',
        'appearance' => 'Appearance',
        'sg' => 'Specific Gravity',
        'ph' => 'pH',
        'protein' => 'Protein',
        'glucose' => 'Glucose',
        'rbc_urine' => 'RBC',
        'wbc_urine' => 'WBC',
        
        // Thyroid Function
        'tsh' => 'TSH',
        'ft4' => 'Free T4',
        'ft3' => 'Free T3',
        't4' => 'T4 Total',
        't3' => 'T3 Total'
    ];
    
    return $names[$parameterCode] ?? strtoupper(str_replace('_', ' ', $parameterCode));
}
?>