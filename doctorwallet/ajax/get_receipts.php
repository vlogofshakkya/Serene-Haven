<?php
require_once '../config.php';
date_default_timezone_set('Asia/Colombo');
// Set content type and disable caching
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

try {
    // Check if user is logged in
    requireLogin();
    $pdo->exec("SET time_zone = '+05:30'");
    // Get staff member's linked doctor
    $doctor_id = null;
    if (isStaff()) {
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE staff_member_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $doctor = $stmt->fetch();
        if ($doctor) {
            $doctor_id = $doctor['id'];
        }
    }
    
    // Check access permissions
    if (!isDoctor() && !$doctor_id) {
        echo '<div class="text-center text-gray-500 py-8">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mb-2"></i>
                <p>Access Denied - No doctor account linked</p>
              </div>';
        exit;
    }
    
    // Use current doctor if logged in as doctor, or linked doctor if staff
    $target_doctor_id = isDoctor() ? $_SESSION['user_id'] : $doctor_id;
    
    // Get filter parameters
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $patient_type = isset($_GET['patient_type']) ? $_GET['patient_type'] : '';
    
    // Build the WHERE clause
    $where_conditions = ["e.doctor_id = ?"];
    $params = [$target_doctor_id];
    
    // Add search filter
    if (!empty($search)) {
        $where_conditions[] = "(
            CASE 
                WHEN e.patient_type = 'adult' THEN a.name
                ELSE k.name
            END LIKE ? OR 
            e.symptoms LIKE ? OR 
            e.id LIKE ?
        )";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add date range filter
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(e.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(e.created_at) <= ?";
        $params[] = $date_to;
    }
    
    // Add patient type filter
    if (!empty($patient_type) && in_array($patient_type, ['adult', 'kid'])) {
        $where_conditions[] = "e.patient_type = ?";
        $params[] = $patient_type;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get receipts with patient information (FIXED QUERY)
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            d.doctor_name,
            CASE 
                WHEN e.patient_type = 'adult' THEN a.name
                ELSE k.name
            END as patient_name,
            CASE 
                WHEN e.patient_type = 'adult' THEN a.phone_number
                ELSE ap.phone_number
            END as patient_phone
        FROM e_receipts e
        INNER JOIN doctors d ON e.doctor_id = d.id
        LEFT JOIN adults a ON e.patient_type = 'adult' AND e.patient_id = a.id
        LEFT JOIN kids k ON e.patient_type = 'kid' AND e.patient_id = k.id
        LEFT JOIN adults ap ON e.patient_type = 'kid' AND k.parent_id = ap.id
        WHERE {$where_clause}
        ORDER BY e.created_at DESC
        LIMIT ?
    ");
    
    $params[] = $limit;
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination info
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM e_receipts e
        INNER JOIN doctors d ON e.doctor_id = d.id
        LEFT JOIN adults a ON e.patient_type = 'adult' AND e.patient_id = a.id
        LEFT JOIN kids k ON e.patient_type = 'kid' AND e.patient_id = k.id
        LEFT JOIN adults ap ON e.patient_type = 'kid' AND k.parent_id = ap.id
        WHERE {$where_clause}
    ");
    
    array_pop($params); // Remove limit parameter
    $count_stmt->execute($params);
    $total_receipts = $count_stmt->fetch()['total'];
    
    if (empty($receipts)) {
        echo '<div class="text-center text-gray-500 py-8">
                <i class="fas fa-receipt text-3xl mb-3"></i>
                <p class="text-lg font-medium">No receipts found</p>
                <p class="text-sm">Try adjusting your search criteria or date range</p>
              </div>';
        exit;
    }
    
    // Display results info
    if (!empty($search) || !empty($date_from) || !empty($date_to) || !empty($patient_type)) {
        echo '<div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Showing ' . count($receipts) . ' of ' . $total_receipts . ' receipts';
        
        $filters = [];
        if (!empty($search)) $filters[] = 'Search: "' . htmlspecialchars($search) . '"';
        if (!empty($date_from)) $filters[] = 'From: ' . date('M d, Y', strtotime($date_from));
        if (!empty($date_to)) $filters[] = 'To: ' . date('M d, Y', strtotime($date_to));
        if (!empty($patient_type)) $filters[] = 'Type: ' . ucfirst($patient_type);
        
        if (!empty($filters)) {
            echo ' (Filters: ' . implode(', ', $filters) . ')';
        }
        
        echo '</p></div>';
    }
    
    // Display receipts
    foreach ($receipts as $receipt):
        // Get receipt items (FIXED QUERY with proper dosage handling)
        $stmt = $pdo->prepare("
            SELECT 
                ri.*,
                m.drug_name,
                m.price_per_tablet as unit_price,
                CASE 
               WHEN ri.dosage = 'M' THEN 'M'
               WHEN ri.dosage = 'N' THEN 'N'
               WHEN ri.dosage = 'Bd' THEN 'Bd'
               WHEN ri.dosage = 'Tds' THEN 'Tds'
               WHEN ri.dosage = 'Qds' THEN 'Qds'
               WHEN ri.dosage = 'SOS' THEN 'SOS'
               WHEN ri.dosage = 'EOD' THEN 'EODy'
               WHEN ri.dosage = 'STAT' THEN 'STAT'
               WHEN ri.dosage = 'VESP' THEN 'VESP'
               WHEN ri.dosage = 'NOON' THEN 'NOON'
               WHEN ri.dosage = '3H' THEN '3H'
               WHEN ri.dosage = '4H' THEN '4H'
               WHEN ri.dosage = '6H' THEN '6H'
               WHEN ri.dosage = '8H' THEN '8H'
               WHEN ri.dosage = 'WEEKLY' THEN 'WEEKLY'
               WHEN ri.dosage = '5X' THEN '5X'
                    ELSE COALESCE(ri.dosage, 'As directed')
                END as dosage_description
            FROM receipt_items ri 
            INNER JOIN medicines m ON ri.medicine_id = m.id 
            WHERE ri.receipt_id = ?
            ORDER BY ri.id ASC
        ");
        $stmt->execute([$receipt['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="border border-gray-200 rounded-lg p-4 mb-4 bg-white shadow-sm hover:shadow-md transition-shadow">
    <!-- Receipt Header -->
    <div class="flex justify-between items-start mb-4">
        <div class="flex-1">
            <div class="flex items-center mb-2">
                <h3 class="font-bold text-lg text-gray-800 mr-3">
                    <i class="fas fa-receipt text-blue-600 mr-2"></i>
                    Receipt #<?php echo str_pad($receipt['id'], 6, '0', STR_PAD_LEFT); ?>
                </h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $receipt['patient_type'] === 'adult' ? 'green' : 'blue'; ?>-100 text-<?php echo $receipt['patient_type'] === 'adult' ? 'green' : 'blue'; ?>-800">
                    <i class="fas fa-<?php echo $receipt['patient_type'] === 'adult' ? 'user' : 'child'; ?> mr-1"></i>
                    <?php echo ucfirst($receipt['patient_type']); ?>
                </span>
            </div>
            
            <div class="space-y-1 text-sm">
                <p class="text-gray-700">
                    <i class="fas fa-user text-gray-500 w-4"></i>
                    <strong>Patient:</strong> <?php echo htmlspecialchars($receipt['patient_name']); ?>
                </p>
                
                <?php if ($receipt['patient_phone']): ?>
                <p class="text-gray-700">
                    <i class="fas fa-phone text-gray-500 w-4"></i>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($receipt['patient_phone']); ?>
                </p>
                <?php endif; ?>
                
                <p class="text-gray-700">
                    <i class="fas fa-user-md text-gray-500 w-4"></i>
                    <strong>Doctor:</strong> <?php echo htmlspecialchars($receipt['doctor_name']); ?>
                </p>
            </div>
        </div>
        
        <div class="text-right ml-4">
            <p class="text-sm text-gray-500 mb-1">
                <i class="fas fa-calendar text-gray-400 mr-1"></i>
                <?php echo date('M d, Y', strtotime($receipt['created_at'])); ?>
            </p>
            <p class="text-xs text-gray-400 mb-2">
                <i class="fas fa-clock text-gray-400 mr-1"></i>
                <?php echo date('h:i A', strtotime($receipt['created_at'])); ?>
            </p>
            <p class="font-bold text-xl text-green-600">
                <?php echo formatCurrency($receipt['total_amount']); ?>
            </p>
            
            <!-- Action buttons -->
            <div class="mt-2 space-x-1">
                <button onclick="printReceipt(<?php echo $receipt['id']; ?>)" class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
                    <i class="fas fa-print mr-1"></i>Print
                </button>
                <button onclick="viewReceiptDetails(<?php echo $receipt['id']; ?>)" class="inline-flex items-center px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors">
                    <i class="fas fa-eye mr-1"></i>View
                </button>
            </div>
        </div>
    </div>
    
    <!-- Symptoms Section -->
    <?php if (!empty($receipt['symptoms'])): ?>
    <div class="mb-4">
        <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
            <i class="fas fa-stethoscope text-red-500 mr-2"></i>
            Symptoms & Diagnosis:
        </h4>
        <p class="text-gray-600 bg-red-50 p-3 rounded border-l-4 border-red-200 leading-relaxed">
            <?php echo nl2br(htmlspecialchars($receipt['symptoms'])); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Medicines Section -->
    <div>
        <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
            <i class="fas fa-pills text-green-500 mr-2"></i>
            Prescribed Medicines (<?php echo count($items); ?> items):
        </h4>
        
        <?php if (empty($items)): ?>
        <p class="text-gray-500 italic">No medicines prescribed</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($items as $index => $item): ?>
            <div class="bg-gray-50 p-3 rounded border-l-4 border-green-200 hover:bg-gray-100 transition-colors">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center mb-1">
                            <span class="inline-flex items-center justify-center w-6 h-6 bg-green-100 text-green-800 text-xs font-bold rounded-full mr-2">
                                <?php echo $index + 1; ?>
                            </span>
                            <span class="font-semibold text-gray-800">
                                <?php echo htmlspecialchars($item['drug_name']); ?>
                            </span>
                        </div>
                        
                        <div class="ml-8 space-y-1 text-sm">
                            <p class="text-gray-700">
                                <i class="fas fa-tablets text-blue-500 w-4 mr-1"></i>
                                <strong>Quantity:</strong> <?php echo $item['quantity_issued']; ?> tablets

                            </p>
                            
                            <p class="text-gray-700">
                                <i class="fas fa-clock text-orange-500 w-4 mr-1"></i>
                                <strong>Instructions:</strong> <?php echo htmlspecialchars($item['dosage_description']); ?>
                                <?php if ($item['tablets_per_dose']): ?>
                                    - <?php echo $item['tablets_per_dose']; ?> tablet(s) per dose
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="text-right ml-4">
                        <span class="font-semibold text-green-600 text-lg">
                            <?php echo formatCurrency($item['amount']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Total Summary -->
        <div class="mt-3 pt-3 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <span class="text-gray-600">
                    Total Items: <strong><?php echo count($items); ?></strong>
                </span>
                <span class="text-lg font-bold text-green-600">
                    Total: <?php echo formatCurrency($receipt['total_amount']); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Receipt Footer -->
    <div class="mt-4 pt-3 border-t border-gray-200 text-xs text-gray-500">
        <div class="flex justify-between items-center">
            <span>
                <i class="fas fa-calendar-plus mr-1"></i>
                Created: <?php echo date('F j, Y \a\t g:i A', strtotime($receipt['created_at'])); ?>
            </span>
            <?php if (isset($receipt['updated_at']) && $receipt['updated_at'] !== $receipt['created_at']): ?>
            <span>
                <i class="fas fa-edit mr-1"></i>
                Updated: <?php echo date('F j, Y \a\t g:i A', strtotime($receipt['updated_at'])); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endforeach; ?>

<!-- Load more button if there are more receipts -->
<?php if (count($receipts) >= $limit && count($receipts) < $total_receipts): ?>
<div class="text-center py-4">
    <button onclick="loadMoreReceipts()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
        <i class="fas fa-plus mr-2"></i>
        Load More Receipts (<?php echo $total_receipts - count($receipts); ?> remaining)
    </button>
</div>
<?php endif; ?>

<script>
// Auto-scroll functionality
if (typeof window.receiptsLoaded === 'undefined') {
    window.receiptsLoaded = true;
} else {
    // Smooth scroll to top when receipts are updated
    const container = document.getElementById('receipts-container') || document.querySelector('.receipts-wrapper');
    if (container) {
        container.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
}

    function viewReceiptDetails(receiptId) {
        $('#receiptContent').html('<div class="text-center py-4">Loading...</div>');
        $('#receiptModal').removeClass('hidden');
        
        $.get('../ajax/get_receipt_details.php', { id: receiptId }, function(data) {
            $('#receiptContent').html(data);
        }).fail(function() {
            $('#receiptContent').html('<div class="text-center py-4 text-red-500">Error loading receipt details</div>');
        });
    }

    function printReceipt(receiptId) {
        const printWindow = window.open(`../ajax/print_receipt.php?id=${receiptId}`, '_blank');
        printWindow.onload = function() {
            printWindow.print();
        };
    }

// Load more receipts function
function loadMoreReceipts() {
    const currentLimit = <?php echo $limit; ?>;
    const newLimit = currentLimit + 20;
    
    // Update the limit parameter and reload
    const url = new URL(window.location.href);
    url.searchParams.set('limit', newLimit);
    
    // Reload the receipts with new limit
    if (typeof loadReceipts === 'function') {
        loadReceipts(url.searchParams.toString());
    } else {
        window.location.href = url.toString();
    }
}

// Add smooth animations
document.querySelectorAll('.hover\\:shadow-md').forEach(element => {
    element.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
    });
    
    element.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});
</script>

<?php
} catch (PDOException $e) {
    // Log the error (don't expose to user)
    error_log("Database error in get_receipts.php: " . $e->getMessage());
    echo '<div class="text-center text-red-500 py-8">
            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
            <p>Database error occurred. Please try again later.</p>
            <p class="text-xs mt-2">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
          </div>';
    
} catch (Exception $e) {
    // Log the error (don't expose to user)
    error_log("General error in get_receipts.php: " . $e->getMessage());
    echo '<div class="text-center text-red-500 py-8">
            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
            <p>An error occurred. Please try again later.</p>
            <p class="text-xs mt-2">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
          </div>';
}
?>