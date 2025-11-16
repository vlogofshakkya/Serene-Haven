<?php
require_once 'config.php';

$error = '';

if ($_POST) {
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    if ($phone && $password && $user_type) {
        $table = $user_type === 'doctor' ? 'doctors' : 'staff';
        $name_field = $user_type === 'doctor' ? 'doctor_name' : 'name';
        
        $stmt = $pdo->prepare("SELECT id, $name_field as name, password FROM $table WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $user['name'];
            
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid phone number or password';
        }
    } else {
        $error = 'Please fill all fields';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Wallet - Login</title>
    <!-- Favicon (modern browsers) -->
    <link rel="icon" type="image/png" sizes="32x32" href="icon.png">
    <!-- High-res favicon -->
    <link rel="icon" type="image/png" sizes="192x192" href="icon.png">
    <!-- Apple touch icon (iOS home screen) -->
    <link rel="apple-touch-icon" sizes="180x180" href="icon.png">
    <!-- Safari pinned tab (monochrome SVG) -->
    <link rel="mask-icon" href="icon.svg" color="#0F2E44">
    
<!-- Microsoft tile icon -->
<meta name="msapplication-TileImage" content="icon.png">
<meta name="msapplication-TileColor" content="#0F2E44">

<!-- Theme color for mobile browsers -->
<meta name="theme-color" content="#0F2E44">

<link rel="manifest" href="manifest.json">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Loading Screen Styles */
        #loadingScreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.8s ease-out, visibility 0.8s ease-out;
        }

        #loadingScreen.hide {
            opacity: 0;
            visibility: hidden;
        }

        .loading-logo {
            width: 280px;
            height: 280px;
            margin-bottom: 40px;
            animation: floatAndPulse 3s ease-in-out infinite;
            filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.3));
        }

        @keyframes floatAndPulse {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-20px) scale(1.05);
            }
        }

        .loading-text {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 30px;
            animation: fadeInOut 2s ease-in-out infinite;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        @keyframes fadeInOut {
            0%, 100% {
                opacity: 0.6;
            }
            50% {
                opacity: 1;
            }
        }

        /* Modern Loading Bar */
        .loading-bar-container {
            width: 320px;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .loading-bar {
            height: 100%;
            background: linear-gradient(90deg, #fff, #a8edea, #fed6e3, #fff);
            background-size: 200% 100%;
            border-radius: 10px;
            animation: loadingProgress 5s cubic-bezier(0.4, 0, 0.2, 1) forwards,
                       shimmer 2s linear infinite;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
        }

        @keyframes loadingProgress {
            from {
                width: 0%;
            }
            to {
                width: 100%;
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }

        /* Rotating particles around logo */
        .particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: orbit 4s linear infinite;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
        }

        .particle:nth-child(1) {
            animation-delay: 0s;
        }

        .particle:nth-child(2) {
            animation-delay: -1s;
        }

        .particle:nth-child(3) {
            animation-delay: -2s;
        }

        .particle:nth-child(4) {
            animation-delay: -3s;
        }

        @keyframes orbit {
            0% {
                transform: rotate(0deg) translateX(160px) rotate(0deg);
                opacity: 0.3;
            }
            50% {
                opacity: 1;
            }
            100% {
                transform: rotate(360deg) translateX(160px) rotate(-360deg);
                opacity: 0.3;
            }
        }

        /* Background Image Styles */
        body {
            background-image: url('bg1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* Responsive background overlay for readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        /* Enhanced form container with glassmorphism */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile responsive adjustments */
        @media (max-width: 640px) {
            .loading-logo {
                width: 200px;
                height: 200px;
            }

            .loading-text {
                font-size: 1.5rem;
            }

            .loading-bar-container {
                width: 260px;
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <!-- Loading Screen -->
    <div id="loadingScreen">
        <div style="position: relative;">
            <img src="li.png" alt="Doctor Wallet" class="loading-logo">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        <div class="loading-text">Doctor Wallet</div>
        <div class="loading-bar-container">
            <div class="loading-bar"></div>
        </div>
        <div style="color: rgba(255, 255, 255, 0.8); margin-top: 20px; font-size: 0.9rem;">
            Loading your private practice management...
        </div>
    </div>

    <!-- Main Login Content -->
    <div class="form-container rounded-lg shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Doctor Wallet</h1>
            <p class="text-gray-600 mt-2">Private Practice Management</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">User Type</label>
                <select name="user_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select User Type</option>
                    <option value="doctor" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                    <option value="staff" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'staff') ? 'selected' : ''; ?>>Staff</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                <input type="tel" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                       placeholder="Enter phone number">
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                       placeholder="Enter password">
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Login
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="register.php" class="text-blue-500 hover:text-blue-600 text-sm">Create New Account</a>
        </div>

        <div class="mt-4 text-xs text-gray-500 text-center">
            <p>Default Login:</p>
            <p>Doctor: 0753374974 / 123456</p>
            <p>Staff: 0753374975 / 123456</p>
        </div>
    </div>

    <script>
        // Check if loading screen has been shown before
        window.addEventListener('load', function() {
            // Check if this is the first visit
            var hasSeenLoading = sessionStorage.getItem('hasSeenLoading');
            
            if (hasSeenLoading) {
                // If already seen, hide immediately
                document.getElementById('loadingScreen').style.display = 'none';
            } else {
                // First time - show loading for 5 seconds
                setTimeout(function() {
                    document.getElementById('loadingScreen').classList.add('hide');
                    // Mark as seen in session storage
                    sessionStorage.setItem('hasSeenLoading', 'true');
                }, 5000);
            }
        });
    </script>
</body>
</html>