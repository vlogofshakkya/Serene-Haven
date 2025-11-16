<?php
require_once '../config.php';
requireLogin();

$receipt_id = (int)($_GET['id'] ?? 0);

if (!$receipt_id) {
    echo '<div class="text-red-500">Invalid receipt ID</div>';
    exit;
}

// Get receipt details
$stmt = $pdo->prepare("
    SELECT e.*, d.doctor_name,
           CASE 
               WHEN e.patient_type = 'adult' THEN a.name
               ELSE k.name
           END as patient_name,
           CASE 
               WHEN e.patient_type = 'adult' THEN a.phone_number
               ELSE ap.phone_number
           END as patient_phone,
           CASE 
               WHEN e.patient_type = 'adult' THEN a.nic_number
               ELSE NULL
           END as patient_nic,
           CASE 
               WHEN e.patient_type = 'kid' THEN ap.name
               ELSE NULL
           END as parent_name
    FROM e_receipts e
    JOIN doctors d ON e.doctor_id = d.id
    LEFT JOIN adults a ON e.patient_type = 'adult' AND e.patient_id = a.id
    LEFT JOIN kids k ON e.patient_type = 'kid' AND e.patient_id = k.id
    LEFT JOIN adults ap ON e.patient_type = 'kid' AND k.parent_id = ap.id
    WHERE e.id = ?
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    echo '<div class="text-red-500">Receipt not found</div>';
    exit;
}

// Get receipt items with dosage description
$stmt = $pdo->prepare("
    SELECT ri.*, m.drug_name,
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
    JOIN medicines m ON ri.medicine_id = m.id 
    WHERE ri.receipt_id = ?
    ORDER BY ri.id
");
$stmt->execute([$receipt_id]);
$items = $stmt->fetchAll();
?>

<div class="space-y-4">
    <!-- Receipt Header -->
    <div class="text-center border-b pb-4">
        <h2 class="text-2xl font-bold text-gray-800">Medical Receipt</h2>
        <p class="text-gray-600">Dr. <?php echo htmlspecialchars($receipt['doctor_name']); ?></p>
        <p class="text-sm text-gray-500">Receipt #<?php echo $receipt['id']; ?></p>
        <p class="text-sm text-gray-500"><?php echo date('F d, Y h:i A', strtotime($receipt['created_at'])); ?></p>
    </div>

    <!-- Patient Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Patient Information</h3>
            <div class="space-y-1 text-sm">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($receipt['patient_name']); ?></p>
                <?php if ($receipt['parent_name']): ?>
                    <p><strong>Parent:</strong> <?php echo htmlspecialchars($receipt['parent_name']); ?></p>
                <?php endif; ?>
                <?php if ($receipt['patient_phone']): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($receipt['patient_phone']); ?></p>
                <?php endif; ?>
                <?php if ($receipt['patient_nic']): ?>
                    <p><strong>NIC:</strong> <?php echo htmlspecialchars($receipt['patient_nic']); ?></p>
                <?php endif; ?>
                <p><strong>Patient Type:</strong> <?php echo ucfirst($receipt['patient_type']); ?></p>
            </div>
        </div>
        
        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Visit Information</h3>
            <div class="space-y-1 text-sm">
                <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($receipt['created_at'])); ?></p>
                <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($receipt['created_at'])); ?></p>
                <p><strong>Total Amount:</strong> <span class="font-bold text-green-600"><?php echo formatCurrency($receipt['total_amount']); ?></span></p>
            </div>
        </div>
    </div>

    <!-- Symptoms -->
    <div>
        <h3 class="font-semibold text-gray-700 mb-2">Symptoms</h3>
        <div class="bg-gray-50 p-3 rounded border text-sm">
            <?php echo nl2br(htmlspecialchars($receipt['symptoms'] ?? 'No symptoms recorded')); ?>
        </div>
    </div>

    <!-- Prescribed Medicines -->
    <div>
        <h3 class="font-semibold text-gray-700 mb-2">Prescribed Medicines</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full table-auto border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-b">Medicine</th>
                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-b">Quantity</th>
                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-b">Instructions</th>
                        <th class="px-4 py-2 text-right text-sm font-semibold text-gray-700 border-b">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($item['drug_name']); ?></td>
                            <td class="px-4 py-2"><?php echo $item['quantity_issued']; ?> tablets</td>
                            <td class="px-4 py-2 text-sm">
                                <?php echo htmlspecialchars($item['dosage_description']); ?>
                                <?php if ($item['tablets_per_dose']): ?>
                                    <br><span class="text-gray-500"><?php echo $item['tablets_per_dose']; ?> tablet(s) per dose</span>
                                <?php endif; ?>
                                <?php if ($item['days_of_taking']): ?>
                                    <br><span class="text-gray-500">for <?php echo $item['days_of_taking']; ?> days</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 text-right font-medium"><?php echo formatCurrency($item['amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-right font-bold">Total:</td>
                        <td class="px-4 py-2 text-right font-bold text-green-600"><?php echo formatCurrency($receipt['total_amount']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Instructions -->
    <div class="bg-blue-50 p-4 rounded border border-blue-200">
        <h3 class="font-semibold text-blue-800 mb-2">General Instructions</h3>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>• Take medicines as prescribed by the doctor</li>
            <li>• Complete the full course of medication</li>
            <li>• If you experience any side effects, consult your doctor immediately</li>
            <li>• Store medicines in a cool, dry place</li>
            <li>• Keep medicines away from children</li>
        </ul>
    </div>

    <!-- Footer -->
    <div class="text-center text-sm text-gray-500 border-t pt-4">
        <p>Thank you for choosing our medical services</p>
        <p>For any queries, please contact us</p>
    </div>

    <!-- Print Button -->
    <div class="text-center">
        <button onclick="printReceipt(<?php echo $receipt['id']; ?>)" 
                class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg">
            Print Receipt
        </button>
    </div>
</div>

<script>
function printReceipt(receiptId) {
    const printWindow = window.open(`print_receipt.php?id=${receiptId}`, '_blank', 'width=800,height=600');
    printWindow.onload = function() {
        printWindow.print();
        printWindow.onafterprint = function() {
            printWindow.close();
        };
    };
}
</script>