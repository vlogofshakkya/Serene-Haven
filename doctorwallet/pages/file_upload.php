<?php
require_once '../config.php';
requireDoctor();

$message = '';
$error = '';

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle file upload
if ($_POST && isset($_FILES['file'])) {
    $patient_type = $_POST['patient_type'];
    $patient_id = $_POST['patient_id'];
    $file = $_FILES['file'];
    
    if ($patient_id && $file['error'] === UPLOAD_ERR_OK) {
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_type = $file['type'];
        $file_size = $file['size'];
        
        // Validate file size (max 10MB)
        if ($file_size > 10 * 1024 * 1024) {
            $error = 'File size must be less than 10MB';
        } else {
            // Generate unique filename
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_name = uniqid() . '_' . time() . '.' . $extension;
            $file_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO file_uploads (patient_type, patient_id, file_name, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$patient_type, $patient_id, $file_name, $file_path, $file_type, $_SESSION['user_id']]);
                    $message = 'File uploaded successfully!';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    unlink($file_path); // Delete uploaded file if database insert fails
                }
            } else {
                $error = 'Failed to upload file';
            }
        }
    } else {
        $error = 'Please select a patient and file to upload';
    }
}

// Get uploaded files
$files = [];
$stmt = $pdo->prepare("
    SELECT f.*, 
           CASE 
               WHEN f.patient_type = 'adult' THEN a.name
               ELSE CONCAT(k.name, ' (', ap.name, ')')
           END as patient_name
    FROM file_uploads f
    LEFT JOIN adults a ON f.patient_type = 'adult' AND f.patient_id = a.id
    LEFT JOIN kids k ON f.patient_type = 'kid' AND f.patient_id = k.id
    LEFT JOIN adults ap ON f.patient_type = 'kid' AND k.parent_id = ap.id
    WHERE f.uploaded_by = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$files = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload - Doctor Wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
        /* Autocomplete Styles */
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .autocomplete-suggestion {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        
        .autocomplete-suggestion:hover,
        .autocomplete-suggestion.selected {
            background-color: #f3f4f6;
        }
        
        .autocomplete-suggestion:last-child {
            border-bottom: none;
        }
        
        .patient-type-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .adult-badge {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .kid-badge {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .no-results {
            padding: 10px 12px;
            color: #6b7280;
            font-style: italic;
            text-align: center;
        }
        
        .loading {
            padding: 10px 12px;
            color: #6b7280;
            text-align: center;
        }
        
        .selected-patient-info {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        
        .selected-patient-info h4 {
            margin: 0 0 8px 0;
            color: #0c4a6e;
            font-weight: bold;
        }
        
        .patient-detail {
            margin: 4px 0;
            font-size: 14px;
            color: #374151;
        }
        
        .patient-allergies {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 8px;
            margin-top: 8px;
            color: #991b1b;
            font-weight: 500;
        }
        
        .clear-patient-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
        }
        
        .clear-patient-btn:hover {
            background: #dc2626;
        }

/* Modern Premium Smooth Transitions */
body {
    margin: 0;
    padding: 0;
    position: relative;
    overflow-x: hidden;
}

/* Gradient overlay for smooth transition */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, rgba(99, 102, 241, 0.03) 0%, rgba(0, 0, 0, 0.4) 100%);
    opacity: 0;
    pointer-events: none;
    z-index: 9998;
    transition: opacity 0.6s cubic-bezier(0.65, 0, 0.35, 1);
    backdrop-filter: blur(0px);
}

/* Animated gradient overlay */
body::after {
    content: '';
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%);
    opacity: 0;
    pointer-events: none;
    z-index: 9999;
    transform: scale(0);
    transition: all 0.7s cubic-bezier(0.65, 0, 0.35, 1);
}

/* Exit animation */
body.page-transitioning-out {
    animation: smoothOut 0.7s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    pointer-events: none;
}

body.page-transitioning-out::before {
    opacity: 1;
    backdrop-filter: blur(20px);
}

body.page-transitioning-out::after {
    opacity: 1;
    transform: scale(2);
}

/* Enter animation */
body.page-transitioning-in {
    animation: smoothIn 0.8s cubic-bezier(0.65, 0, 0.35, 1) forwards;
}

body.page-transitioning-in::after {
    animation: pulseIn 0.8s cubic-bezier(0.65, 0, 0.35, 1);
}

/* Smooth exit with elastic feel */
@keyframes smoothOut {
    0% {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0deg);
        filter: blur(0px) brightness(1);
    }
    100% {
        opacity: 0;
        transform: translateY(-40px) scale(0.95) rotateX(2deg);
        filter: blur(10px) brightness(0.8);
    }
}

/* Smooth enter with bounce */
@keyframes smoothIn {
    0% {
        opacity: 0;
        transform: translateY(50px) scale(0.94) rotateX(-2deg);
        filter: blur(15px) brightness(0.7);
    }
    60% {
        opacity: 0.8;
        transform: translateY(-5px) scale(1.01) rotateX(0deg);
        filter: blur(2px) brightness(0.95);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0deg);
        filter: blur(0px) brightness(1);
    }
}

/* Pulse effect */
@keyframes pulseIn {
    0%, 100% {
        opacity: 0;
        transform: scale(0);
    }
    50% {
        opacity: 1;
        transform: scale(1.5);
    }
}

/* Prevent scroll during transition */
body.page-transitioning-out,
body.page-transitioning-in {
    overflow: hidden;
    height: 100vh;
}

/* Stagger content animation */
body.page-transitioning-in * {
    animation: contentSlideIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
}

body.page-transitioning-in *:nth-child(1) { animation-delay: 0.1s; }
body.page-transitioning-in *:nth-child(2) { animation-delay: 0.15s; }
body.page-transitioning-in *:nth-child(3) { animation-delay: 0.2s; }
body.page-transitioning-in *:nth-child(4) { animation-delay: 0.25s; }

@keyframes contentSlideIn {
    0% {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Smooth links hover effect */
a {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

a:hover {
    transform: translateY(-2px);
}
</style>
</head>
<body class="bg-gray-100 min-h-screen" style="visibility: hidden;">
<!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4">
  <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-4">

    <!-- Left side (title) -->
    <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">File Upload</h1>

    <!-- Center menu (responsive stack) -->
    <div class="flex flex-wrap justify-center md:justify-center gap-3">
      <!-- Patients Link -->
      <a href="patients.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-user-injured"></i>
        <span>Patients</span>
      </a>

      <!-- Medicines Link -->
      <a href="medicines.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-pills"></i>
        <span>Medicines</span>
      </a>

      <!-- Drug dispenser Link -->
      <a href="drug_dispenser.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-stethoscope"></i>
        <span>Drug dispenser</span>
      </a>

      <!-- Reports Link -->
      <a href="reports.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
      </a>

      <!-- All receipts Link -->
      <a href="receipts.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-receipt"></i>
        <span>All receipts</span>
      </a>

      <!-- New Print Section Button -->
      <a href="print_section.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-pink-500 to-pink-600 hover:from-pink-600 hover:to-pink-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-print text-sm"></i>
        <span>Print Section</span>
      </a>
    </div>

    <!-- Right side -->
    <div class="flex justify-center md:justify-end">
      <a href="../index.php" class="bg-blue-800 hover:bg-blue-900 px-4 py-2 rounded text-sm md:text-base">
        Back to Dashboard
      </a>
    </div>
  </div>
</nav>

    <div class="container mx-auto p-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Upload Patient File</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4" id="uploadForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <!-- Patient Selection Section -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">Patient Selection</h2>
        
                        <!-- Patient Search with Autocomplete -->
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Search Patient *</label>
                            <div class="autocomplete-container">
                                <input 
                                    type="text" 
                                    id="patient_search_input" 
                                    placeholder="Type patient name, phone number, or NIC..." 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    autocomplete="off"
                                />
                                <div id="patient_autocomplete_suggestions" class="autocomplete-suggestions" style="display: none;"></div>
                            </div>
            
                            <!-- Hidden form fields for selected patient -->
                                <input type="hidden" id="selected_patient_id" name="patient_id" />
                                <input type="hidden" id="selected_patient_type" name="patient_type" />
                        </div>
        
                        <!-- Selected Patient Display -->
                            <div id="selected_patient_display" style="display: none;" class="selected-patient-info">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 id="patient_display_name"></h4>
                                        <div id="patient_display_details"></div>
                                        <div id="patient_display_allergies" style="display: none;" class="patient-allergies">
                                            <strong>Allergies:</strong> <span id="patient_allergies_text"></span>
                                        </div>
                                    </div>
                                    <button type="button" id="clear_patient_btn" class="clear-patient-btn">
                                        <i class="fas fa-times"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">File *</label>
                        <input type="file" name="file" required accept="image/*,application/pdf,.doc,.docx,.txt" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <small class="text-gray-500">Accepted formats: Images, PDF, Word documents, Text files (Max: 10MB)</small>
                    </div>
                </div>
                
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">
                    Upload File
                </button>
            </form>
        </div>

        <!-- Uploaded Files -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Uploaded Files (<?php echo count($files); ?>)</h2>
            
            <?php if (empty($files)): ?>
                <div class="text-center text-gray-500 py-8">
                    <div class="text-4xl mb-4">📁</div>
                    <p>No files uploaded yet</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($files as $file): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-2xl">
                                    <?php 
                                    $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        echo '🖼️';
                                    } elseif ($ext === 'pdf') {
                                        echo '📄';
                                    } elseif (in_array($ext, ['doc', 'docx'])) {
                                        echo '📝';
                                    } else {
                                        echo '📁';
                                    }
                                    ?>
                                </div>
                                <button onclick="viewFile('<?php echo addslashes($file['file_path']); ?>', '<?php echo addslashes($file['file_name']); ?>', '<?php echo addslashes($file['file_type']); ?>')"
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                    View
                                </button>
                            </div>
                            
                            <h3 class="font-medium text-gray-800 mb-1 truncate" title="<?php echo htmlspecialchars($file['file_name']); ?>">
                                <?php echo htmlspecialchars($file['file_name']); ?>
                            </h3>
                            
                            <p class="text-sm text-gray-600 mb-1">
                                Patient: <?php echo htmlspecialchars($file['patient_name']); ?>
                            </p>
                            
                            <p class="text-xs text-gray-500">
                                <?php echo date('M d, Y h:i A', strtotime($file['created_at'])); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File Viewer Modal -->
    <div id="fileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-4xl max-h-full overflow-auto">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 id="fileName" class="text-lg font-semibold"></h3>
                <button onclick="closeModal()" class="text-red-500 hover:text-red-700 text-2xl font-bold">&times;</button>
            </div>
            <div id="fileContent" class="p-4">
                <!-- File content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white mt-12 shadow-2xl print-hide" style="border-radius: 50px 50px 0 0;">
        <div class="container mx-auto px-6 py-8">
            <div class="text-center">
                <p class="text-indigo-300 text-sm mb-2">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
    <script>
    $(document).ready(function() {
        let selectedPatientIndex = -1;
        let currentPatients = [];
        
        // Patient search autocomplete - immediate search
        $('#patient_search_input').on('input', function() {
            const searchTerm = $(this).val().trim();
            
            if (searchTerm.length < 1) {
                hideAutocomplete();
                return;
            }
            
            // Show loading and search immediately
            showLoadingInAutocomplete();
            searchPatients(searchTerm);
        });
        
        // Handle keyboard navigation
        $('#patient_search_input').on('keydown', function(e) {
            const suggestions = $('#patient_autocomplete_suggestions .autocomplete-suggestion');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedPatientIndex = Math.min(selectedPatientIndex + 1, suggestions.length - 1);
                updateSelectedSuggestion();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedPatientIndex = Math.max(selectedPatientIndex - 1, -1);
                updateSelectedSuggestion();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedPatientIndex >= 0 && currentPatients[selectedPatientIndex]) {
                    selectPatient(currentPatients[selectedPatientIndex]);
                }
            } else if (e.key === 'Escape') {
                hideAutocomplete();
            }
        });
        
        // Hide autocomplete when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.autocomplete-container').length) {
                hideAutocomplete();
            }
        });
        
        // Clear patient selection
        $('#clear_patient_btn').on('click', function() {
            clearPatientSelection();
        });
        
        // Form validation
        $('#uploadForm').on('submit', function(e) {
            const patientId = $('#selected_patient_id').val();
            
            if (!patientId) {
                e.preventDefault();
                alert('Please select a patient first.');
                $('#patient_search_input').focus();
                return false;
            }
        });
        
        function searchPatients(searchTerm) {
            $.ajax({
                url: '../ajax/get_patients.php',
                method: 'GET',
                data: { search: searchTerm },
                dataType: 'json',
                success: function(patients) {
                    console.log('Received patients:', patients);
                    currentPatients = patients;
                    displayPatientSuggestions(patients);
                },
                error: function(xhr, status, error) {
                    console.error('Error searching patients:', error);
                    showErrorInAutocomplete('Error searching patients. Please try again.');
                }
            });
        }
        
        function displayPatientSuggestions(patients) {
            const $suggestions = $('#patient_autocomplete_suggestions');
            $suggestions.empty();
            selectedPatientIndex = -1;
            
            if (patients.length === 0) {
                $suggestions.append('<div class="no-results">No patients found</div>');
            } else {
                patients.forEach(function(patient, index) {
                    const badgeClass = patient.type === 'adult' ? 'adult-badge' : 'kid-badge';
                    const badgeText = patient.type === 'adult' ? 'Adult' : 'Kid';
                    
                    const $suggestion = $('<div class="autocomplete-suggestion"></div>')
                        .html(patient.display + '<span class="patient-type-badge ' + badgeClass + '">' + badgeText + '</span>')
                        .data('index', index)
                        .on('click', function() {
                            selectPatient(patient);
                        })
                        .on('mouseenter', function() {
                            selectedPatientIndex = index;
                            updateSelectedSuggestion();
                        });
                    
                    $suggestions.append($suggestion);
                });
            }
            
            $suggestions.show();
        }
        
        function selectPatient(patient) {
            console.log('Selecting patient:', patient);
            
            // Set hidden form fields
            $('#selected_patient_id').val(patient.id);
            $('#selected_patient_type').val(patient.type);
            
            // Clear search input and hide suggestions
            $('#patient_search_input').val('');
            hideAutocomplete();
            
            // Display selected patient info
            displaySelectedPatient(patient);
        }
        
        function displaySelectedPatient(patient) {
            $('#patient_display_name').text(patient.name + ' (' + (patient.type === 'adult' ? 'Adult' : 'Child') + ')');
            
            let detailsHtml = '';
            if (patient.phone_number) {
                detailsHtml += '<div class="patient-detail"><strong>Phone:</strong> ' + patient.phone_number + '</div>';
            }
            if (patient.nic_number) {
                detailsHtml += '<div class="patient-detail"><strong>NIC:</strong> ' + patient.nic_number + '</div>';
            }
            if (patient.age) {
                detailsHtml += '<div class="patient-detail"><strong>Age:</strong> ' + patient.age + ' years</div>';
            }
            if (patient.birthday) {
                detailsHtml += '<div class="patient-detail"><strong>Birthday:</strong> ' + formatDate(patient.birthday) + '</div>';
            }
            if (patient.parent_name) {
                detailsHtml += '<div class="patient-detail"><strong>Parent:</strong> ' + patient.parent_name + '</div>';
            }
            
            $('#patient_display_details').html(detailsHtml);
            
            // Show/hide allergies
            if (patient.allergies && patient.allergies.trim()) {
                $('#patient_allergies_text').text(patient.allergies);
                $('#patient_display_allergies').show();
            } else {
                $('#patient_display_allergies').hide();
            }
            
            $('#selected_patient_display').show();
        }
        
        function clearPatientSelection() {
            $('#selected_patient_id').val('');
            $('#selected_patient_type').val('');
            $('#patient_search_input').val('');
            $('#selected_patient_display').hide();
            hideAutocomplete();
        }
        
        function updateSelectedSuggestion() {
            $('#patient_autocomplete_suggestions .autocomplete-suggestion').removeClass('selected');
            if (selectedPatientIndex >= 0) {
                $('#patient_autocomplete_suggestions .autocomplete-suggestion').eq(selectedPatientIndex).addClass('selected');
            }
        }
        
        function hideAutocomplete() {
            $('#patient_autocomplete_suggestions').hide().empty();
            selectedPatientIndex = -1;
        }
        
        function showLoadingInAutocomplete() {
            $('#patient_autocomplete_suggestions')
                .html('<div class="loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>')
                .show();
        }
        
        function showErrorInAutocomplete(message) {
            $('#patient_autocomplete_suggestions')
                .html('<div class="no-results">' + message + '</div>')
                .show();
        }
        
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString();
        }
    });

    // File viewer functions
    function viewFile(filePath, fileName, fileType) {
        $('#fileName').text(fileName);
        
        if (fileType.startsWith('image/')) {
            $('#fileContent').html(`
                <div class="text-center">
                    <img src="${filePath}" alt="${fileName}" class="max-w-full max-h-96 mx-auto rounded">
                </div>
            `);
        } else {
            $('#fileContent').html(`
                <div class="text-center">
                    <div class="text-4xl mb-4">📄</div>
                    <p class="text-gray-600 mb-4">File type: ${fileType}</p>
                    <a href="${filePath}" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        Open File in New Tab
                    </a>
                </div>
            `);
        }
        
        $('#fileModal').removeClass('hidden');
    }

    function closeModal() {
        $('#fileModal').addClass('hidden');
    }

    // Close modal on outside click
    $('#fileModal').click(function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

// Modern smooth page transition system
document.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', e => {
        // Only handle internal links
        if (link.hostname === window.location.hostname && 
            !link.target && 
            !link.classList.contains('no-transition') &&
            !link.hash) {
            
            e.preventDefault();
            const targetUrl = link.href;
            
            // Prevent multiple clicks
            if (document.body.classList.contains('page-transitioning-out')) {
                return;
            }
            
            // Start smooth exit transition
            document.body.classList.add('page-transitioning-out');
            
            // Add subtle haptic feel with smooth timing
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 700);
        }
    });
});

// Smooth enter animation on page load
window.addEventListener('DOMContentLoaded', () => {
    // Prevent flash of unstyled content
    document.body.style.visibility = 'visible';
    document.body.classList.add('page-transitioning-in');
    
    setTimeout(() => {
        document.body.classList.remove('page-transitioning-in');
    }, 800);
});

// Handle browser navigation
window.addEventListener('pageshow', (e) => {
    if (e.persisted) {
        document.body.classList.remove('page-transitioning-out', 'page-transitioning-in');
    }
});

// Prevent flash on initial load
document.body.style.visibility = 'hidden';
    </script>
</body>
</html>