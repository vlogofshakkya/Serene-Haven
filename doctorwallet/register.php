<?php
require_once 'config.php';

$message = '';
$error = '';

if ($_POST) {
    $user_type = $_POST['user_type'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            if ($user_type === 'staff') {
                $stmt = $pdo->prepare("INSERT INTO staff (name, phone_number, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $phone, $hashed_password]);
            } else {
                $staff_id = $_POST['staff_id'] ? $_POST['staff_id'] : null;
                $slmc_no = trim($_POST['slmc_no']);
                $stmt = $pdo->prepare("INSERT INTO doctors (doctor_name, phone_number, slmc_no, staff_member_id, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $slmc_no, $staff_id, $hashed_password]);
            }
            
            $message = ucfirst($user_type) . ' account created successfully!';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                if (strpos($e->getMessage(), 'phone_number') !== false) {
                    $error = 'Phone number already exists';
                } elseif (strpos($e->getMessage(), 'slmc_no') !== false) {
                    $error = 'SLMC Number already exists';
                } else {
                    $error = 'Phone number or SLMC Number already exists';
                }
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$staff_members = [];
if (!$error && !$message) {
    $stmt = $pdo->query("SELECT id, name, phone_number FROM staff ORDER BY name");
    $staff_members = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Wallet - Register</title>
    <link rel="icon" type="image/png" sizes="32x32" href="icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icon.png">
    <link rel="mask-icon" href="icon.svg" color="#0F2E44">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-image: url('bg1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.35);
            z-index: -1;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            animation: slideUp 0.8s ease-out;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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

        input, select {
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        button[type="submit"] {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button[type="submit"]:hover::before {
            width: 300px;
            height: 300px;
        }

        @media (max-width: 640px) {
            .form-container {
                margin: 1rem;
            }
        }

        @media (max-height: 700px) {
            body {
                background-attachment: scroll;
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="form-container rounded-lg p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Create Account</h1>
            <p class="text-gray-600 mt-2">Doctor Wallet Registration</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
                <div class="mt-2">
                    <a href="login.php" class="text-green-600 underline">Go to Login</a>
                </div>
            </div>
        <?php else: ?>

        <form method="POST" id="registerForm">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Account Type</label>
                <select name="user_type" id="user_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">Select Account Type</option>
                    <option value="staff" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'staff') ? 'selected' : ''; ?>>Staff</option>
                    <option value="doctor" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" 
                       placeholder="Enter full name">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                <input type="tel" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" 
                       placeholder="Enter phone number">
            </div>

            <div id="slmc_field" class="mb-4" style="display: none;">
                <label class="block text-gray-700 text-sm font-bold mb-2">SLMC Number</label>
                <input type="text" name="slmc_no" value="<?php echo htmlspecialchars($_POST['slmc_no'] ?? ''); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" 
                       placeholder="Enter SLMC registration number">
            </div>

            <div id="staff_selection" class="mb-4" style="display: none;">
                <label class="block text-gray-700 text-sm font-bold mb-2">Link Staff Member (Optional)</label>
                <select name="staff_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">Select Staff Member</option>
                    <?php foreach ($staff_members as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php echo (isset($_POST['staff_id']) && $_POST['staff_id'] == $staff['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['name'] . ' - ' . $staff['phone_number']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" 
                       placeholder="Enter password (min 6 characters)">
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="6"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" 
                       placeholder="Confirm password">
            </div>

            <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Create Account
            </button>
        </form>

        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="login.php" class="text-blue-500 hover:text-blue-600 text-sm">Already have an account? Login</a>
        </div>
    </div>

    <script>
    $('#user_type').change(function() {
        if ($(this).val() === 'doctor') {
            $('#staff_selection').slideDown(300);
            $('#slmc_field').slideDown(300);
        } else {
            $('#staff_selection').slideUp(300);
            $('#slmc_field').slideUp(300);
        }
    });

    $(document).ready(function() {
        if ($('#user_type').val() === 'doctor') {
            $('#staff_selection').show();
            $('#slmc_field').show();
        }
    });
    </script>
</body>
</html>