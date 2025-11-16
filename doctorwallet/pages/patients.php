<?php
require_once '../config.php';
requireDoctor();

$message = '';
$error = '';

// Get current doctor's ID with error handling
if (!isset($_SESSION['doctor_id'])) {
    // Try to get doctor_id from different possible session keys
    if (isset($_SESSION['user_id'])) {
        $doctor_id = $_SESSION['user_id'];
    } elseif (isset($_SESSION['id'])) {
        $doctor_id = $_SESSION['id'];
    } else {
        // Redirect to login if no doctor ID found
        header('Location: ../login.php');
        exit;
    }
} else {
    $doctor_id = $_SESSION['doctor_id'];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_adult'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $nic = trim($_POST['nic']);
        $birthday = $_POST['birthday'] ?: null;
        $age = $_POST['age'] ? intval($_POST['age']) : null;
        
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO adults (name, phone_number, nic_number, birthday, age, doctor_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone ?: null, $nic ?: null, $birthday, $age, $doctor_id]);
                $message = 'Adult patient added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding patient: ' . $e->getMessage();
            }
        } else {
            $error = 'Name is required';
        }
    } elseif (isset($_POST['add_kid'])) {
        $name = trim($_POST['kid_name']);
        $parent_id = $_POST['parent_id'];
        $birthday = $_POST['kid_birthday'] ?: null;
        $age = $_POST['kid_age'] ? intval($_POST['kid_age']) : null;
        
        if ($name && $parent_id) {
            try {
                // Verify that the parent belongs to the current doctor
                $stmt = $pdo->prepare("SELECT id FROM adults WHERE id = ? AND doctor_id = ?");
                $stmt->execute([$parent_id, $doctor_id]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO kids (name, parent_id, birthday, age, doctor_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $parent_id, $birthday, $age, $doctor_id]);
                    $message = 'Kid patient added successfully!';
                } else {
                    $error = 'Selected parent not found or does not belong to your practice';
                }
            } catch (PDOException $e) {
                $error = 'Error adding kid patient: ' . $e->getMessage();
            }
        } else {
            $error = 'Kid name and parent selection are required';
        }
    }
}

// Get all adults for parent selection (only for current doctor)
$adults = [];
$stmt = $pdo->prepare("SELECT id, name, phone_number, nic_number, birthday, age FROM adults WHERE doctor_id = ? ORDER BY name");
$stmt->execute([$doctor_id]);
$adults = $stmt->fetchAll();

// Get all kids with parent info (only for current doctor)
$kids = [];
$stmt = $pdo->prepare("
    SELECT k.id, k.name as kid_name, k.birthday as kid_birthday, k.age as kid_age, 
           a.name as parent_name, a.phone_number 
    FROM kids k 
    JOIN adults a ON k.parent_id = a.id 
    WHERE k.doctor_id = ? 
    ORDER BY k.name
");
$stmt->execute([$doctor_id]);
$kids = $stmt->fetchAll();

// Function to calculate age from birthday
function calculateAge($birthday) {
    if (!$birthday) return null;
    $birthDate = new DateTime($birthday);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management - Doctor Wallet</title>
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
    <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Patients Management</h1>

    <!-- Center menu (responsive stack) -->
    <div class="flex flex-wrap justify-center md:justify-center gap-3">
      <!-- Medicines Link -->
      <a href="medicines.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-pills"></i>
        <span>Medicine store</span>
      </a>

      <!-- Drug dispenser Link -->
      <a href="drug_dispenser.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-stethoscope"></i>
        <span>Drug dispenser</span>
      </a>

      <!-- File upload Link -->
      <a href="file_upload.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-file-upload"></i>
        <span>File upload</span>
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

      <!-- Print Section Link -->
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

        <!-- Add Patient Forms -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Add Adult Patient -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Add Adult Patient</h2>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Name *</label>
                        <input type="text" name="name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Enter patient name">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                        <input type="tel" name="phone" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Enter phone number">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">NIC Number</label>
                        <input type="text" name="nic" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Enter NIC number">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Birthday (Optional)</label>
                        <input type="date" name="birthday" id="adult_birthday"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Age (Optional)</label>
                        <input type="number" name="age" id="adult_age" min="0" max="150"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Enter age">
                        <p class="text-xs text-gray-500 mt-1">Age will be calculated automatically if birthday is provided</p>
                    </div>
                    
                    <button type="submit" name="add_adult" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">
                        Add Adult Patient
                    </button>
                </form>
            </div>

            <!-- Add Kid Patient -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Add Kid Patient</h2>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Kid Name *</label>
                        <input type="text" name="kid_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Enter kid name">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Parent *</label>
                        <input type="text" id="parent_search" placeholder="Search parent by name, phone or NIC" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 mb-2">
                        <select name="parent_id" id="parent_select" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Parent</option>
                            <?php foreach ($adults as $adult): ?>
                                <option value="<?php echo $adult['id']; ?>" 
                                        data-search="<?php echo strtolower($adult['name'] . ' ' . $adult['phone_number'] . ' ' . $adult['nic_number']); ?>">
                                    <?php echo htmlspecialchars($adult['name'] . ' - ' . ($adult['phone_number'] ?: 'No Phone')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Birthday (Optional)</label>
                        <input type="date" name="kid_birthday" id="kid_birthday"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Age (Optional)</label>
                        <input type="number" name="kid_age" id="kid_age" min="0" max="18"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Enter age">
                        <p class="text-xs text-gray-500 mt-1">Age will be calculated automatically if birthday is provided</p>
                    </div>
                    
                    <button type="submit" name="add_kid" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">
                        Add Kid Patient
                    </button>
                </form>
            </div>
        </div>

        <!-- Patients Lists -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Adult Patients -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Adult Patients (<?php echo count($adults); ?>)</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Name</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Phone</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Age</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">NIC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adults as $adult): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($adult['name']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($adult['phone_number'] ?: 'N/A'); ?></td>
                                    <td class="px-4 py-2">
                                        <?php 
                                        if ($adult['birthday']) {
                                            $calculatedAge = calculateAge($adult['birthday']);
                                            echo $calculatedAge . ' years';
                                        } elseif ($adult['age']) {
                                            echo $adult['age'] . ' years';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($adult['nic_number'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kid Patients -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Kid Patients (<?php echo count($kids); ?>)</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Kid Name</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Age</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Parent</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Parent Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kids as $kid): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($kid['kid_name']); ?></td>
                                    <td class="px-4 py-2">
                                        <?php 
                                        if ($kid['kid_birthday']) {
                                            $calculatedAge = calculateAge($kid['kid_birthday']);
                                            echo $calculatedAge . ' years';
                                        } elseif ($kid['kid_age']) {
                                            echo $kid['kid_age'] . ' years';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($kid['parent_name']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($kid['phone_number'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
    // Parent search functionality
    $('#parent_search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const $select = $('#parent_select');
        const $options = $select.find('option');
        
        $options.each(function() {
            const $option = $(this);
            const searchData = $option.data('search') || '';
            
            if (searchData.includes(searchTerm) || $option.val() === '') {
                $option.show();
            } else {
                $option.hide();
            }
        });
        
        // Reset selection if current option is hidden
        if (!$select.find('option:selected').is(':visible')) {
            $select.val('');
        }
    });
    
    // Auto-calculate age from birthday for adults
    $('#adult_birthday').on('change', function() {
        const birthday = new Date($(this).val());
        if (birthday) {
            const today = new Date();
            const age = Math.floor((today - birthday) / (365.25 * 24 * 60 * 60 * 1000));
            $('#adult_age').val(age >= 0 ? age : '');
        }
    });
    
    // Auto-calculate age from birthday for kids
    $('#kid_birthday').on('change', function() {
        const birthday = new Date($(this).val());
        if (birthday) {
            const today = new Date();
            const age = Math.floor((today - birthday) / (365.25 * 24 * 60 * 60 * 1000));
            $('#kid_age').val(age >= 0 ? age : '');
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