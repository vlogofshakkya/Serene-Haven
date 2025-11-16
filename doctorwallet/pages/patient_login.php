<?php
require_once '../config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['patient_logged_in']) && $_SESSION['patient_logged_in'] === true) {
    header('Location: patient_s.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($phone) || empty($password)) {
        $error = 'Please enter both phone number and password.';
    } else {
        // Check in patient_accounts table
        $stmt = $pdo->prepare("
            SELECT pa.*, pa.patient_type, pa.patient_id, pa.doctor_id,
                   CASE 
                       WHEN pa.patient_type = 'adult' THEN a.name
                       WHEN pa.patient_type = 'kid' THEN k.name
                   END as patient_name,
                   CASE 
                       WHEN pa.patient_type = 'adult' THEN a.phone_number
                       WHEN pa.patient_type = 'kid' THEN ap.phone_number
                   END as patient_phone
            FROM patient_accounts pa
            LEFT JOIN adults a ON pa.patient_type = 'adult' AND pa.patient_id = a.id
            LEFT JOIN kids k ON pa.patient_type = 'kid' AND pa.patient_id = k.id
            LEFT JOIN adults ap ON pa.patient_type = 'kid' AND k.parent_id = ap.id
            WHERE pa.phone_number = ? AND pa.is_active = 1
        ");
        $stmt->execute([$phone]);
        $account = $stmt->fetch();
        
        if ($account && password_verify($password, $account['password'])) {
            // Set session variables
            $_SESSION['patient_logged_in'] = true;
            $_SESSION['patient_account_id'] = $account['id'];
            $_SESSION['patient_id'] = $account['patient_id'];
            $_SESSION['patient_type'] = $account['patient_type'];
            $_SESSION['patient_doctor_id'] = $account['doctor_id'];
            $_SESSION['patient_name'] = $account['patient_name'];
            $_SESSION['patient_phone'] = $account['phone_number'];
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE patient_accounts SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$account['id']]);
            
            header('Location: patient_s.php');
            exit;
        } else {
            $error = 'Invalid phone number or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Logo/Header -->
        <div class="text-center mb-8 floating">
            <img src="../iconbgr_b.png" alt="Logo" class="h-20 w-auto mx-auto mb-4">
            <h1 class="text-4xl font-bold text-white mb-2">Patient Portal</h1>
            <p class="text-white text-opacity-90">Access your health records</p>
        </div>

        <!-- Login Card -->
        <div class="login-card rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-circle text-3xl text-white"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Welcome Back</h2>
                <p class="text-gray-600 text-sm">Sign in to your patient account</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-phone mr-2 text-blue-600"></i>Phone Number
                    </label>
                    <input type="tel" 
                           name="phone" 
                           required 
                           placeholder="Enter your phone number"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-lock mr-2 text-blue-600"></i>Password
                    </label>
                    <input type="password" 
                           name="password" 
                           required 
                           placeholder="Enter your password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 rounded-lg font-bold hover:from-blue-700 hover:to-purple-700 transition duration-300 shadow-lg transform hover:scale-105">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600 text-sm">Don't have an account?</p>
                <a href="patient_register.php" 
                   class="text-blue-600 hover:text-blue-800 font-bold text-sm mt-2 inline-block">
                    <i class="fas fa-user-plus mr-1"></i>Register Now
                </a>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                <p class="text-gray-500 text-xs">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Your data is secure and encrypted
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-white text-sm">
            <p class="text-opacity-80">
                <i class="fas fa-question-circle mr-1"></i>
                Need help? Contact your doctor's clinic
            </p>
        </div>
    </div>
</body>
</html>