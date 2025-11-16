<?php
require_once '../config.php';
requireDoctor();

$message = '';
$error = '';
$doctor_id = $_SESSION['doctor_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$doctor_id) {
    header('Location: ../login.php');
    exit;
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $patient_type = $_POST['patient_type'];
    $patient_id = $_POST['patient_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            $upload_dir = '../uploads/lab_reports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO lab_reports 
                        (doctor_id, patient_type, patient_id, document_name, original_filename, 
                         file_path, file_type, file_size, notes, uploaded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $doctor_id,
                        $patient_type,
                        $patient_id,
                        $file['name'],
                        $file['name'],
                        $upload_path,
                        $file['type'],
                        $file['size'],
                        $notes,
                        $doctor_id
                    ]);
                    $message = 'Document uploaded successfully!';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    unlink($upload_path);
                }
            } else {
                $error = 'Failed to upload file.';
            }
        } else {
            $error = 'Invalid file type. Only documents are allowed.';
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM lab_reports WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$doc_id, $doctor_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            $stmt = $pdo->prepare("DELETE FROM lab_reports WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$doc_id, $doctor_id]);
            $message = 'Document deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error deleting document: ' . $e->getMessage();
    }
}

// Get all adults for the doctor
$adults = [];
$stmt = $pdo->prepare("SELECT id, name, phone_number, nic_number FROM adults WHERE doctor_id = ? ORDER BY name");
$stmt->execute([$doctor_id]);
$adults = $stmt->fetchAll();

// Get all kids for the doctor
$kids = [];
$stmt = $pdo->prepare("
    SELECT k.id, k.name, k.birthday, k.age, a.name as parent_name, a.phone_number 
    FROM kids k 
    JOIN adults a ON k.parent_id = a.id 
    WHERE k.doctor_id = ? 
    ORDER BY k.name
");
$stmt->execute([$doctor_id]);
$kids = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Reports - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            position: relative;
            overflow-x: hidden;
        }

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

        body.page-transitioning-in {
            animation: smoothIn 0.8s cubic-bezier(0.65, 0, 0.35, 1) forwards;
        }

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

        a {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        a:hover {
            transform: translateY(-2px);
        }

        /* Enhanced autocomplete */
        .autocomplete-items {
            position: absolute;
            border: 1px solid #e5e7eb;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 400px;
            overflow-y: auto;
            background-color: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border-radius: 0 0 12px 12px;
        }

        .autocomplete-items div {
            padding: 14px 18px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s ease;
        }

        .autocomplete-items div:hover {
            background-color: #f9fafb;
            padding-left: 24px;
            border-left: 4px solid #3b82f6;
        }

        .autocomplete-items div:last-child {
            border-bottom: none;
        }

        .autocomplete-active {
            background-color: #3b82f6 !important;
            color: #ffffff;
            border-left: 4px solid #1e40af !important;
        }

        .autocomplete-items::-webkit-scrollbar {
            width: 8px;
        }

        .autocomplete-items::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .autocomplete-items::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .autocomplete-items::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Highlight matched text */
        .highlight {
            background-color: #fef3c7;
            font-weight: 600;
            padding: 1px 3px;
            border-radius: 3px;
        }

        /* Document viewer */
        .doc-viewer {
            width: 100%;
            height: 600px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
        }

        .doc-viewer iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
        }

        /* Patient info card */
        .patient-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* Document card */
        .doc-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .doc-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        /* Search input focus effect */
        #patientSearch:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        /* Empty state animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen" style="visibility: hidden;">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4">
        <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Lab Reports Management</h1>
            
            <div class="flex flex-wrap justify-center md:justify-center gap-3">
                <a href="patients.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                
                <a href="medicines.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
                    <i class="fas fa-pills"></i>
                    <span>Medicines</span>
                </a>
                
                <a href="drug_dispenser.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
                    <i class="fas fa-stethoscope"></i>
                    <span>Drug dispenser</span>
                </a>
            </div>
            
            <div class="flex justify-center md:justify-end">
                <a href="../index.php" class="bg-blue-800 hover:bg-blue-900 px-4 py-2 rounded text-sm md:text-base">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 fade-in-up">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 fade-in-up">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Patient Search Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-search mr-2 text-blue-600"></i>Search Patient
            </h2>
            
            <div class="relative">
                <input type="text" 
                       id="patientSearch" 
                       class="w-full px-4 py-3 pl-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                       placeholder="Start typing patient name, phone number, or NIC..."
                       autocomplete="off">
                <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                <div id="autocomplete-list" class="autocomplete-items"></div>
            </div>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Type any character to search. Results appear instantly.
            </p>
        </div>

        <!-- Patient Details Section (Hidden by default) -->
        <div id="patientDetailsSection" class="hidden mb-6 fade-in-up">
            <div class="patient-info-card">
                <h3 class="text-xl font-bold mb-4">
                    <i class="fas fa-user-circle mr-2"></i>Patient Information
                </h3>
                <div id="patientDetails" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Patient details will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Upload Document Section (Hidden by default) -->
        <div id="uploadSection" class="hidden bg-white rounded-lg shadow p-6 mb-6 fade-in-up">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-upload mr-2 text-green-600"></i>Upload Lab Report
            </h2>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="patient_type" id="upload_patient_type">
                <input type="hidden" name="patient_id" id="upload_patient_id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-file mr-2"></i>Select Document *
                    </label>
                    <input type="file" 
                           name="document" 
                           required 
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv"
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        Allowed formats: PDF, DOC, DOCX, XLS, XLSX, TXT, CSV (Max 10MB)
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-sticky-note mr-2"></i>Notes (Optional)
                    </label>
                    <textarea name="notes" 
                              rows="3" 
                              class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                              placeholder="Add any notes about this report..."></textarea>
                </div>
                
                <button type="submit" 
                        name="upload_document" 
                        class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-cloud-upload-alt mr-2"></i>Upload Document
                </button>
            </form>
        </div>

        <!-- Patient Documents Section (Hidden by default) -->
        <div id="documentsSection" class="hidden fade-in-up">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-folder-open mr-2 text-orange-600"></i>Lab Reports
                <span id="documentCount" class="text-base font-normal text-gray-500 ml-2"></span>
            </h2>
            <div id="documentsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <!-- Documents will be loaded here -->
            </div>
        </div>

        <!-- Document Viewer Section -->
        <div id="viewerSection" class="hidden bg-white rounded-lg shadow p-6 fade-in-up">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-eye mr-2 text-purple-600"></i>Document Viewer
                </h2>
                <button onclick="closeViewer()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
            <div id="documentViewer" class="doc-viewer">
                <!-- Document will be displayed here -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white mt-12 shadow-2xl" style="border-radius: 50px 50px 0 0;">
        <div class="container mx-auto px-6 py-8">
            <div class="text-center">
                <p class="text-indigo-300 text-sm mb-2">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // All patients data
        const allPatients = [
            <?php foreach ($adults as $adult): ?>
            {
                type: 'adult',
                id: <?php echo $adult['id']; ?>,
                name: '<?php echo addslashes($adult['name']); ?>',
                phone: '<?php echo addslashes($adult['phone_number'] ?? ''); ?>',
                nic: '<?php echo addslashes($adult['nic_number'] ?? ''); ?>',
                searchText: '<?php echo strtolower(addslashes($adult['name'] . ' ' . ($adult['phone_number'] ?? '') . ' ' . ($adult['nic_number'] ?? ''))); ?>'
            },
            <?php endforeach; ?>
            <?php foreach ($kids as $kid): ?>
            {
                type: 'kid',
                id: <?php echo $kid['id']; ?>,
                name: '<?php echo addslashes($kid['name']); ?>',
                phone: '<?php echo addslashes($kid['phone_number'] ?? ''); ?>',
                parent: '<?php echo addslashes($kid['parent_name']); ?>',
                age: '<?php echo $kid['age'] ?? 'N/A'; ?>',
                searchText: '<?php echo strtolower(addslashes($kid['name'] . ' ' . ($kid['phone_number'] ?? '') . ' ' . $kid['parent_name'])); ?>'
            },
            <?php endforeach; ?>
        ];

        let currentPatient = null;

        // Powerful autocomplete functionality - works with just 1 character!
        $('#patientSearch').on('input', function() {
            const value = $(this).val().toLowerCase().trim();
            const $list = $('#autocomplete-list');
            
            $list.empty();
            
            // Works with just 1 character now!
            if (value.length < 1) {
                return;
            }
            
            // Advanced fuzzy search - matches anywhere in the text
            const matches = allPatients.filter(p => p.searchText.includes(value));
            
            if (matches.length === 0) {
                $list.append(`
                    <div class="p-4 text-gray-500 text-center">
                        <i class="fas fa-search-minus text-2xl mb-2"></i>
                        <p>No patients found matching "${value}"</p>
                    </div>
                `);
                return;
            }
            
            // Show up to 15 results
            matches.slice(0, 15).forEach(patient => {
                // Highlight matching text
                const highlightedName = highlightMatch(patient.name, value);
                const highlightedPhone = patient.phone ? highlightMatch(patient.phone, value) : '';
                const highlightedNic = patient.nic ? highlightMatch(patient.nic, value) : '';
                const highlightedParent = patient.parent ? highlightMatch(patient.parent, value) : '';
                
                const div = $('<div></div>')
                    .html(`
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center ${patient.type === 'adult' ? 'bg-blue-100' : 'bg-green-100'}">
                                <i class="fas ${patient.type === 'adult' ? 'fa-user' : 'fa-child'} ${patient.type === 'adult' ? 'text-blue-600' : 'text-green-600'}"></i>
                            </div>
                            <div class="flex-1">
                                <strong class="text-base">${highlightedName}</strong>
                                <span class="ml-2 text-xs px-2 py-1 rounded ${patient.type === 'adult' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'}">
                                    ${patient.type === 'adult' ? 'Adult' : 'Child'}
                                </span>
                                ${patient.type === 'adult' ? 
                                    `<br><small class="text-gray-600">
                                        ${highlightedPhone || 'No phone'} 
                                        ${patient.nic ? '| NIC: ' + highlightedNic : ''}
                                    </small>` :
                                    `<br><small class="text-gray-600">
                                        Parent: ${highlightedParent} | ${highlightedPhone || 'No phone'}
                                    </small>`
                                }
                            </div>
                        </div>
                    `)
                    .on('click', function() {
                        selectPatient(patient);
                        $list.empty();
                        $('#patientSearch').val(patient.name);
                    });
                
                $list.append(div);
            });
            
            // Show count
            if (matches.length > 15) {
                $list.append(`
                    <div class="p-3 text-center text-sm text-gray-500 bg-gray-50">
                        <i class="fas fa-info-circle mr-1"></i>
                        Showing 15 of ${matches.length} results. Keep typing to refine search.
                    </div>
                `);
            }
        });

        // Highlight matching text
        function highlightMatch(text, searchValue) {
            if (!text || !searchValue) return text;
            
            const index = text.toLowerCase().indexOf(searchValue.toLowerCase());
            if (index === -1) return text;
            
            const before = text.substring(0, index);
            const match = text.substring(index, index + searchValue.length);
            const after = text.substring(index + searchValue.length);
            
            return before + '<span class="highlight">' + match + '</span>' + after;
        }

        // Close autocomplete when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#patientSearch, #autocomplete-list').length) {
                $('#autocomplete-list').empty();
            }
        });

        // Keyboard navigation for autocomplete
        let currentFocus = -1;
        $('#patientSearch').on('keydown', function(e) {
            const $items = $('#autocomplete-list > div');
            
            if (e.keyCode === 40) { // Down arrow
                currentFocus++;
                addActive($items);
                e.preventDefault();
            } else if (e.keyCode === 38) { // Up arrow
                currentFocus--;
                addActive($items);
                e.preventDefault();
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (currentFocus > -1 && $items.length > 0) {
                    $items.eq(currentFocus).click();
                }
            }
        });

        function addActive($items) {
            if (!$items || $items.length === 0) return false;
            removeActive($items);
            if (currentFocus >= $items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = $items.length - 1;
            $items.eq(currentFocus).addClass('autocomplete-active');
        }

        function removeActive($items) {
            $items.removeClass('autocomplete-active');
        }

        // Select patient
        function selectPatient(patient) {
            currentPatient = patient;
            
            // Show patient details
            let detailsHtml = `
                <div>
                    <p class="text-sm opacity-90">Name</p>
                    <p class="text-lg font-bold">${patient.name}</p>
                </div>
                <div>
                    <p class="text-sm opacity-90">Type</p>
                    <p class="text-lg font-bold">${patient.type === 'adult' ? 'Adult' : 'Child'}</p>
                </div>
            `;
            
            if (patient.type === 'adult') {
                detailsHtml += `
                    <div>
                        <p class="text-sm opacity-90">Phone</p>
                        <p class="text-lg font-bold">${patient.phone || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">NIC</p>
                        <p class="text-lg font-bold">${patient.nic || 'N/A'}</p>
                    </div>
                `;
            } else {
                detailsHtml += `
                    <div>
                        <p class="text-sm opacity-90">Parent</p>
                        <p class="text-lg font-bold">${patient.parent}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Age</p>
                        <p class="text-lg font-bold">${patient.age}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Contact</p>
                        <p class="text-lg font-bold">${patient.phone || 'N/A'}</p>
                    </div>
                `;
            }
            
            $('#patientDetails').html(detailsHtml);
            $('#patientDetailsSection').removeClass('hidden');
            
            // Set form values
            $('#upload_patient_type').val(patient.type);
            $('#upload_patient_id').val(patient.id);
            $('#uploadSection').removeClass('hidden');
            
            // Load documents
            loadDocuments(patient.type, patient.id);
            
            // Smooth scroll to patient details
            $('html, body').animate({
                scrollTop: $('#patientDetailsSection').offset().top - 100
            }, 500);
        }

        // Load documents for selected patient
        function loadDocuments(patientType, patientId) {
            console.log('Loading documents for:', patientType, patientId);
            
            // Show loading indicator
            $('#documentsList').html(`
                <div class="col-span-full text-center py-8">
                    <i class="fas fa-spinner fa-spin text-5xl text-blue-500 mb-3"></i>
                    <p class="text-gray-600 text-lg">Loading documents...</p>
                </div>
            `);
            $('#documentsSection').removeClass('hidden');
            $('#documentCount').text('');
            
            $.ajax({
                url: '../ajax/get_lab_reports.php',
                method: 'GET',
                data: { 
                    patient_type: patientType, 
                    patient_id: patientId 
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Response:', response);
                    
                    if (response.success === false) {
                        console.error('API Error:', response);
                        $('#documentsList').html(`
                            <div class="col-span-full text-center py-8 text-red-500">
                                <i class="fas fa-exclamation-triangle text-5xl mb-3"></i>
                                <p class="text-lg font-semibold mb-2">Error loading documents</p>
                                <p class="text-sm">${response.error || 'Unknown error'}</p>
                                ${response.debug ? '<pre class="text-xs mt-2 bg-gray-100 p-2 rounded">' + JSON.stringify(response.debug, null, 2) + '</pre>' : ''}
                            </div>
                        `);
                        return;
                    }
                    
                    if (response.documents && response.documents.length > 0) {
                        let html = '';
                        response.documents.forEach(doc => {
                            const icon = getFileIcon(doc.file_type);
                            const size = formatFileSize(doc.file_size);
                            const date = new Date(doc.upload_date).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            });
                            
                            html += `
                                <div class="doc-card bg-white rounded-lg shadow-md p-5 border-2 border-gray-200 hover:border-blue-400">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <div class="flex-shrink-0">
                                                <i class="${icon} text-4xl text-blue-500"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-bold text-gray-800 truncate" title="${doc.original_filename}">${doc.original_filename}</h4>
                                                <p class="text-xs text-gray-500">
                                                    <i class="fas fa-hdd mr-1"></i>${size} 
                                                    <i class="fas fa-calendar ml-2 mr-1"></i>${date}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    ${doc.notes ? `
                                        <div class="mb-3 p-2 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                                            <p class="text-sm text-gray-700">
                                                <i class="fas fa-sticky-note text-yellow-600 mr-1"></i>
                                                ${doc.notes}
                                            </p>
                                        </div>
                                    ` : ''}
                                    <div class="flex gap-2">
                                        <button onclick="viewDocument('${doc.file_path}', '${doc.file_type}')" 
                                                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm transition-all transform hover:scale-105">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                        <a href="${doc.file_path}" 
                                           download="${doc.original_filename}"
                                           class="flex-1 bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm text-center transition-all transform hover:scale-105">
                                            <i class="fas fa-download mr-1"></i>Download
                                        </a>
                                        <button onclick="deleteDocument(${doc.id})" 
                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-sm transition-all transform hover:scale-105">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        
                        $('#documentsList').html(html);
                        $('#documentCount').text(`(${response.documents.length} ${response.documents.length === 1 ? 'document' : 'documents'})`);
                        $('#documentsSection').removeClass('hidden');
                    } else {
                        // No documents found - show friendly empty state
                        $('#documentsList').html(`
                            <div class="col-span-full text-center py-12">
                                <div class="inline-block p-8 bg-gray-50 rounded-full mb-4">
                                    <i class="fas fa-folder-open text-6xl text-gray-300"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-700 mb-2">No Documents Yet</h3>
                                <p class="text-gray-500 mb-4">There are no lab reports for this patient.</p>
                                <p class="text-sm text-gray-400">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    Use the upload form above to add the first document
                                </p>
                            </div>
                        `);
                        $('#documentCount').text('(0 documents)');
                        $('#documentsSection').removeClass('hidden');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);
                    
                    $('#documentsList').html(`
                        <div class="col-span-full text-center py-8 text-red-500">
                            <i class="fas fa-exclamation-circle text-5xl mb-3"></i>
                            <p class="text-lg font-semibold mb-2">Failed to load documents</p>
                            <p class="text-sm mb-4">Error: ${error}</p>
                            <button onclick="loadDocuments('${patientType}', ${patientId})" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-all transform hover:scale-105">
                                <i class="fas fa-redo mr-2"></i>Try Again
                            </button>
                        </div>
                    `);
                    $('#documentsSection').removeClass('hidden');
                }
            });
        }

        // View document
        function viewDocument(filePath, fileType) {
            console.log('Viewing document:', filePath);
            
            const extension = filePath.split('.').pop().toLowerCase();
            let viewerHtml = '';
            
            if (extension === 'pdf') {
                viewerHtml = `<iframe src="${filePath}"></iframe>`;
            } else if (['doc', 'docx', 'xls', 'xlsx'].includes(extension)) {
                // Use Office viewer for Office documents
                const fullUrl = window.location.origin + '/' + filePath;
                viewerHtml = `<iframe src="https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(fullUrl)}"></iframe>`;
            } else if (['txt', 'csv'].includes(extension)) {
                // For text files, show download option
                viewerHtml = `
                    <div class="flex flex-col items-center justify-center h-full">
                        <i class="fas fa-file-alt text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 mb-4 text-lg">Text file preview</p>
                        <a href="${filePath}" download class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-all transform hover:scale-105">
                            <i class="fas fa-download mr-2"></i>Download to View
                        </a>
                    </div>
                `;
            } else {
                viewerHtml = `
                    <div class="flex flex-col items-center justify-center h-full">
                        <i class="fas fa-file text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 mb-4 text-lg">Preview not available for this file type</p>
                        <a href="${filePath}" download class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-all transform hover:scale-105">
                            <i class="fas fa-download mr-2"></i>Download File
                        </a>
                    </div>
                `;
            }
            
            $('#documentViewer').html(viewerHtml);
            $('#viewerSection').removeClass('hidden');
            
            $('html, body').animate({
                scrollTop: $('#viewerSection').offset().top - 100
            }, 500);
        }

        // Close viewer
        function closeViewer() {
            $('#viewerSection').addClass('hidden');
        }

        // Delete document
        function deleteDocument(docId) {
            if (confirm('⚠️ Are you sure you want to delete this document?\n\nThis action cannot be undone!')) {
                window.location.href = `?delete=${docId}`;
            }
        }

        // Get file icon based on file type
        function getFileIcon(fileType) {
            const ext = fileType.toLowerCase();
            if (ext.includes('pdf')) return 'fas fa-file-pdf';
            if (ext.includes('word') || ext.includes('doc')) return 'fas fa-file-word';
            if (ext.includes('excel') || ext.includes('sheet') || ext.includes('xls')) return 'fas fa-file-excel';
            if (ext.includes('text') || ext.includes('txt')) return 'fas fa-file-alt';
            if (ext.includes('csv')) return 'fas fa-file-csv';
            return 'fas fa-file';
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Page transitions
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', e => {
                if (link.hostname === window.location.hostname && 
                    !link.target && 
                    !link.classList.contains('no-transition') &&
                    !link.hash &&
                    !link.hasAttribute('download')) {
                    
                    e.preventDefault();
                    const targetUrl = link.href;
                    
                    if (document.body.classList.contains('page-transitioning-out')) {
                        return;
                    }
                    
                    document.body.classList.add('page-transitioning-out');
                    
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 700);
                }
            });
        });

        // Smooth enter animation on page load
        window.addEventListener('DOMContentLoaded', () => {
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