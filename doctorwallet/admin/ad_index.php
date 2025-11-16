<?php
require_once '../config.php';

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors");
$total_doctors = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM staff");
$total_staff = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM profile_accounts");
$total_profiles = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM sms_sender_ids WHERE is_active = 1");
$active_senders = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM doctor_images WHERE is_active = 1");
$total_images = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg">
        <div class="container mx-auto px-6 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <i class="fas fa-user-shield text-3xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">Admin Dashboard</h1>
                        <p class="text-blue-100">Doctor Wallet Management System</p>
                    </div>
                </div>
                <a href="../index.php" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-lg font-semibold transition duration-200 shadow-md">
                    <i class="fas fa-home mr-2"></i>Main Site
                </a>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl p-6 shadow-lg transform hover:scale-105 transition duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Doctors</p>
                        <p class="text-4xl font-bold mt-2"><?php echo $total_doctors; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-full">
                        <i class="fas fa-user-md text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl p-6 shadow-lg transform hover:scale-105 transition duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Staff Members</p>
                        <p class="text-4xl font-bold mt-2"><?php echo $total_staff; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-full">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl p-6 shadow-lg transform hover:scale-105 transition duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Profile Accounts</p>
                        <p class="text-4xl font-bold mt-2"><?php echo $total_profiles; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-full">
                        <i class="fas fa-id-card text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl p-6 shadow-lg transform hover:scale-105 transition duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">SMS Senders</p>
                        <p class="text-4xl font-bold mt-2"><?php echo $active_senders; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-full">
                        <i class="fas fa-sms text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-pink-500 to-pink-600 text-white rounded-xl p-6 shadow-lg transform hover:scale-105 transition duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-pink-100 text-sm font-medium">Doctor Images</p>
                        <p class="text-4xl font-bold mt-2"><?php echo $total_images; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-4 rounded-full">
                        <i class="fas fa-image text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Modules -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-th-large text-blue-600 mr-3"></i>
                Admin Modules
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Doctor Management -->
                <a href="ad_dc_mng.php" class="group bg-gradient-to-br from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 rounded-xl p-6 border-2 border-blue-200 transition duration-300 transform hover:scale-105 hover:shadow-xl">
                    <div class="flex items-start space-x-4">
                        <div class="bg-blue-600 text-white p-4 rounded-lg group-hover:bg-blue-700 transition duration-300">
                            <i class="fas fa-user-md text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Doctor Management</h3>
                            <p class="text-sm text-gray-600">View, edit, and manage all doctor details including SLMC numbers and staff assignments</p>
                        </div>
                    </div>
                </a>

                <!-- Profile Accounts -->
                <a href="admin_panel.php" class="group bg-gradient-to-br from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 rounded-xl p-6 border-2 border-green-200 transition duration-300 transform hover:scale-105 hover:shadow-xl">
                    <div class="flex items-start space-x-4">
                        <div class="bg-green-600 text-white p-4 rounded-lg group-hover:bg-green-700 transition duration-300">
                            <i class="fas fa-id-card text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Profile Accounts</h3>
                            <p class="text-sm text-gray-600">Create and manage doctor profile login accounts</p>
                        </div>
                    </div>
                </a>

                <!-- SMS Management -->
                <a href="sms_ad.php" class="group bg-gradient-to-br from-orange-50 to-orange-100 hover:from-orange-100 hover:to-orange-200 rounded-xl p-6 border-2 border-orange-200 transition duration-300 transform hover:scale-105 hover:shadow-xl">
                    <div class="flex items-start space-x-4">
                        <div class="bg-orange-600 text-white p-4 rounded-lg group-hover:bg-orange-700 transition duration-300">
                            <i class="fas fa-sms text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">SMS Management</h3>
                            <p class="text-sm text-gray-600">Manage SMS sender IDs, configure doctor SMS units and settings</p>
                        </div>
                    </div>
                </a>

                <!-- Doctor Images -->
                <a href="DIU.php" class="group bg-gradient-to-br from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 rounded-xl p-6 border-2 border-purple-200 transition duration-300 transform hover:scale-105 hover:shadow-xl">
                    <div class="flex items-start space-x-4">
                        <div class="bg-purple-600 text-white p-4 rounded-lg group-hover:bg-purple-700 transition duration-300">
                            <i class="fas fa-image text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Doctor Images</h3>
                            <p class="text-sm text-gray-600">Upload and manage doctor logos, signatures, and seals</p>
                        </div>
                    </div>
                </a>

                <!-- System Settings (Placeholder) -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 border-2 border-gray-200 opacity-60">
                    <div class="flex items-start space-x-4">
                        <div class="bg-gray-400 text-white p-4 rounded-lg">
                            <i class="fas fa-cog text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">System Settings</h3>
                            <p class="text-sm text-gray-600">Coming soon...</p>
                        </div>
                    </div>
                </div>

                <!-- Reports (Placeholder) -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 border-2 border-gray-200 opacity-60">
                    <div class="flex items-start space-x-4">
                        <div class="bg-gray-400 text-white p-4 rounded-lg">
                            <i class="fas fa-chart-bar text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Reports</h3>
                            <p class="text-sm text-gray-600">Coming soon...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-start space-x-4">
                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                    <i class="fas fa-info-circle text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-2">Admin Panel Information</h3>
                    <ul class="space-y-2 text-blue-100">
                        <li><i class="fas fa-check-circle mr-2"></i>Manage all doctor profiles and credentials</li>
                        <li><i class="fas fa-check-circle mr-2"></i>Configure SMS settings and sender IDs</li>
                        <li><i class="fas fa-check-circle mr-2"></i>Upload and manage doctor images for receipts</li>
                        <li><i class="fas fa-check-circle mr-2"></i>Monitor system statistics and usage</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6">
        <div class="container mx-auto px-6 text-center text-gray-600">
            <p>&copy; <?php echo date('Y'); ?> Doctor Wallet - Admin Dashboard. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>