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


// Handle stock update
if ($_POST && isset($_POST['update_stock'])) {
    $medicine_id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : 0;
    $update_stock = isset($_POST['update_stock_quantity']) ? (int)$_POST['update_stock_quantity'] : 0;
    $new_expiry_date = isset($_POST['new_expiry_date']) ? $_POST['new_expiry_date'] : null;
    
    if ($medicine_id > 0 && $update_stock > 0) {
        try {
            // Get current medicine data - ensure it belongs to current doctor
            $stmt = $pdo->prepare("SELECT current_stock, notify_threshold, drug_name FROM medicines WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$medicine_id, $doctor_id]);
            $current = $stmt->fetch();
            
            if ($current) {
                // Update stock: current_stock = current + update, amount_brought = update_stock
                $new_stock = $current['current_stock'] + $update_stock;
                
                // Build update query based on whether expiry date is provided
                if ($new_expiry_date) {
                    $stmt = $pdo->prepare("UPDATE medicines SET amount_brought = ?, current_stock = ?, expiry_date = ?, updated_at = NOW() WHERE id = ? AND doctor_id = ?");
                    $stmt->execute([$update_stock, $new_stock, $new_expiry_date, $medicine_id, $doctor_id]);
                    $message = 'Stock and expiry date updated successfully! Added ' . $update_stock . ' tablets. New stock: ' . $new_stock;
                } else {
                    $stmt = $pdo->prepare("UPDATE medicines SET amount_brought = ?, current_stock = ?, updated_at = NOW() WHERE id = ? AND doctor_id = ?");
                    $stmt->execute([$update_stock, $new_stock, $medicine_id, $doctor_id]);
                    $message = 'Stock updated successfully! Added ' . $update_stock . ' tablets. New stock: ' . $new_stock;
                }
                
                // Mark low stock notifications as read if stock is now above threshold
                if ($new_stock > $current['notify_threshold']) {
                    $stmt = $pdo->prepare("UPDATE medicine_notifications SET is_read = 1 WHERE medicine_id = ? AND doctor_id = ? AND notification_type = 'low_stock' AND is_read = 0");
                    $stmt->execute([$medicine_id, $doctor_id]);
                }
                
            } else {
                $error = 'Medicine not found or you do not have permission to update it';
            }
        } catch (PDOException $e) {
            $error = 'Error updating stock: ' . $e->getMessage();
        }
    } else {
        $error = 'Please provide valid medicine ID (' . $medicine_id . ') and stock quantity (' . $update_stock . ')';
    }
}

// Handle form submission for adding medicine
if ($_POST && isset($_POST['add_medicine'])) {
    $drug_name = trim($_POST['drug_name']);
    $amount_brought = (int)$_POST['amount_brought'];
    $price_per_tablet = (float)$_POST['price_per_tablet'];
    $buying_price = (float)$_POST['buying_price'];
    $expiry_date = $_POST['expiry_date'];
    $notify_threshold = (int)$_POST['notify_threshold'];
    
    if ($drug_name && $amount_brought > 0 && $price_per_tablet > 0 && $buying_price > 0) {
        try {
            // Check if medicine already exists for this doctor
            $stmt = $pdo->prepare("SELECT id, current_stock FROM medicines WHERE drug_name = ? AND doctor_id = ?");
            $stmt->execute([$drug_name, $doctor_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing medicine stock
                $new_stock = $existing['current_stock'] + $amount_brought;
                $stmt = $pdo->prepare("UPDATE medicines SET amount_brought = amount_brought + ?, current_stock = ?, price_per_tablet = ?, buying_price = ?, expiry_date = ?, notify_threshold = ?, updated_at = NOW() WHERE id = ? AND doctor_id = ?");
                $stmt->execute([$amount_brought, $new_stock, $price_per_tablet, $buying_price, $expiry_date, $notify_threshold, $existing['id'], $doctor_id]);
                $message = 'Medicine stock updated successfully!';
                
                // Mark low stock notifications as read if stock is now above threshold
                if ($new_stock > $notify_threshold) {
                    $stmt = $pdo->prepare("UPDATE medicine_notifications SET is_read = 1 WHERE medicine_id = ? AND doctor_id = ? AND notification_type = 'low_stock' AND is_read = 0");
                    $stmt->execute([$existing['id'], $doctor_id]);
                }
            } else {
                // Add new medicine with doctor_id
                $stmt = $pdo->prepare("INSERT INTO medicines (doctor_id, drug_name, amount_brought, price_per_tablet, buying_price, current_stock, expiry_date, notify_threshold, total_sold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$doctor_id, $drug_name, $amount_brought, $price_per_tablet, $buying_price, $amount_brought, $expiry_date, $notify_threshold]);
                $message = 'New medicine added successfully!';
                
                $new_medicine_id = $pdo->lastInsertId();
                
                // Create notification if stock is low
                if ($amount_brought <= $notify_threshold) {
                    createLowStockNotification($pdo, $new_medicine_id, $drug_name, $amount_brought, $current_doctor_id);
                }
            }
        } catch (PDOException $e) {
            $error = 'Error adding medicine: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill all required fields with valid values';
    }
}

// Function to create low stock notification
function createLowStockNotification($pdo, $medicine_id, $drug_name, $current_stock, $doctor_id) {
    $message = "Medicine '{$drug_name}' is running low! Current stock: {$current_stock}";
    $stmt = $pdo->prepare("INSERT INTO medicine_notifications (medicine_id, doctor_id, notification_type, message) VALUES (?, ?, 'low_stock', ?)");
    $stmt->execute([$medicine_id, $doctor_id, $message]);
}

// Get all medicines with notifications - only for current doctor
$medicines = [];
$stmt = $pdo->prepare("
    SELECT m.*, 
    CASE WHEN m.current_stock <= m.notify_threshold THEN 1 ELSE 0 END as is_low_stock,
    CASE WHEN m.expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END as is_expiring_soon
    FROM medicines m 
    WHERE m.doctor_id = ?
    ORDER BY m.drug_name
");
$stmt->execute([$doctor_id]);
$medicines = $stmt->fetchAll();

// Get active notifications - only show unread low stock alerts for medicines that are STILL low stock and belong to current doctor
$notifications = [];
$stmt = $pdo->prepare("
    SELECT n.*, m.drug_name, m.current_stock, m.notify_threshold
    FROM medicine_notifications n 
    JOIN medicines m ON n.medicine_id = m.id 
    WHERE n.is_read = 0 
    AND n.doctor_id = ?
    AND (
        (n.notification_type = 'low_stock' AND m.current_stock <= m.notify_threshold) 
        OR n.notification_type != 'low_stock'
    )
    ORDER BY n.created_at DESC 
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$notifications = $stmt->fetchAll();

// Calculate totals - Based on actual sales - only for current doctor's medicines
$total_investment = 0;
$total_revenue = 0;
$total_profit = 0;
$total_potential_value = 0;

foreach ($medicines as $medicine) {
    // Investment (buying cost of all stock brought)
    $medicine_investment = $medicine['amount_brought'] * $medicine['buying_price'];
    $total_investment += $medicine_investment;
    
    // Revenue (selling price of sold tablets)
    $medicine_revenue = $medicine['total_sold'] * $medicine['price_per_tablet'];
    $total_revenue += $medicine_revenue;
    
    // Actual profit (revenue - cost of sold tablets)
    $sold_cost = $medicine['total_sold'] * $medicine['buying_price'];
    $medicine_profit = $medicine_revenue - $sold_cost;
    $total_profit += $medicine_profit;
    
    // Potential value of remaining stock
    $remaining_value = $medicine['current_stock'] * $medicine['price_per_tablet'];
    $total_potential_value += $remaining_value;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines Management - Doctor Wallet</title>
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
        .notification-alert {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
    <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Medicines Management</h1>

    <!-- Center menu (responsive stack) -->
    <div class="flex flex-wrap justify-center md:justify-center gap-3">
      <!-- Patients Link -->
      <a href="patients.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-user-injured"></i>
        <span>Patients</span>
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
        <!-- Notifications Alert -->
        <?php if (!empty($notifications)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 notification-alert">
                <h4 class="font-bold">Medicine Alerts!</h4>
                <?php foreach ($notifications as $notification): ?>
                    <p class="text-sm mt-1">⚠️ <?php echo htmlspecialchars($notification['message']); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Medicines</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo count($medicines); ?></p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Investment</h3>
                <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($total_investment); ?></p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Revenue (Sold)</h3>
                <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($total_revenue); ?></p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Actual Profit</h3>
                <p class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($total_profit); ?></p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Stock Value</h3>
                <p class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($total_potential_value); ?></p>
            </div>
        </div>

        <!-- Add Medicine Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Add Medicine</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Drug Name *</label>
                    <input type="text" name="drug_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="Enter drug name">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Amount Brought *</label>
                    <input type="number" name="amount_brought" required min="1" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="Number of tablets">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Buying Price *</label>
                    <input type="number" name="buying_price" required min="0" step="0.01" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="Cost price (Rs.)">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Selling Price *</label>
                    <input type="number" name="price_per_tablet" required min="0" step="0.01" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="Selling price (Rs.)">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Expiry Date</label>
                    <input type="date" name="expiry_date" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Notify When Below</label>
                    <input type="number" name="notify_threshold" value="10" min="1" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="Alert threshold">
                </div>
                
                <div class="md:col-span-3 lg:col-span-6 flex justify-end">
                    <button type="submit" name="add_medicine" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg">
                        Add Medicine
                    </button>
                </div>
            </form>
        </div>

        <!-- Medicines Table -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Medicine Store</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Drug Name</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Total Brought</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Current Stock</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Buying Price</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Selling Price</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Expiry Date</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Alert Level</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Last Updated</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicines as $medicine): ?>
                            <?php 
                                $is_low_stock = $medicine['current_stock'] <= $medicine['notify_threshold'];
                                $profit_per_unit = $medicine['price_per_tablet'] - $medicine['buying_price'];
                                
                                // Calculate actual profit from sold tablets
                                $total_medicine_profit = $medicine['total_sold'] * $profit_per_unit;
                                
                                // Current stock value (potential selling value)
                                $stock_value = $medicine['current_stock'] * $medicine['price_per_tablet'];
                                
                                $row_class = $is_low_stock ? 'bg-red-50 border-l-4 border-red-500' : 'border-b hover:bg-gray-50';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="px-4 py-2 font-medium">
                                    <?php echo htmlspecialchars($medicine['drug_name']); ?>
                                    <?php if ($is_low_stock): ?>
                                        <span class="text-red-500 text-xs">⚠️</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2"><?php echo number_format($medicine['amount_brought']); ?></td>
                                <td class="px-4 py-2">
                                    <span class="<?php echo $is_low_stock ? 'text-red-600 font-bold' : ''; ?>">
                                        <?php echo number_format($medicine['current_stock']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?php echo formatCurrency($medicine['buying_price']); ?></td>
                                <td class="px-4 py-2"><?php echo formatCurrency($medicine['price_per_tablet']); ?></td>
                                <td class="px-4 py-2">
                                    <?php if ($medicine['expiry_date']): ?>
                                        <?php 
                                            $expiry = new DateTime($medicine['expiry_date']);
                                            $today = new DateTime();
                                            $diff = $today->diff($expiry);
                                            $days_to_expiry = $expiry > $today ? $diff->days : -$diff->days;
                                            
                                            if ($days_to_expiry < 0) {
                                                echo '<span class="text-red-600 font-bold">Expired</span>';
                                            } elseif ($days_to_expiry <= 30) {
                                                echo '<span class="text-orange-600 font-bold">' . date('M d, Y', strtotime($medicine['expiry_date'])) . '</span>';
                                            } else {
                                                echo date('M d, Y', strtotime($medicine['expiry_date']));
                                            }
                                        ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2"><?php echo $medicine['notify_threshold']; ?></td>
                                <td class="px-4 py-2">
                                    <?php if ($medicine['current_stock'] <= 0): ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs">Out of Stock</span>
                                    <?php elseif ($is_low_stock): ?>
                                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Low Stock</span>
                                    <?php else: ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($medicine['updated_at'])); ?>
                                </td>
                                <td class="px-4 py-2">
                                    <button onclick="openUpdateModal(<?php echo $medicine['id']; ?>, '<?php echo htmlspecialchars($medicine['drug_name']); ?>', <?php echo $medicine['current_stock']; ?>, '<?php echo $medicine['expiry_date']; ?>')" 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs">
                                        Update
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-bold mb-4">Update Stock & Expiry</h3>
                <form method="POST" id="updateForm">
                    <input type="hidden" id="medicine_id" name="medicine_id" value="">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Medicine Name</label>
                        <input type="text" id="medicine_name" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Current Stock</label>
                        <input type="text" id="current_stock" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Add Stock Quantity *</label>
                        <input type="number" name="update_stock_quantity" id="update_stock_quantity" required min="1" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Enter quantity to add">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">New Expiry Date (Optional)</label>
                        <input type="date" name="new_expiry_date" id="new_expiry_date" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <small class="text-gray-500">Leave empty to keep current expiry date</small>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeUpdateModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                            Cancel
                        </button>
                        <button type="submit" name="update_stock" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                            Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-indigo-900 text-white mt-12 shadow-2xl print-hide" style="border-radius: 50px 50px 0 0;">
        <div class="container mx-auto px-6 py-8">
            <div class="text-center">
                <p class="text-indigo-300 text-sm mb-2">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
    <script>
        function openUpdateModal(id, name, currentStock, expiryDate) {
            console.log('Opening modal with:', id, name, currentStock, expiryDate);
            document.getElementById('medicine_id').value = id;
            document.getElementById('medicine_name').value = name;
            document.getElementById('current_stock').value = currentStock;
            document.getElementById('update_stock_quantity').value = ''; // Clear previous value
            document.getElementById('new_expiry_date').value = expiryDate || ''; // Set current expiry date or empty
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
            document.getElementById('updateForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUpdateModal();
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