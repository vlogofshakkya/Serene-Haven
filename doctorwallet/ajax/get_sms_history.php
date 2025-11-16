<?php
require_once '../config.php';
requireDoctor();

$user = getCurrentUser();
$doctorId = $user['id'];

try {
    // Get recent SMS history for this doctor
    $stmt = $pdo->prepare("
        SELECT phone_number, message, status, response, created_at 
        FROM sms_logs 
        WHERE doctor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$doctorId]);
    $smsHistory = $stmt->fetchAll();
    
    if (empty($smsHistory)): ?>
        <div class="text-center text-gray-500 py-8">
            <i class="fas fa-inbox text-4xl mb-4"></i>
            <p>No SMS history found. Send your first SMS to see it here!</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($smsHistory as $sms): ?>
                <div class="border border-gray-200 rounded-lg p-4 <?php echo $sms['status'] === 'sent' ? 'bg-green-50' : 'bg-red-50'; ?>">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($sms['phone_number']); ?></span>
                                <span class="<?php echo $sms['status'] === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> px-2 py-1 rounded-full text-xs font-medium">
                                    <?php echo $sms['status'] === 'sent' ? 'Sent' : 'Failed'; ?>
                                </span>
                                <span class="text-xs text-gray-500">
                                    <?php echo date('M j, Y g:i A', strtotime($sms['created_at'])); ?>
                                </span>
                            </div>
                            <div class="text-gray-700 mb-2">
                                <strong>Message:</strong> <?php echo htmlspecialchars($sms['message']); ?>
                            </div>
                            <?php if ($sms['status'] === 'failed' && $sms['response']): ?>
                                <div class="text-red-600 text-sm">
                                    <strong>Error:</strong> <?php echo htmlspecialchars($sms['response']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ml-4">
                            <i class="fas <?php echo $sms['status'] === 'sent' ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-500'; ?>"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-4 text-center">
            <button onclick="loadMoreHistory()" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-chevron-down mr-1"></i>Load More History
            </button>
        </div>
    <?php endif;
    
} catch (PDOException $e) {
    error_log("Error loading SMS history: " . $e->getMessage());
    ?>
    <div class="text-center text-red-500 py-8">
        <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
        <p>Error loading SMS history. Please try again later.</p>
    </div>
    <?php
}
?>

<script>
function loadMoreHistory() {
    // This can be implemented to load more history with pagination
    alert('Load more functionality can be implemented based on your needs');
}
</script>