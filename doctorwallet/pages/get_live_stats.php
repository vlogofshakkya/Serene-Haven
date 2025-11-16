<?php
session_start();
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user has profile access
if (!isset($_SESSION['profile_logged_in']) || $_SESSION['profile_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Get comprehensive statistics
    $stats = [];

    // Total patients
    $stmt = $pdo->query("SELECT (SELECT COUNT(*) FROM adults) + (SELECT COUNT(*) FROM kids) as total");
    $stats['total_patients'] = (int)$stmt->fetch()['total'];

    // Adult patients
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM adults");
    $stats['adult_patients'] = (int)$stmt->fetch()['count'];

    // Kid patients  
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kids");
    $stats['kid_patients'] = (int)$stmt->fetch()['count'];

    // Total medicines
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines");
    $stats['total_medicines'] = (int)$stmt->fetch()['count'];

    // Medicines in stock
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE current_stock > 0");
    $stats['medicines_in_stock'] = (int)$stmt->fetch()['count'];

    // Low stock medicines (less than 10)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE current_stock < 10 AND current_stock > 0");
    $stats['low_stock_medicines'] = (int)$stmt->fetch()['count'];

    // Out of stock medicines
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE current_stock = 0");
    $stats['out_of_stock'] = (int)$stmt->fetch()['count'];

    // Total receipts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM e_receipts");
    $stats['total_receipts'] = (int)$stmt->fetch()['count'];

    // Today's receipts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM e_receipts WHERE DATE(created_at) = CURDATE()");
    $stats['today_receipts'] = (int)$stmt->fetch()['count'];

    // This week's receipts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM e_receipts WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())");
    $stats['week_receipts'] = (int)$stmt->fetch()['count'];

    // This month's receipts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM e_receipts WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['month_receipts'] = (int)$stmt->fetch()['count'];

    // Get staff count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff");
    $stats['total_staff'] = (int)$stmt->fetch()['count'];

    // Get updated chart data (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            MONTHNAME(created_at) as month, 
            COUNT(*) as count 
        FROM e_receipts 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY MONTH(created_at), YEAR(created_at)
        ORDER BY created_at ASC
    ");
    $monthly_trend = $stmt->fetchAll();

    $chart_data = null;
    if (count($monthly_trend) > 0) {
        $chart_data = [
            'months' => array_column($monthly_trend, 'month'),
            'counts' => array_map('intval', array_column($monthly_trend, 'count'))
        ];
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'chart_data' => $chart_data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>