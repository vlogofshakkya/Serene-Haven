<?php
require_once '../config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to main page
if (isset($_SESSION['lab_user_id']) && $_SESSION['lab_user_type'] === 'laboratory') {
    header('Location: lab_reports.php');
    exit;
}

$message = '';
$error = '';
$show_register = isset($_GET['register']) ? true : false;

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $lab_name = trim($_POST['lab_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address'] ?? '');
    $registration_number = trim($_POST['registration_number'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($lab_name) || empty($contact_person) || empty($email) || empty($phone_number) || empty($password)) {
        $error = 'Please fill all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            // Check if email or phone already exists
            $stmt = $pdo->prepare("SELECT id FROM laboratory_users WHERE email = ? OR phone_number = ?");
            $stmt->execute([$email, $phone_number]);
            
            if ($stmt->fetch()) {
                $error = 'Email or phone number already registered.';
            } else {
                // Insert new laboratory user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO laboratory_users 
                    (lab_name, contact_person, email, phone_number, address, registration_number, password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $lab_name, 
                    $contact_person, 
                    $email, 
                    $phone_number, 
                    $address, 
                    $registration_number, 
                    $hashed_password
                ]);
                
                // Get the new user ID
                $new_user_id = $pdo->lastInsertId();
                
                // Auto login after registration
                $_SESSION['lab_user_id'] = $new_user_id;
                $_SESSION['lab_user_type'] = 'laboratory';
                $_SESSION['lab_name'] = $lab_name;
                
                header('Location: lab_reports.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['login_email']);
    $password = $_POST['login_password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM laboratory_users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['lab_user_id'] = $user['id'];
                $_SESSION['lab_user_type'] = 'laboratory';
                $_SESSION['lab_name'] = $user['lab_name'];
                
                header('Location: lab_reports.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Login - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .form-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.3s ease;
        }
        
        .form-container:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .input-field {
            padding-left: 40px;
        }
        
        .tab-button {
            position: relative;
            overflow: hidden;
        }
        
        .tab-button::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .tab-button.active::after {
            transform: scaleX(1);
        }
        
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
        
        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo/Header -->
        <div class="text-center mb-8 animate-fadeInUp">
            <div class="inline-block p-4 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-flask text-5xl text-purple-600"></i>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">Laboratory Portal</h1>
            <p class="text-purple-200">Upload and manage lab reports</p>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 animate-fadeInUp">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 animate-fadeInUp">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container rounded-2xl shadow-2xl p-8 animate-fadeInUp" style="animation-delay: 0.1s;">
            <!-- Tabs -->
            <div class="flex mb-6 border-b-2 border-gray-200">
                <button id="loginTab" class="tab-button flex-1 py-3 font-semibold text-gray-700 transition-colors <?php echo !$show_register ? 'active text-purple-600' : ''; ?>" onclick="showLogin()">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
                <button id="registerTab" class="tab-button flex-1 py-3 font-semibold text-gray-700 transition-colors <?php echo $show_register ? 'active text-purple-600' : ''; ?>" onclick="showRegister()">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </button>
            </div>

            <!-- Login Form -->
            <div id="loginForm" class="<?php echo $show_register ? 'hidden' : ''; ?>">
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div class="input-group">
                            <input type="email" 
                                   name="login_email" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Email Address" 
                                   required>
                        </div>

                        <div class="input-group">
                            <input type="password" 
                                   name="login_password" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Password" 
                                   required>
                        </div>

                        <button type="submit" 
                                name="login" 
                                class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login
                        </button>
                    </div>
                </form>
            </div>

            <!-- Register Form -->
            <div id="registerForm" class="<?php echo !$show_register ? 'hidden' : ''; ?>">
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div class="input-group">
                            <input type="text" 
                                   name="lab_name" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Laboratory Name *" 
                                   required>
                        </div>

                        <div class="input-group">
                            <input type="text" 
                                   name="contact_person" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Contact Person *" 
                                   required>
                        </div>

                        <div class="input-group">
                            <input type="email" 
                                   name="email" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Email Address *" 
                                   required>
                        </div>

                        <div class="input-group">
                            <input type="tel" 
                                   name="phone_number" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Phone Number *" 
                                   required>
                        </div>

                        <div class="input-group">
                            <textarea name="address" 
                                      class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                      placeholder="Address (Optional)" 
                                      rows="2"></textarea>
                        </div>

                        <div class="input-group">
                            <input type="text" 
                                   name="registration_number" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Registration Number (Optional)">
                        </div>

                        <div class="input-group">
                            <input type="password" 
                                   name="password" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Password (min 6 characters) *" 
                                   required 
                                   minlength="6">
                        </div>

                        <div class="input-group">
                            <input type="password" 
                                   name="confirm_password" 
                                   class="input-field w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 transition-colors" 
                                   placeholder="Confirm Password *" 
                                   required>
                        </div>

                        <button type="submit" 
                                name="register" 
                                class="w-full bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white font-bold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-user-plus mr-2"></i>Register
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Back to Main Site -->
        <div class="text-center mt-6">
            <a href="http://thedcwallet.ct.ws" class="text-white hover:text-purple-200 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Main Site
            </a>
        </div>
    </div>

    <script>
        function showLogin() {
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('registerForm').classList.add('hidden');
            document.getElementById('loginTab').classList.add('active', 'text-purple-600');
            document.getElementById('registerTab').classList.remove('active', 'text-purple-600');
            
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.delete('register');
            window.history.pushState({}, '', url);
        }

        function showRegister() {
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.remove('hidden');
            document.getElementById('loginTab').classList.remove('active', 'text-purple-600');
            document.getElementById('registerTab').classList.add('active', 'text-purple-600');
            
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('register', '1');
            window.history.pushState({}, '', url);
        }
    </script>
</body>
</html>