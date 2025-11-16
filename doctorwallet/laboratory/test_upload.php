<?php
// Test upload directory permissions
// Access this file to check if everything is configured correctly
// URL: http://yourdomain.com/laboratory/test_upload.php

$upload_dir = '../uploads/lab_reports/';
$results = [];

// Check if directory exists
if (is_dir($upload_dir)) {
    $results['directory_exists'] = '✅ Directory exists';
} else {
    $results['directory_exists'] = '❌ Directory does not exist';
    // Try to create it
    if (mkdir($upload_dir, 0755, true)) {
        $results['directory_created'] = '✅ Directory created successfully';
    } else {
        $results['directory_created'] = '❌ Failed to create directory';
    }
}

// Check if directory is writable
if (is_writable($upload_dir)) {
    $results['directory_writable'] = '✅ Directory is writable';
} else {
    $results['directory_writable'] = '❌ Directory is not writable';
    $results['directory_permissions'] = 'Current permissions: ' . substr(sprintf('%o', fileperms($upload_dir)), -4);
}

// Check PHP upload settings
$results['upload_max_filesize'] = 'Max file size: ' . ini_get('upload_max_filesize');
$results['post_max_size'] = 'Max post size: ' . ini_get('post_max_size');
$results['file_uploads'] = 'File uploads: ' . (ini_get('file_uploads') ? '✅ Enabled' : '❌ Disabled');

// Get directory path
$results['upload_directory_path'] = 'Upload directory: ' . realpath($upload_dir);

// Check database connection
try {
    require_once '../config.php';
    $results['database_connection'] = '✅ Database connection successful';
    
    // Check if lab_reports table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'lab_reports'");
    if ($stmt->rowCount() > 0) {
        $results['lab_reports_table'] = '✅ lab_reports table exists';
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE lab_reports");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $results['table_columns'] = 'Columns: ' . implode(', ', $columns);
    } else {
        $results['lab_reports_table'] = '❌ lab_reports table does not exist';
    }
    
    // Check if laboratory_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'laboratory_users'");
    if ($stmt->rowCount() > 0) {
        $results['laboratory_users_table'] = '✅ laboratory_users table exists';
    } else {
        $results['laboratory_users_table'] = '❌ laboratory_users table does not exist - Please run the SQL script';
    }
    
} catch (Exception $e) {
    $results['database_connection'] = '❌ Database error: ' . $e->getMessage();
}

// Test file write
$test_file = $upload_dir . 'test_' . time() . '.txt';
if (file_put_contents($test_file, 'Test content')) {
    $results['file_write_test'] = '✅ File write test successful';
    unlink($test_file);
} else {
    $results['file_write_test'] = '❌ File write test failed';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Directory Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">
                🔧 Laboratory Upload System Diagnostics
            </h1>
            
            <div class="space-y-3">
                <?php foreach ($results as $key => $result): ?>
                    <div class="p-4 rounded-lg <?php echo strpos($result, '✅') !== false ? 'bg-green-50 border border-green-200' : (strpos($result, '❌') !== false ? 'bg-red-50 border border-red-200' : 'bg-blue-50 border border-blue-200'); ?>">
                        <p class="font-mono text-sm">
                            <strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong><br>
                            <?php echo htmlspecialchars($result); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-bold text-yellow-800 mb-2">📋 Quick Fixes:</h3>
                <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                    <li>If directory is not writable, run: <code class="bg-yellow-100 px-2 py-1 rounded">chmod 755 uploads/lab_reports</code></li>
                    <li>If directory doesn't exist, create it: <code class="bg-yellow-100 px-2 py-1 rounded">mkdir -p uploads/lab_reports</code></li>
                    <li>If laboratory_users table doesn't exist, run the SQL script provided</li>
                    <li>Make sure your web server user has write permissions to the uploads directory</li>
                </ul>
            </div>
            
            <div class="mt-6 flex gap-3">
                <a href="lab_login.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg transition-colors">
                    Go to Login
                </a>
                <a href="lab_reports.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors">
                    Go to Lab Reports
                </a>
                <button onclick="location.reload()" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors">
                    Refresh Test
                </button>
            </div>
        </div>
    </div>
</body>
</html>