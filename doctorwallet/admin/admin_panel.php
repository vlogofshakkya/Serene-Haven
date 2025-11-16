<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any early output
ob_start();

try {
    // Check if config file exists
    $config_path = '../config.php';
    if (!file_exists($config_path)) {
        throw new Exception("Config file not found at: " . $config_path);
    }
    
    require_once $config_path;
    
    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception("Database connection (PDO) not found in config file");
    }
    
    // Test database connection
    $pdo->query("SELECT 1");
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Debug - Admin Panel Error - Doctor wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-red-50 min-h-screen flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg p-8 max-w-2xl">
            <h1 class="text-2xl font-bold text-red-600 mb-4">⚠️ System Error Detected</h1>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            
            <h2 class="text-lg font-semibold mb-3">Troubleshooting Steps:</h2>
            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                <li>Check if <code class="bg-gray-100 px-2 py-1 rounded">config.php</code> exists in the parent directory</li>
                <li>Verify database connection settings in config.php</li>
                <li>Ensure the <code class="bg-gray-100 px-2 py-1 rounded">profile_accounts</code> table exists</li>
                <li>Check server error logs for more details</li>
            </ol>
            
            <div class="mt-6 p-4 bg-blue-50 rounded">
                <h3 class="font-semibold text-blue-800">Current Environment:</h3>
                <p class="text-sm text-blue-700">PHP Version: <?php echo PHP_VERSION; ?></p>
                <p class="text-sm text-blue-700">Server Software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                <p class="text-sm text-blue-700">Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    // Check if required tables exist
    $tables_to_check = ['doctors', 'profile_accounts', 'staff'];
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            throw new Exception("Required table '$table' does not exist");
        }
    }
    
    // Get all doctors
    $stmt = $pdo->query("SELECT * FROM doctors ORDER BY doctor_name");
    $doctors = $stmt->fetchAll();

    // Get all profile accounts with doctor information
    $stmt = $pdo->query("
        SELECT pa.*, d.doctor_name, d.phone_number
        FROM profile_accounts pa 
        JOIN doctors d ON pa.doctor_id = d.id 
        ORDER BY d.doctor_name
    ");
    $profile_accounts = $stmt->fetchAll();

    // Get system statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff");
    $total_staff = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error - Admin Panel - Doctor wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-red-50 min-h-screen flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg p-8 max-w-2xl">
            <h1 class="text-2xl font-bold text-red-600 mb-4">🗃️ Database Error</h1>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Database Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            
            <h2 class="text-lg font-semibold mb-3">Possible Solutions:</h2>
            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                <li>Run the SQL script to create the <code class="bg-gray-100 px-2 py-1 rounded">profile_accounts</code> table</li>
                <li>Check if the doctors table has an email column (may need to add it)</li>
                <li>Verify all foreign key constraints are properly set</li>
                <li>Ensure the database user has proper permissions</li>
            </ol>
            
            <div class="mt-6">
                <a href="ad_index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Back to Main Site
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle profile account creation
if (isset($_POST['create_account'])) {
    $doctor_id = $_POST['doctor_id'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO profile_accounts (doctor_id, username, password) VALUES (?, ?, ?)");
        $stmt->execute([$doctor_id, $username, $password]);
        $success = 'Profile account created successfully';
        
        // Refresh the profile accounts list
        $stmt = $pdo->query("
            SELECT pa.*, d.doctor_name, d.phone_number
            FROM profile_accounts pa 
            JOIN doctors d ON pa.doctor_id = d.id 
            ORDER BY d.doctor_name
        ");
        $profile_accounts = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = 'Username already exists. Please choose a different username.';
        } else {
            $error = 'Error creating account: ' . $e->getMessage();
        }
    }
}

// Handle account deletion
if (isset($_POST['delete_account'])) {
    $account_id = $_POST['account_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM profile_accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        $success = 'Account deleted successfully';
        
        // Refresh the profile accounts list
        $stmt = $pdo->query("
            SELECT pa.*, d.doctor_name, d.phone_number
            FROM profile_accounts pa 
            JOIN doctors d ON pa.doctor_id = d.id 
            ORDER BY d.doctor_name
        ");
        $profile_accounts = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = 'Error deleting account: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Account Management - Doctor Wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-green-800 text-white shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="bg-green-500 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">Profile Account Management</h1>
                        <p class="text-green-200">Create and manage doctor profile accounts</p>
                    </div>
                </div>
                <div class="flex space-x-4">
                    <a href="ad_index.php" class="bg-green-700 hover:bg-green-800 px-4 py-2 rounded-lg transition duration-200">
                        Back to Main Site
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        <!-- Success Message -->
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            🎉 Profile Account Management System is ready! Create accounts for doctors to access their profiles.
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            ✅ <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- System Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-blue-600 text-white rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100">Total Doctors</p>
                        <p class="text-3xl font-bold"><?php echo count($doctors); ?></p>
                    </div>
                    <div class="bg-blue-500 bg-opacity-50 p-3 rounded-full">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-green-600 text-white rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">Profile Accounts</p>
                        <p class="text-3xl font-bold"><?php echo count($profile_accounts); ?></p>
                    </div>
                    <div class="bg-green-500 bg-opacity-50 p-3 rounded-full">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-purple-600 text-white rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100">Total Staff</p>
                        <p class="text-3xl font-bold"><?php echo $total_staff; ?></p>
                    </div>
                    <div class="bg-purple-500 bg-opacity-50 p-3 rounded-full">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-orange-600 text-white rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100">System Status</p>
                        <p class="text-lg font-bold">✅ Ready</p>
                    </div>
                    <div class="bg-orange-500 bg-opacity-50 p-3 rounded-full">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Profile Account Form -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Create New Profile Account</h2>
            
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="doctor_id" class="block text-gray-700 text-sm font-semibold mb-2">Select Doctor</label>
                    <select id="doctor_id" name="doctor_id" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200">
                        <option value="">Choose Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>">
                            <?php echo htmlspecialchars($doctor['doctor_name']); ?> - <?php echo htmlspecialchars($doctor['phone_number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter unique username"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200">
                </div>

                <div>
                    <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter secure password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200">
                </div>

                <div class="md:col-span-3">
                    <button type="submit" name="create_account" 
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 transform hover:scale-105">
                        Create Profile Account
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing Profile Accounts -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Existing Profile Accounts</h2>
            
            <?php if (count($profile_accounts) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($profile_accounts as $account): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-white text-sm font-bold">
                                            <?php echo strtoupper(substr($account['doctor_name'], 0, 2)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($account['doctor_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">ID: <?php echo $account['doctor_id']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-sm bg-green-100 text-green-800 rounded-full font-medium">
                                    <?php echo htmlspecialchars($account['username']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($account['phone_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($account['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this account?')">
                                    <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                    <button type="submit" name="delete_account" 
                                            class="text-red-600 hover:text-red-900 transition duration-200 font-medium">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <p class="text-lg">No profile accounts created yet.</p>
                <p class="text-sm">Create the first profile account using the form above.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mt-8">
            <h3 class="text-lg font-semibold text-blue-800 mb-3">📋 Instructions</h3>
            <ul class="text-blue-700 space-y-2">
                <li>• Select a doctor from the dropdown to create their profile account</li>
                <li>• Choose a unique username that the doctor will use to log in</li>
                <li>• Set a secure password for the account</li>
                <li>• Each doctor can only have one profile account</li>
                <li>• Doctors will use these credentials to access their profile dashboard</li>
            </ul>
        </div>
    </div>
</body>
</html>