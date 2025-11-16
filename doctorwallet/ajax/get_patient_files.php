<?php
// File: ../ajax/get_patient_files.php
require_once '../config.php';
requireDoctor();

$patient_type = $_GET['type'] ?? '';
$patient_id = $_GET['id'] ?? '';

try {
    $stmt = $pdo->prepare("
        SELECT 
            fu.id,
            fu.file_name,
            fu.file_path,
            fu.file_type,
            fu.created_at,
            d.doctor_name as uploaded_by
        FROM file_uploads fu
        JOIN doctors d ON fu.uploaded_by = d.id
        WHERE fu.patient_type = ? AND fu.patient_id = ?
        ORDER BY fu.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_type, $patient_id]);
    $files = $stmt->fetchAll();
    
    if (!empty($files)) {
        echo '<div class="space-y-2">';
        echo '<h5 class="font-medium text-gray-700 text-sm mb-2">Patient Files:</h5>';
        
        foreach ($files as $file) {
            $file_icon = 'fas fa-file';
            if (strpos($file['file_type'], 'image') !== false) {
                $file_icon = 'fas fa-image';
            } elseif (strpos($file['file_type'], 'pdf') !== false) {
                $file_icon = 'fas fa-file-pdf';
            } elseif (strpos($file['file_type'], 'word') !== false) {
                $file_icon = 'fas fa-file-word';
            }
            
            echo '<div class="flex items-center justify-between p-2 bg-blue-50 rounded border">';
            echo '<div class="flex items-center space-x-2">';
            echo '<i class="' . $file_icon . ' text-blue-600"></i>';
            echo '<div>';
            echo '<p class="text-xs font-medium text-gray-800">' . htmlspecialchars($file['file_name']) . '</p>';
            echo '<p class="text-xs text-gray-500">Uploaded by ' . htmlspecialchars($file['uploaded_by']) . '</p>';
            echo '<p class="text-xs text-gray-400">' . date('M d, Y', strtotime($file['created_at'])) . '</p>';
            echo '</div>';
            echo '</div>';
            echo '<a href="' . htmlspecialchars($file['file_path']) . '" target="_blank" class="text-blue-600 hover:text-blue-800">';
            echo '<i class="fas fa-external-link-alt text-xs"></i>';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="text-center py-4">';
        echo '<p class="text-gray-500 text-xs">No files uploaded for this patient</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="text-center py-4 text-red-600 text-xs">Error loading patient files: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>