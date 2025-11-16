<?php
require_once '../config.php';

// Get all sender IDs
$stmt = $pdo->query("SELECT * FROM sms_sender_ids ORDER BY created_at DESC");
$senderIds = $stmt->fetchAll();

// Get all doctors
$stmt = $pdo->query("SELECT id, doctor_name, phone_number FROM doctors ORDER BY doctor_name");
$doctors = $stmt->fetchAll();

// Get SMS configuration overview
$stmt = $pdo->query("SELECT * FROM sms_config_overview ORDER BY doctor_name");
$smsConfigs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="ad_index.php" class="hover:bg-blue-700 px-3 py-2 rounded">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
                <h1 class="text-xl font-bold">SMS Management - Admin Panel</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span>Admin Panel</span>
                <a href="../logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <!-- Action Buttons -->
        <div class="mb-6 flex space-x-4">
            <button onclick="openAddSenderModal()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold">
                <i class="fas fa-plus mr-2"></i>Add New Sender ID
            </button>
            <button onclick="openLinkDoctorModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold">
                <i class="fas fa-link mr-2"></i>Link Doctor to Sender ID
            </button>
        </div>

        <!-- Available Sender IDs -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Available Sender IDs</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">Sender ID</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-left">API Key</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($senderIds as $sender): ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($sender['sender_id']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($sender['description'] ?? '-'); ?></td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">
                                        <?php echo substr($sender['api_key'], 0, 20) . '...'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($sender['is_active']): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">Active</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <button onclick="editSender(<?php echo $sender['id']; ?>)" class="text-blue-600 hover:text-blue-800 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleSenderStatus(<?php echo $sender['id']; ?>, <?php echo $sender['is_active']; ?>)" class="text-orange-600 hover:text-orange-800">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($senderIds)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No sender IDs found. Add your first sender ID to get started.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Doctor SMS Configuration -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Doctor SMS Configuration</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">Doctor Name</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-left">Sender ID</th>
                            <th class="px-4 py-3 text-left">Total Units</th>
                            <th class="px-4 py-3 text-left">Used Units</th>
                            <th class="px-4 py-3 text-left">Remaining</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($smsConfigs as $config): ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($config['doctor_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($config['doctor_phone']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-semibold">
                                        <?php echo htmlspecialchars($config['sender_id']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?php echo number_format($config['total_units']); ?></td>
                                <td class="px-4 py-3"><?php echo number_format($config['used_units']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold <?php echo $config['remaining_units'] <= 10 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo number_format($config['remaining_units']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    if ($config['unit_status'] === 'DEPLETED') {
                                        $statusClass = 'bg-red-100 text-red-800';
                                    } elseif ($config['unit_status'] === 'LOW') {
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                    } elseif ($config['unit_status'] === 'AVAILABLE') {
                                        $statusClass = 'bg-green-100 text-green-800';
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?> px-2 py-1 rounded text-xs font-medium">
                                        <?php echo $config['unit_status']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <button onclick='openUpdateUnitsModal(<?php echo $config['id']; ?>, <?php echo json_encode($config['doctor_name']); ?>, <?php echo $config['total_units']; ?>, <?php echo $config['used_units']; ?>)' 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-plus-circle mr-1"></i>Update Units
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($smsConfigs)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    No doctor configurations found. Link a doctor to a sender ID to get started.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Sender ID Modal -->
    <div id="addSenderModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Add New Sender ID</h3>
            <form id="addSenderForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sender ID</label>
                    <input type="text" name="sender_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                    <textarea name="api_key" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                    <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddSenderModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Link Doctor Modal -->
    <div id="linkDoctorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Link Doctor to Sender ID</h3>
            <form id="linkDoctorForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Doctor</label>
                    <select name="doctor_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Doctor --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                <?php echo htmlspecialchars($doctor['doctor_name']); ?> (<?php echo htmlspecialchars($doctor['phone_number']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Sender ID</label>
                    <select name="sender_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Sender ID --</option>
                        <?php foreach ($senderIds as $sender): ?>
                            <?php if ($sender['is_active']): ?>
                                <option value="<?php echo $sender['id']; ?>">
                                    <?php echo htmlspecialchars($sender['sender_id']); ?>
                                    <?php echo $sender['description'] ? ' - ' . htmlspecialchars($sender['description']) : ''; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">SMS Units</label>
                    <input type="number" name="total_units" required min="1" value="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Number of SMS messages the doctor can send</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeLinkDoctorModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-link mr-2"></i>Link Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Units Modal -->
    <div id="updateUnitsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Update SMS Units</h3>
            <form id="updateUnitsForm">
                <input type="hidden" name="config_id" id="update_config_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Doctor</label>
                    <input type="text" id="update_doctor_name" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                </div>
                
                <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="text-sm space-y-2">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Current Total Units:</span>
                            <span class="font-bold text-blue-700" id="display_total_units">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Used Units:</span>
                            <span class="font-bold text-gray-700" id="display_used_units">0</span>
                        </div>
                        <div class="flex justify-between border-t pt-2 mt-2">
                            <span class="font-medium text-gray-700">Remaining Units:</span>
                            <span class="font-bold text-green-600" id="display_remaining_units">0</span>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Choose Update Type:</label>
                    <div class="space-y-3">
                        <label class="flex items-start p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="update_type" value="add_units" checked class="mt-1 mr-3">
                            <div>
                                <div class="font-medium text-gray-900">Credit Units (Reduce Used Units)</div>
                                <div class="text-sm text-gray-600">Subtract from used units to increase remaining units</div>
                            </div>
                        </label>
                        
                        <label class="flex items-start p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="update_type" value="set_total" class="mt-1 mr-3">
                            <div>
                                <div class="font-medium text-gray-900">Set New Total Units</div>
                                <div class="text-sm text-gray-600">Change the total units capacity (used units stay same)</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2" id="units_label">
                        <i class="fas fa-undo text-green-600 mr-1"></i>Units to Credit Back
                    </label>
                    <input type="number" name="units_value" id="units_value" required min="1" placeholder="Enter number of units" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1" id="units_help">
                        This will subtract from used units and increase remaining units
                    </p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Example:</strong> If used units = 10 and you credit 5 units, used units becomes 5 and remaining units increases by 5.
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeUpdateUnitsModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-save mr-2"></i>Update Units
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="text-gray-700">Processing...</span>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddSenderModal() {
            $('#addSenderModal').removeClass('hidden');
        }

        function closeAddSenderModal() {
            $('#addSenderModal').addClass('hidden');
            $('#addSenderForm')[0].reset();
        }

        function openLinkDoctorModal() {
            $('#linkDoctorModal').removeClass('hidden');
        }

        function closeLinkDoctorModal() {
            $('#linkDoctorModal').addClass('hidden');
            $('#linkDoctorForm')[0].reset();
        }

        function openUpdateUnitsModal(configId, doctorName, totalUnits, usedUnits) {
            $('#update_config_id').val(configId);
            $('#update_doctor_name').val(doctorName);
            $('#display_total_units').text(totalUnits);
            $('#display_used_units').text(usedUnits);
            $('#display_remaining_units').text(totalUnits - usedUnits);
            
            // Reset form
            $('#updateUnitsForm')[0].reset();
            $('input[name="update_type"][value="add_units"]').prop('checked', true).trigger('change');
            
            $('#updateUnitsModal').removeClass('hidden');
        }

        function closeUpdateUnitsModal() {
            $('#updateUnitsModal').addClass('hidden');
            $('#updateUnitsForm')[0].reset();
        }

        // Update labels and placeholders based on selected update type
        $('input[name="update_type"]').change(function() {
            const updateType = $(this).val();
            const label = $('#units_label');
            const help = $('#units_help');
            const input = $('#units_value');
            
            if (updateType === 'add_units') {
                label.html('<i class="fas fa-undo text-green-600 mr-1"></i>Units to Credit Back');
                help.text('This will subtract from used units (total stays same, remaining increases)');
                input.attr('placeholder', 'Enter number of units to credit');
                input.attr('min', '1');
            } else {
                label.html('<i class="fas fa-edit text-blue-600 mr-1"></i>New Total Units');
                help.text('This will change the total units (used units stay same)');
                input.attr('placeholder', 'Enter new total units');
                const usedUnits = parseInt($('#display_used_units').text());
                input.attr('min', usedUnits);
            }
            
            // Clear the input when switching types
            input.val('');
        });

        // Add Sender ID
        $('#addSenderForm').submit(function(e) {
            e.preventDefault();
            $('#loadingModal').removeClass('hidden');

            $.ajax({
                url: '../ajax/admin_sms_actions.php',
                method: 'POST',
                data: $(this).serialize() + '&action=add_sender',
                dataType: 'json',
                success: function(response) {
                    $('#loadingModal').addClass('hidden');
                    if (response.success) {
                        alert('Sender ID added successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.error);
                    }
                },
                error: function() {
                    $('#loadingModal').addClass('hidden');
                    alert('Error adding sender ID. Please try again.');
                }
            });
        });

        // Link Doctor
        $('#linkDoctorForm').submit(function(e) {
            e.preventDefault();
            $('#loadingModal').removeClass('hidden');

            $.ajax({
                url: '../ajax/admin_sms_actions.php',
                method: 'POST',
                data: $(this).serialize() + '&action=link_doctor',
                dataType: 'json',
                success: function(response) {
                    $('#loadingModal').addClass('hidden');
                    if (response.success) {
                        alert('Doctor linked successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.error);
                    }
                },
                error: function() {
                    $('#loadingModal').addClass('hidden');
                    alert('Error linking doctor. Please try again.');
                }
            });
        });

        // Update Units Form Submit
        $('#updateUnitsForm').submit(function(e) {
            e.preventDefault();
            
            const unitsValue = $('#units_value').val();
            const updateType = $('input[name="update_type"]:checked').val();
            
            if (!unitsValue || parseInt(unitsValue) <= 0) {
                alert('Please enter a valid number of units');
                return;
            }
            
            // Additional validation for set_total
            if (updateType === 'set_total') {
                const usedUnits = parseInt($('#display_used_units').text());
                if (parseInt(unitsValue) < usedUnits) {
                    alert('New total units cannot be less than used units (' + usedUnits + ')');
                    return;
                }
            }
            
            $('#loadingModal').removeClass('hidden');

            $.ajax({
                url: '../ajax/admin_sms_actions.php',
                method: 'POST',
                data: $(this).serialize() + '&action=update_units',
                dataType: 'json',
                success: function(response) {
                    $('#loadingModal').addClass('hidden');
                    if (response.success) {
                        let message = 'Units updated successfully!\n\n';
                        message += 'Total Units: ' + response.new_total + '\n';
                        message += 'Used Units: ' + response.new_used + '\n';
                        message += 'Remaining: ' + response.remaining;
                        alert(message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    $('#loadingModal').addClass('hidden');
                    console.error('Error:', error);
                    alert('Error updating units. Please try again.');
                }
            });
        });

        // Toggle Sender Status
        function toggleSenderStatus(senderId, currentStatus) {
            if (!confirm('Are you sure you want to ' + (currentStatus ? 'deactivate' : 'activate') + ' this sender ID?')) {
                return;
            }

            $('#loadingModal').removeClass('hidden');

            $.ajax({
                url: '../ajax/admin_sms_actions.php',
                method: 'POST',
                data: {
                    action: 'toggle_sender_status',
                    sender_id: senderId,
                    current_status: currentStatus
                },
                dataType: 'json',
                success: function(response) {
                    $('#loadingModal').addClass('hidden');
                    if (response.success) {
                        alert('Status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.error);
                    }
                },
                error: function() {
                    $('#loadingModal').addClass('hidden');
                    alert('Error updating status. Please try again.');
                }
            });
        }
    </script>
</body>
</html>