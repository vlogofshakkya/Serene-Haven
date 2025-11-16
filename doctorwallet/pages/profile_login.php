<?php
session_start();
require_once '../config.php';

$error = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        // Find profile account by username
        $stmt = $pdo->prepare("
            SELECT pa.*, d.doctor_name, d.phone_number 
            FROM profile_accounts pa 
            JOIN doctors d ON pa.doctor_id = d.id 
            WHERE pa.username = ?
        ");
        $stmt->execute([$username]);
        $account = $stmt->fetch();
        
        if ($account && password_verify($password, $account['password'])) {
            // Set profile session variables
            $_SESSION['profile_logged_in'] = true;
            $_SESSION['profile_account_id'] = $account['id'];
            $_SESSION['doctor_id'] = $account['doctor_id'];
            $_SESSION['doctor_name'] = $account['doctor_name'];
            $_SESSION['user_type'] = 'doctor'; // Set this for compatibility
            $_SESSION['user_id'] = $account['doctor_id']; // Set this for compatibility
            
            header('Location: doctor_profile.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } catch (PDOException $e) {
        $error = 'Database error occurred. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Access - Doctor Wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <script src="https://cdn.tailwindcss.com"></script>

<style>

</style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="bg-blue-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h2 class="text-3xl font-bold text-gray-800">Doctor Profile Access</h2>
            <p class="text-gray-600 mt-2">Enter your profile credentials to access detailed summary</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Enter your username"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition duration-200">
            </div>

            <div>
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter your password"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition duration-200">
            </div>

            <button type="submit" name="login" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-105">
                🔓 Access Profile Dashboard
            </button>
        </form>

        <div class="text-center mt-6 space-y-2">
            <a href="../index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium block">
                ← Back to Main Dashboard
            </a>
            <p class="text-xs text-gray-500">
                Use the username and password created by the administrator
            </p>
        </div>

        <!-- Demo credentials (remove in production) -->
        <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h3 class="text-sm font-semibold text-yellow-800 mb-2">If you didn't remember the password:</h3>
            <ol class="text-xs text-yellow-700 space-y-1">
                <li>Please contact admin to recover it</li>
            </ol>
        </div>
    </div>
</body>
<script>

</sctipt>
</html>