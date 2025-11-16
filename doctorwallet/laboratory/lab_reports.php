<?php
require_once 'lab_config.php';
requireLaboratory();

$lab_user = getLabUserInfo();
$message = '';
$error = '';

// Show registration success message
if (isset($_GET['registered'])) {
    $message = 'Registration successful! Welcome to the Laboratory Portal.';
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $doctor_id = $_POST['doctor_id'];
    $patient_type = $_POST['patient_type'];
    $patient_id = $_POST['patient_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        
        // Allow all document types except images
        $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check if it's not an image
        $image_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico'];
        
        if (in_array($file_extension, $image_types)) {
            $error = 'Image files are not allowed. Please upload document files only.';
        } elseif (in_array($file_extension, $allowed_types)) {
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
                         file_path, file_type, file_size, notes, uploaded_by, lab_user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                        $_SESSION['lab_user_id'],
                        $_SESSION['lab_user_id']
                    ]);
                    $message = 'Document uploaded successfully!';
                    
                    // Redirect to prevent form resubmission
                    header('Location: lab_reports.php?success=1');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
            } else {
                $error = 'Failed to upload file. Please check directory permissions.';
            }
        } else {
            $error = 'Invalid file type. Only document files are allowed (PDF, DOC, DOCX, XLS, XLSX, TXT, CSV, PPT, etc).';
        }
    } else {
        $upload_error_code = $_FILES['document']['error'] ?? 'unknown';
        $error = 'Please select a file to upload. Error code: ' . $upload_error_code;
    }
}

// Show success message after redirect
if (isset($_GET['success'])) {
    $message = 'Document uploaded successfully!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Portal - Lab Reports</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Autocomplete styles */
        .autocomplete-container {
            position: relative;
        }

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

        .highlight {
            background-color: #fef3c7;
            font-weight: 600;
            padding: 1px 3px;
            border-radius: 3px;
        }

        /* Upload button popup animation */
        .upload-button-container {
            position: fixed;
            bottom: -100px;
            left: 50%;
            transform: translateX(-50%);
            transition: bottom 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1000;
        }

        .upload-button-container.show {
            bottom: 30px;
        }

        /* Document card styles */
        .doc-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .doc-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        /* Section card styles */
        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease;
        }

        .section-card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        /* Fade in animation */
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

        /* File upload area */
        .file-upload-area {
            border: 2px dashed #cbd5e0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #4299e1;
            background-color: #ebf8ff;
        }

        .file-upload-area.has-file {
            border-color: #48bb78;
            background-color: #f0fff4;
        }

        .file-upload-area.drag-over {
            border-color: #4299e1;
            background-color: #ebf8ff;
            transform: scale(1.02);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 text-white p-4 shadow-lg">
        <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-bold">
                    <i class="fas fa-flask mr-2"></i>Laboratory Portal
                </h1>
                <p class="text-sm text-purple-100">
                    <i class="fas fa-hospital mr-1"></i><?php echo htmlspecialchars($lab_user['lab_name']); ?>
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <span class="text-sm">
                    <i class="fas fa-user-circle mr-1"></i>
                    <?php echo htmlspecialchars($lab_user['contact_person']); ?>
                </span>
                <a href="lab_logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 fade-in-up">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 fade-in-up">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Doctor Search Section -->
        <div class="section-card p-6 mb-6 fade-in-up">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-user-md mr-2 text-blue-600"></i>Step 1: Select Doctor
            </h2>
            
            <div class="autocomplete-container">
                <div class="relative">
                    <input type="text" 
                           id="doctorSearch" 
                           class="w-full px-4 py-3 pl-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                           placeholder="Start typing doctor's name, phone, or SLMC number..."
                           autocomplete="off">
                    <i class="fas fa-search absolute left-4 top-4 text-gray-400 pointer-events-none"></i>
                </div>
                <div id="doctorAutocomplete" class="autocomplete-items"></div>
            </div>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Type any character to search doctors in the system
            </p>
        </div>

        <!-- Patient Search Section (Hidden by default) -->
        <div id="patientSection" class="section-card p-6 mb-6 fade-in-up hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-user-injured mr-2 text-green-600"></i>Step 2: Select Patient
            </h2>
            
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                <p class="text-sm text-blue-700">
                    <i class="fas fa-user-md mr-2"></i>
                    <strong>Selected Doctor:</strong> <span id="selectedDoctorName"></span>
                </p>
            </div>
            
            <div class="autocomplete-container">
                <div class="relative">
                    <input type="text" 
                           id="patientSearch" 
                           class="w-full px-4 py-3 pl-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" 
                           placeholder="Start typing patient's name, phone, or NIC..."
                           autocomplete="off">
                    <i class="fas fa-search absolute left-4 top-4 text-gray-400 pointer-events-none"></i>
                </div>
                <div id="patientAutocomplete" class="autocomplete-items"></div>
            </div>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Search patients from the selected doctor's records
            </p>
        </div>

        <!-- Upload Section (Hidden by default) -->
        <div id="uploadSection" class="section-card p-6 mb-6 fade-in-up hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-upload mr-2 text-purple-600"></i>Step 3: Upload Lab Report
            </h2>
            
            <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-500 p-4 mb-6">
                <p class="text-sm text-purple-700 mb-2">
                    <i class="fas fa-user-injured mr-2"></i>
                    <strong>Patient:</strong> <span id="selectedPatientName"></span>
                </p>
                <p class="text-sm text-purple-700">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Type:</strong> <span id="selectedPatientType"></span>
                </p>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="doctor_id" id="upload_doctor_id">
                <input type="hidden" name="patient_type" id="upload_patient_type">
                <input type="hidden" name="patient_id" id="upload_patient_id">
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-3">
                        <i class="fas fa-file mr-2"></i>Select Document *
                    </label>
                    <div class="file-upload-area rounded-lg p-6 text-center" id="fileUploadArea">
                        <input type="file" 
                               name="document" 
                               id="documentInput"
                               required 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.ppt,.pptx,.odt,.ods,.odp,.rtf"
                               style="display: none;">
                        <div id="uploadPrompt">
                            <i class="fas fa-cloud-upload-alt text-5xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 mb-2 font-semibold">drag and drop your file</p>
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                Allowed: PDF, DOC, DOCX, XLS, XLSX, TXT, CSV, PPT, etc. (No images)
                            </p>
                            <p class="text-xs text-gray-500 mt-1">Maximum file size: 10MB</p>
                        </div>
                    </div>
                    <div id="fileInfo" class="hidden mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-green-700">
                                <i class="fas fa-file mr-2"></i>
                                <span id="fileName"></span>
                            </p>
                            <button type="button" onclick="clearFile()" class="text-red-500 hover:text-red-700 font-bold">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-sticky-note mr-2"></i>Notes (Optional)
                    </label>
                    <textarea name="notes" 
                              rows="3" 
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" 
                              placeholder="Add any notes about this lab report..."></textarea>
                </div>
                
                <input type="hidden" name="upload_document" value="1">
            </form>
        </div>

        <!-- Upload Button (Fixed at bottom, shows when file is selected) -->
        <div id="uploadButtonContainer" class="upload-button-container">
            <button type="button" 
                    onclick="submitUpload()" 
                    class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-4 px-8 rounded-full shadow-2xl transition-all duration-200 transform hover:scale-110">
                <i class="fas fa-cloud-upload-alt mr-2 text-xl"></i>
                <span class="text-lg">Upload Lab Report</span>
            </button>
        </div>

        <!-- Patient Documents Section (Hidden by default) -->
        <div id="documentsSection" class="section-card p-6 fade-in-up hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-folder-open mr-2 text-orange-600"></i>Existing Lab Reports
                <span id="documentCount" class="text-base font-normal text-gray-500 ml-2"></span>
            </h2>
            <div id="documentsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Documents will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        let allDoctors = [];
        let allPatients = [];
        let selectedDoctor = null;
        let selectedPatient = null;

        // Load all doctors on page load
        $(document).ready(function() {
            loadDoctors();
            setupFileUpload();
        });

        // Load doctors
        function loadDoctors() {
            $.ajax({
                url: '../ajax/get_doctors.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        allDoctors = response.doctors.map(doctor => ({
                            id: doctor.id,
                            name: doctor.doctor_name,
                            phone: doctor.phone_number || '',
                            slmc: doctor.slmc_no || '',
                            searchText: (doctor.doctor_name + ' ' + (doctor.phone_number || '') + ' ' + (doctor.slmc_no || '')).toLowerCase()
                        }));
                    }
                },
                error: function() {
                    console.error('Failed to load doctors');
                }
            });
        }

        // Doctor search autocomplete
        $('#doctorSearch').on('input', function() {
            const value = $(this).val().toLowerCase().trim();
            const $list = $('#doctorAutocomplete');
            
            $list.empty();
            
            if (value.length < 1) {
                return;
            }
            
            const matches = allDoctors.filter(d => d.searchText.includes(value));
            
            if (matches.length === 0) {
                $list.append(`
                    <div class="p-4 text-gray-500 text-center">
                        <i class="fas fa-search-minus text-2xl mb-2"></i>
                        <p>No doctors found matching "${value}"</p>
                    </div>
                `);
                return;
            }
            
            matches.slice(0, 15).forEach(doctor => {
                const highlightedName = highlightMatch(doctor.name, value);
                const highlightedPhone = doctor.phone ? highlightMatch(doctor.phone, value) : '';
                const highlightedSlmc = doctor.slmc ? highlightMatch(doctor.slmc, value) : '';
                
                const div = $('<div></div>')
                    .html(`
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-blue-100">
                                <i class="fas fa-user-md text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <strong class="text-base">${highlightedName}</strong>
                                <br><small class="text-gray-600">
                                    ${highlightedPhone || 'No phone'} 
                                    ${doctor.slmc ? '| SLMC: ' + highlightedSlmc : ''}
                                </small>
                            </div>
                        </div>
                    `)
                    .on('click', function() {
                        selectDoctor(doctor);
                        $list.empty();
                        $('#doctorSearch').val(doctor.name);
                    });
                
                $list.append(div);
            });
        });

        // Select doctor
        function selectDoctor(doctor) {
            selectedDoctor = doctor;
            $('#selectedDoctorName').text(doctor.name);
            $('#upload_doctor_id').val(doctor.id);
            
            // Show patient section
            $('#patientSection').removeClass('hidden');
            
            // Load patients for this doctor
            loadPatients(doctor.id);
            
            // Scroll to patient section
            $('html, body').animate({
                scrollTop: $('#patientSection').offset().top - 100
            }, 500);
        }

        // Load patients for selected doctor
        function loadPatients(doctorId) {
            $.ajax({
                url: '../ajax/get_doctor_patients.php',
                method: 'GET',
                data: { doctor_id: doctorId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        allPatients = response.patients.map(patient => ({
                            id: patient.id,
                            type: patient.patient_type,
                            name: patient.name,
                            phone: patient.phone_number || '',
                            nic: patient.nic_number || '',
                            parent: patient.parent_name || '',
                            age: patient.age || '',
                            searchText: (patient.name + ' ' + (patient.phone_number || '') + ' ' + (patient.nic_number || '') + ' ' + (patient.parent_name || '')).toLowerCase()
                        }));
                    }
                },
                error: function() {
                    console.error('Failed to load patients');
                }
            });
        }

        // Patient search autocomplete
        $('#patientSearch').on('input', function() {
            const value = $(this).val().toLowerCase().trim();
            const $list = $('#patientAutocomplete');
            
            $list.empty();
            
            if (value.length < 1) {
                return;
            }
            
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
            
            matches.slice(0, 15).forEach(patient => {
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
        });

        // Select patient
        function selectPatient(patient) {
            selectedPatient = patient;
            $('#selectedPatientName').text(patient.name);
            $('#selectedPatientType').text(patient.type === 'adult' ? 'Adult' : 'Child');
            $('#upload_patient_type').val(patient.type);
            $('#upload_patient_id').val(patient.id);
            
            // Show upload section
            $('#uploadSection').removeClass('hidden');
            
            // Load existing documents
            loadDocuments(patient.type, patient.id);
            
            // Scroll to upload section
            $('html, body').animate({
                scrollTop: $('#uploadSection').offset().top - 100
            }, 500);
        }

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

        // Setup file upload
        function setupFileUpload() {
            // Click to upload
            $('#fileUploadArea').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#documentInput').click();
            });
            
            // File input change
            $('#documentInput').on('change', function(e) {
                handleFileSelect(this.files);
            });
            
            // Drag and drop
            const uploadArea = document.getElementById('fileUploadArea');
            
            uploadArea.addEventListener('dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });
            
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    document.getElementById('documentInput').files = dt.files;
                    handleFileSelect(files);
                }
            });
        }

        // Handle file selection
        function handleFileSelect(files) {
            if (files && files.length > 0) {
                const file = files[0];
                
                // Check file size (10MB limit)
                if (file.size > 10 * 1024 * 1024) {
                    alert('File is too large! Maximum size is 10MB.');
                    clearFile();
                    return;
                }
                
                // Check if it's an image
                const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico'];
                const fileName = file.name.toLowerCase();
                const fileExt = fileName.substring(fileName.lastIndexOf('.') + 1);
                
                if (imageTypes.includes(fileExt)) {
                    alert('Image files are not allowed! Please upload document files only (PDF, DOC, XLS, etc).');
                    clearFile();
                    return;
                }
                
                $('#fileName').text(file.name + ' (' + formatFileSize(file.size) + ')');
                $('#fileInfo').removeClass('hidden');
                $('#uploadPrompt').hide();
                $('#fileUploadArea').addClass('has-file');
                $('#uploadButtonContainer').addClass('show');
            }
        }

        // Clear file selection
        function clearFile() {
            $('#documentInput').val('');
            $('#fileInfo').addClass('hidden');
            $('#uploadPrompt').show();
            $('#fileUploadArea').removeClass('has-file');
            $('#uploadButtonContainer').removeClass('show');
        }

        // Submit upload
        function submitUpload() {
            const fileInput = document.getElementById('documentInput');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please select a file to upload.');
                return;
            }
            
            // Show loading state
            $('#uploadButtonContainer button').html('<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...').prop('disabled', true);
            
            // Submit the form
            document.getElementById('uploadForm').submit();
        }

        // Load documents for selected patient
        function loadDocuments(patientType, patientId) {
            $('#documentsList').html(`
                <div class="col-span-full text-center py-8">
                    <i class="fas fa-spinner fa-spin text-5xl text-blue-500 mb-3"></i>
                    <p class="text-gray-600 text-lg">Loading documents...</p>
                </div>
            `);
            $('#documentsSection').removeClass('hidden');
            
            $.ajax({
                url: '../ajax/get_lab_reports.php',
                method: 'GET',
                data: { 
                    patient_type: patientType, 
                    patient_id: patientId 
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.documents && response.documents.length > 0) {
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
                                                ${escapeHtml(doc.notes)}
                                            </p>
                                        </div>
                                    ` : ''}
                                    <div class="flex gap-2">
                                        <a href="${doc.file_path}" 
                                           target="_blank"
                                           class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm text-center transition-all transform hover:scale-105">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </a>
                                        <a href="${doc.file_path}" 
                                           download="${doc.original_filename}"
                                           class="flex-1 bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm text-center transition-all transform hover:scale-105">
                                            <i class="fas fa-download mr-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            `;
                        });
                        
                        $('#documentsList').html(html);
                        $('#documentCount').text(`(${response.documents.length} ${response.documents.length === 1 ? 'document' : 'documents'})`);
                    } else {
                        $('#documentsList').html(`
                            <div class="col-span-full text-center py-12">
                                <div class="inline-block p-8 bg-gray-50 rounded-full mb-4">
                                    <i class="fas fa-folder-open text-6xl text-gray-300"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-700 mb-2">Patient Haven't Any Lab Reports</h3>
                                <p class="text-gray-500 mb-4">No lab reports have been uploaded for this patient yet.</p>
                                <p class="text-sm text-gray-400">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    Use the upload form above to add the first document
                                </p>
                            </div>
                        `);
                        $('#documentCount').text('(0 documents)');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    $('#documentsList').html(`
                        <div class="col-span-full text-center py-8 text-red-500">
                            <i class="fas fa-exclamation-circle text-5xl mb-3"></i>
                            <p class="text-lg font-semibold mb-2">Failed to load documents</p>
                            <button onclick="loadDocuments('${patientType}', ${patientId})" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-all transform hover:scale-105">
                                <i class="fas fa-redo mr-2"></i>Try Again
                            </button>
                        </div>
                    `);
                }
            });
        }

        // Get file icon based on file type
        function getFileIcon(fileType) {
            const ext = fileType.toLowerCase();
            if (ext.includes('pdf')) return 'fas fa-file-pdf text-red-500';
            if (ext.includes('word') || ext.includes('doc')) return 'fas fa-file-word text-blue-500';
            if (ext.includes('excel') || ext.includes('sheet') || ext.includes('xls')) return 'fas fa-file-excel text-green-500';
            if (ext.includes('powerpoint') || ext.includes('presentation') || ext.includes('ppt')) return 'fas fa-file-powerpoint text-orange-500';
            if (ext.includes('text') || ext.includes('txt')) return 'fas fa-file-alt text-gray-500';
            if (ext.includes('csv')) return 'fas fa-file-csv text-green-500';
            return 'fas fa-file text-gray-500';
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Close autocomplete when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#doctorSearch, #doctorAutocomplete').length) {
                $('#doctorAutocomplete').empty();
            }
            if (!$(e.target).closest('#patientSearch, #patientAutocomplete').length) {
                $('#patientAutocomplete').empty();
            }
        });

        // Keyboard navigation for autocomplete
        let currentFocus = -1;
        
        $('#doctorSearch, #patientSearch').on('keydown', function(e) {
            const isDoctor = $(this).attr('id') === 'doctorSearch';
            const $items = $(isDoctor ? '#doctorAutocomplete > div' : '#patientAutocomplete > div');
            
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
    </script>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 text-white mt-12 py-6 mt-auto">
        <div class="container mx-auto px-6">
            <div class="text-center">
                <p class="text-purple-200 text-sm">
                    Copyright © 2025 Doctor Wallet - Laboratory Portal. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>
</html>