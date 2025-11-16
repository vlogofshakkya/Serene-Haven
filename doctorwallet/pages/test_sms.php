<?php
require_once '../config.php';
requireDoctor();

// Test SMS sending with debugging
if ($_POST['test_sms'] ?? false) {
    $testNumber = $_POST['test_number'];
    $testMessage = $_POST['test_message'];
    
    $result = testSMSAPI($testNumber, $testMessage);
    
    echo "<div class='bg-white p-6 rounded-lg mt-4'>";
    echo "<h3 class='font-bold text-lg mb-4'>SMS Test Result</h3>";
    echo "<pre class='bg-gray-100 p-4 rounded text-sm overflow-auto'>";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "</pre>";
    echo "</div>";
}

function testSMSAPI($number, $message) {
    // Clean the number (remove + for text.lk)
    $cleanNumber = str_replace('+', '', $number);
    if (substr($cleanNumber, 0, 1) === '0') {
        $cleanNumber = '94' . substr($cleanNumber, 1);
    }
    
    // Use the same API format as your working OTP example
    $apiUrl = 'https://app.text.lk/api/v3/sms/send';
    $apiKey = '1203|539wQXaZPaW6KANa6BIfccxiJZxEF2Y57sSYwjqofe171066'; // Correct format with pipe
    
    // Use the same JSON format as your working OTP code
    $postData = json_encode([
        "recipient" => $cleanNumber,
        "sender_id" => "TextLKDemo",
        "type" => "plain",
        "message" => $message
    ]);
    
    // Debug info
    $debugInfo = [
        'api_url' => $apiUrl,
        'original_number' => $number,
        'formatted_number' => $cleanNumber,
        'message' => $message,
        'payload' => json_decode($postData, true),
        'api_key' => substr($apiKey, 0, 10) . '...' // Show only first 10 chars for security
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    $debugInfo['http_code'] = $httpCode;
    $debugInfo['curl_error'] = $curlError;
    $debugInfo['raw_response'] = $response;
    $debugInfo['curl_info'] = $curlInfo;
    
    if ($response) {
        $debugInfo['parsed_response'] = json_decode($response, true);
    }
    
    return $debugInfo;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Debug Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold mb-6">SMS API Debug Test</h1>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test Phone Number</label>
                    <input type="text" name="test_number" required 
                           value="<?php echo $_POST['test_number'] ?? '+94753379745'; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="+94753379745">
                    <small class="text-gray-500">Enter your test number (Sri Lankan format)</small>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test Message</label>
                    <textarea name="test_message" required rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Test message from Doctor Wallet SMS service"><?php echo $_POST['test_message'] ?? 'Test message from Doctor Wallet SMS service'; ?></textarea>
                </div>
                
                <button type="submit" name="test_sms" value="1" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    Send Test SMS
                </button>
            </form>
            
            <div class="mt-6 p-4 bg-yellow-50 rounded-lg">
                <h4 class="font-medium text-yellow-800 mb-2">Debug Information:</h4>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Using your API key: 1203|539wQ... (correct format with pipe)</li>
                    <li>• API Endpoint: https://app.text.lk/api/v3/sms/send (OAuth 2.0)</li>
                    <li>• Sender ID: TextLKDemo (same as your OTP example)</li>
                    <li>• Authentication: Bearer Token</li>
                    <li>• Content-Type: application/json</li>
                </ul>
            </div>
        </div>
        
        <?php if ($_POST['test_sms'] ?? false): ?>
            <!-- Result will be displayed here -->
        <?php endif; ?>
    </div>
</body>
</html>