<?php
require_once '../config.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_doctor':
                    $stmt = $pdo->prepare("
                        INSERT INTO doctors (doctor_name, phone_number, slmc_no, staff_member_id, password) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $staff_id = !empty($_POST['staff_member_id']) ? $_POST['staff_member_id'] : null;
                    $stmt->execute([
                        $_POST['doctor_name'],
                        $_POST['phone_number'],
                        $_POST['slmc_no'],
                        $staff_id,
                        $password
                    ]);
                    $message = 'Doctor added successfully!';
                    break;

                case 'update_doctor':
                    $stmt = $pdo->prepare("
                        UPDATE doctors 
                        SET doctor_name = ?, phone_number = ?, slmc_no = ?, staff_member_id = ?
                        WHERE id = ?
                    ");
                    $staff_id = !empty($_POST['staff_member_id']) ? $_POST['staff_member_id'] : null;
                    $stmt->execute([
                        $_POST['doctor_name'],
                        $_POST['phone_number'],
                        $_POST['slmc_no'],
                        $staff_id,
                        $_POST['doctor_id']
                    ]);
                    $message = 'Doctor updated successfully!';
                    break;

                case 'delete_doctor':
                    // Check if doctor has dependencies
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM e_receipts WHERE doctor_id = ?");
                    $stmt->execute([$_POST['doctor_id']]);
                    $receipts = $stmt->fetch()['count'];
                    
                    if ($receipts > 0) {
                        $error = 'Cannot delete doctor with existing prescriptions. Please archive instead.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
                        $stmt->execute([$_POST['doctor_id']]);
                        $message = 'Doctor deleted successfully!';
                    }
                    break;

                case 'reset_password':
                    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE doctors SET password = ? WHERE id = ?");
                    $stmt->execute([$new_password, $_POST['doctor_id']]);
                    $message = 'Password reset successfully!';
                    break;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get all doctors with staff information
$stmt = $pdo->query("
    SELECT 
        d.*,
        s.name as staff_name,
        s.phone_number as staff_phone,
        (SELECT COUNT(*) FROM e_receipts WHERE doctor_id = d.id) as total_prescriptions,
        (SELECT COUNT(*) FROM adults WHERE doctor_id = d.id) as total_adult_patients,
        (SELECT COUNT(*) FROM kids WHERE doctor_id = d.id) as total_kid_patients
    FROM doctors d
    LEFT JOIN staff s ON d.staff_member_id = s.id
    ORDER BY d.doctor_name
");
$doctors = $stmt->fetchAll();

// Get all staff members for dropdown
$stmt = $pdo->query("SELECT * FROM staff ORDER BY name");
$staff_members = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Management - Admin Panel</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-500 p-3 rounded-full">
                        <i class="fas fa-user-md text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">Doctor Management</h1>
                        <p class="text-blue-200">View and manage all doctor information</p>
                    </div>
                </div>
                <div class="flex space-x-4">
                    <a href="ad_index.php" class="bg-blue-700 hover:bg-blue-800 px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Admin Home
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center">
            <i class="fas fa-exclamation-triangle mr-3"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100">Total Doctors</p>
                        <p class="text-3xl font-bold"><?php echo count($doctors); ?></p>
                    </div>
                    <i class="fas fa-user-md text-4xl opacity-50"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">With Staff</p>
                        <p class="text-3xl font-bold">
                            <?php echo count(array_filter($doctors, function($d) { return $d['staff_member_id']; })); ?>
                        </p>
                    </div>
                    <i class="fas fa-users text-4xl opacity-50"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100">Total Prescriptions</p>
                        <p class="text-3xl font-bold">
                            <?php echo array_sum(array_column($doctors, 'total_prescriptions')); ?>
                        </p>
                    </div>
                    <i class="fas fa-file-prescription text-4xl opacity-50"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100">Available Staff</p>
                        <p class="text-3xl font-bold"><?php echo count($staff_members); ?></p>
                    </div>
                    <i class="fas fa-user-nurse text-4xl opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- Add Doctor Button -->
        <div class="mb-6">
            <button onclick="openAddDoctorModal()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg transition duration-200 transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Add New Doctor
            </button>
        </div>

        <!-- Doctors Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">All Doctors</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SLMC Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Member</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statistics</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($doctors as $doctor): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-white text-sm font-bold">
                                            <?php echo strtoupper(substr($doctor['doctor_name'], 0, 2)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">ID: <?php echo $doctor['id']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <i class="fas fa-phone text-gray-400 mr-2"></i>
                                    <?php echo htmlspecialchars($doctor['phone_number']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                    <?php echo htmlspecialchars($doctor['slmc_no'] ?? 'Not Set'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($doctor['staff_name']): ?>
                                <div class="text-sm">
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($doctor['staff_name']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-phone text-gray-400 mr-1"></i>
                                        <?php echo htmlspecialchars($doctor['staff_phone']); ?>
                                    </p>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-400 text-sm">No staff assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-xs space-y-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-prescription text-gray-400 mr-2"></i>
                                        <span><?php echo $doctor['total_prescriptions']; ?> prescriptions</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-users text-gray-400 mr-2"></i>
                                        <span><?php echo ($doctor['total_adult_patients'] + $doctor['total_kid_patients']); ?> patients</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <button onclick='openEditDoctorModal(<?php echo json_encode($doctor); ?>)' 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded transition duration-200"
                                            title="Edit Doctor">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick='openResetPasswordModal(<?php echo $doctor["id"]; ?>, "<?php echo htmlspecialchars($doctor["doctor_name"]); ?>")' 
                                            class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded transition duration-200"
                                            title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button onclick='deleteDoctorConfirm(<?php echo $doctor["id"]; ?>, "<?php echo htmlspecialchars($doctor["doctor_name"]); ?>")' 
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded transition duration-200"
                                            title="Delete Doctor">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($doctors)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-user-md text-4xl mb-3"></i>
                                <p>No doctors found. Add your first doctor to get started.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Doctor Modal -->
    <div id="addDoctorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
            <h3 class="text-2xl font-bold mb-6 text-gray-800">
                <i class="fas fa-plus-circle text-green-600 mr-2"></i>Add New Doctor
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_doctor">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-1"></i>Doctor Name *
                        </label>
                        <input type="text" name="doctor_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1"></i>Phone Number *
                        </label>
                        <input type="text" name="phone_number" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-card mr-1"></i>SLMC Number
                        </label>
                        <input type="text" name="slmc_no" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-nurse mr-1"></i>Assign Staff Member
                        </label>
                        <select name="staff_member_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">No Staff Assignment</option>
                            <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['name']); ?> - <?php echo htmlspecialchars($staff['phone_number']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-1"></i>Password *
                    </label>
                    <input type="password" name="password" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">This password will be used for doctor login</p>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeAddDoctorModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Add Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div id="editDoctorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
            <h3 class="text-2xl font-bold mb-6 text-gray-800">
                <i class="fas fa-edit text-blue-600 mr-2"></i>Edit Doctor
            </h3>
            <form method="POST" id="editDoctorForm">
                <input type="hidden" name="action" value="update_doctor">
                <input type="hidden" name="doctor_id" id="edit_doctor_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-1"></i>Doctor Name *
                        </label>
                        <input type="text" name="doctor_name" id="edit_doctor_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1"></i>Phone Number *
                        </label>
                        <input type="text" name="phone_number" id="edit_phone_number" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-card mr-1"></i>SLMC Number
                        </label>
                        <input type="text" name="slmc_no" id="edit_slmc_no" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-nurse mr-1"></i>Assign Staff Member
                        </label>
                        <select name="staff_member_id" id="edit_staff_member_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">No Staff Assignment</option>
                            <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['name']); ?> - <?php echo htmlspecialchars($staff['phone_number']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        To change the password, use the "Reset Password" button in the actions menu.
                    </p>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeEditDoctorModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Update Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-2xl font-bold mb-6 text-gray-800">
                <i class="fas fa-key text-yellow-600 mr-2"></i>Reset Password
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="doctor_id" id="reset_doctor_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Doctor Name</label>
                    <input type="text" id="reset_doctor_name" readonly 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-1"></i>New Password *
                    </label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeResetPasswordModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-key mr-2"></i>Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-2xl font-bold mb-6 text-red-600">
                <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Deletion
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="delete_doctor">
                <input type="hidden" name="doctor_id" id="delete_doctor_id">
                
                <div class="mb-6">
                    <p class="text-gray-700 mb-4">Are you sure you want to delete this doctor?</p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="font-semibold text-red-800" id="delete_doctor_name"></p>
                        <p class="text-sm text-red-700 mt-2">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            This action cannot be undone. All associated data will be removed.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteConfirmModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-trash mr-2"></i>Delete Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add Doctor Modal
        function openAddDoctorModal() {
            $('#addDoctorModal').removeClass('hidden');
        }

        function closeAddDoctorModal() {
            $('#addDoctorModal').addClass('hidden');
        }

        // Edit Doctor Modal
        function openEditDoctorModal(doctor) {
            $('#edit_doctor_id').val(doctor.id);
            $('#edit_doctor_name').val(doctor.doctor_name);
            $('#edit_phone_number').val(doctor.phone_number);
            $('#edit_slmc_no').val(doctor.slmc_no || '');
            $('#edit_staff_member_id').val(doctor.staff_member_id || '');
            $('#editDoctorModal').removeClass('hidden');
        }

        function closeEditDoctorModal() {
            $('#editDoctorModal').addClass('hidden');
        }

        // Reset Password Modal
        function openResetPasswordModal(doctorId, doctorName) {
            $('#reset_doctor_id').val(doctorId);
            $('#reset_doctor_name').val(doctorName);
            $('#resetPasswordModal').removeClass('hidden');
        }

        function closeResetPasswordModal() {
            $('#resetPasswordModal').addClass('hidden');
        }

        // Delete Confirmation Modal
        function deleteDoctorConfirm(doctorId, doctorName) {
            $('#delete_doctor_id').val(doctorId);
            $('#delete_doctor_name').text(doctorName);
            $('#deleteConfirmModal').removeClass('hidden');
        }

        function closeDeleteConfirmModal() {
            $('#deleteConfirmModal').addClass('hidden');
        }

        // Close modals on ESC key
        $(document).keyup(function(e) {
            if (e.key === "Escape") {
                $('.fixed.inset-0').addClass('hidden');
            }
        });
    </script>
</body>
</html>