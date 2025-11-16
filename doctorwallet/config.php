<?php
// Set timezone to Sri Lanka first (before any date operations)
date_default_timezone_set('Asia/Colombo');

// Check if this is an AJAX request
$is_ajax_request = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
) || (
    strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') !== false
);

// Configure error reporting based on request type
if ($is_ajax_request) {
    // For AJAX requests: turn off error display completely to prevent JSON corruption
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0); // Turn off all error reporting for AJAX
} else {
    // For regular pages: enable error reporting for debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Database configuration
define('DB_HOST', 'sql306.infinityfree.com');
define('DB_USER', 'if0_39781227');
define('DB_PASS', 'docwallet');
define('DB_NAME', 'if0_39781227_doc');

// Alternative database configurations to try
$db_configs = [
    // Primary configuration
    [
        'host' => 'sql306.infinityfree.com',
        'user' => 'if0_39781227',
        'pass' => 'docwallet',
        'name' => 'if0_39781227_doc'
    ],
    // Alternative configuration 1
    [
        'host' => 'sql306.infinityfree.com',
        'user' => 'if0_39781227',
        'pass' => 'docwallet',
        'name' => 'doc'
    ],
    // Alternative configuration 2 (different port)
    [
        'host' => 'sql306.infinityfree.com:3306',
        'user' => 'if0_39781227',
        'pass' => 'docwallet',
        'name' => 'if0_39781227_doc'
    ]
];

$pdo = null;
$connection_error = '';

// Try each configuration
foreach ($db_configs as $index => $config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_TIMEOUT => 10, // 10 second timeout
        ];
        
        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        
        // Set MySQL timezone to Sri Lanka Standard Time
        $pdo->exec("SET time_zone = '+05:30'");
        
        // Test the connection
        $pdo->query('SELECT 1');
        
        // Log success to error log instead of echoing (prevents AJAX issues)
        if (!$is_ajax_request) {
            error_log("Database connection successful using configuration " . ($index + 1));
        }
        break;
        
    } catch(PDOException $e) {
        $connection_error = "Config " . ($index + 1) . ": " . $e->getMessage();
        error_log("Database connection attempt " . ($index + 1) . " failed: " . $e->getMessage());
        continue;
    }
}

// If no connection was established
if ($pdo === null) {
    if ($is_ajax_request) {
        // For AJAX requests, return JSON error
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'details' => $connection_error
        ]);
        exit();
    } else {
        // For regular requests, show error page
        $error_details = [
            'timestamp' => date('Y-m-d H:i:s'),
            'server_info' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'php_version' => PHP_VERSION,
            'last_error' => $connection_error,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Connection Error - DocWallet</title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .error-container {
                    background: white;
                    border-radius: 10px;
                    padding: 30px;
                    max-width: 600px;
                    width: 100%;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                }
                .error-icon {
                    font-size: 48px;
                    color: #e74c3c;
                    text-align: center;
                    margin-bottom: 20px;
                }
                .error-title {
                    color: #2c3e50;
                    font-size: 24px;
                    font-weight: bold;
                    text-align: center;
                    margin-bottom: 20px;
                }
                .error-message {
                    color: #555;
                    line-height: 1.6;
                    margin-bottom: 25px;
                    text-align: center;
                }
                .error-details {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 5px;
                    padding: 15px;
                    font-family: monospace;
                    font-size: 14px;
                    margin-bottom: 25px;
                    overflow-x: auto;
                }
                .solutions {
                    background: #e3f2fd;
                    border: 1px solid #2196f3;
                    border-radius: 5px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .solutions h3 {
                    color: #1976d2;
                    margin-top: 0;
                    margin-bottom: 15px;
                }
                .solutions ol {
                    margin: 0;
                    padding-left: 20px;
                }
                .solutions li {
                    margin-bottom: 8px;
                    color: #555;
                }
                .retry-btn {
                    display: block;
                    width: 100%;
                    padding: 12px;
                    background: #007bff;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    text-align: center;
                    font-weight: bold;
                    transition: background 0.3s;
                }
                .retry-btn:hover {
                    background: #0056b3;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">Database Error</div>
                <h1 class="error-title">Database Connection Failed</h1>
                <p class="error-message">
                    Unable to connect to the database. This is usually due to incorrect database credentials or server issues.
                </p>
                
                <div class="error-details">
                    <strong>Error Details:</strong><br>
                    <?php echo htmlspecialchars($connection_error); ?><br><br>
                    <strong>Timestamp:</strong> <?php echo $error_details['timestamp']; ?><br>
                    <strong>Server:</strong> <?php echo $error_details['server_info']; ?><br>
                    <strong>PHP Version:</strong> <?php echo $error_details['php_version']; ?>
                </div>

                <div class="solutions">
                    <h3>Possible Solutions:</h3>
                    <ol>
                        <li><strong>Check Database Name:</strong> InfinityFree usually prefixes database names with your account ID</li>
                        <li><strong>Verify Credentials:</strong> Ensure username and password are correct in your hosting control panel</li>
                        <li><strong>Database Host:</strong> Confirm the correct MySQL hostname from your hosting provider</li>
                        <li><strong>Database Exists:</strong> Make sure the database was created successfully</li>
                        <li><strong>User Permissions:</strong> Verify the database user has proper permissions</li>
                    </ol>
                </div>

                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="retry-btn">Retry Connection</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ==================== AUTHENTICATION FUNCTIONS ====================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function isDoctor() {
    return isLoggedIn() && $_SESSION['user_type'] === 'doctor';
}

function isStaff() {
    return isLoggedIn() && $_SESSION['user_type'] === 'staff';
}

function requireLogin() {
    if (!isLoggedIn()) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'login.php') {
            header('Location: login.php');
            exit();
        }
    }
}

function requireDoctor() {
    if (!isDoctor()) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'login.php') {
            header('Location: login.php');
            exit();
        }
    }
}

function requireStaff() {
    if (!isStaff()) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'login.php') {
            // Redirect to login page - adjust path based on where token.php is located
            if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
                header('Location: ../login.php');
            } else {
                header('Location: login.php');
            }
            exit();
        }
    }
}

// ==================== USER FUNCTIONS ====================

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    try {
        $table = $_SESSION['user_type'] === 'doctor' ? 'doctors' : 'staff';
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

// ==================== UTILITY FUNCTIONS ====================

function formatCurrency($amount) {
    if (!is_numeric($amount)) {
        return 'Rs. 0.00';
    }
    return 'Rs. ' . number_format((float)$amount, 2);
}

function getPatientName($patient_type, $patient_id) {
    global $pdo;
    
    try {
        if ($patient_type === 'adult') {
            $stmt = $pdo->prepare("SELECT name, phone_number FROM adults WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("
                SELECT k.name, a.phone_number, a.name as parent_name 
                FROM kids k 
                JOIN adults a ON k.parent_id = a.id 
                WHERE k.id = ?
            ");
        }
        
        $stmt->execute([$patient_id]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Error getting patient name: " . $e->getMessage());
        return null;
    }
}

// ==================== DATABASE TESTING FUNCTIONS ====================

function testDatabaseConnection() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        return $result && $result['test'] == 1;
    } catch (PDOException $e) {
        return false;
    }
}

function getDatabaseInfo() {
    global $pdo;
    
    try {
        $info = [
            'connection_status' => 'Connected',
            'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'client_version' => $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION),
            'connection_status_code' => $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ];
        
        $stmt = $pdo->query("SHOW TABLES");
        $info['table_count'] = $stmt->rowCount();
        
        return $info;
        
    } catch (PDOException $e) {
        return [
            'connection_status' => 'Error: ' . $e->getMessage(),
            'server_version' => 'Unknown',
            'client_version' => 'Unknown',
            'table_count' => 0
        ];
    }
}

// ==================== APPLICATION CONSTANTS ====================

define('APP_NAME', 'DocWallet');
define('APP_VERSION', '1.0.0');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// ==================== DEBUG INFO ====================

// Debug info - only show when specifically requested and not AJAX
if (isset($_GET['debug']) && $_GET['debug'] === 'db' && !$is_ajax_request) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Database Connection Successful!</h3>";
    echo "<p><strong>Configuration used:</strong> " . DB_HOST . " / " . DB_USER . " / " . DB_NAME . "</p>";
    echo "<p><strong>Current Time (Sri Lanka):</strong> " . date('Y-m-d H:i:s T') . "</p>";
    
    $info = getDatabaseInfo();
    echo "<p><strong>Server Version:</strong> " . $info['server_version'] . "</p>";
    echo "<p><strong>Tables Found:</strong> " . $info['table_count'] . "</p>";
    echo "<p><strong>Status:</strong> " . $info['connection_status'] . "</p>";
    echo "</div>";
}
?>