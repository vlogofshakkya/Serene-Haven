<?php
require_once '../config.php';

$message = '';
$error = '';
$selected_doctor_id = null;

// Get all doctors for the dropdown
$stmt = $pdo->prepare("SELECT id, doctor_name FROM doctors ORDER BY doctor_name");
$stmt->execute();
$all_doctors = $stmt->fetchAll();

// Handle image upload
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $doctor_id = (int)$_POST['doctor_id'];
    $image_type = $_POST['image_type'] ?? '';
    
    if (!$doctor_id) {
        $error = 'Please select a doctor.';
    } else if (!in_array($image_type, ['logo', 'signature', 'seal'])) {
        $error = 'Invalid image type selected.';
    } else if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid image file.';
    } else {
        $file = $_FILES['image'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Only JPEG, PNG, and GIF files are allowed.';
        }
        
        // Validate file size (max 5MB)
        else if ($file['size'] > 5 * 1024 * 1024) {
            $error = 'File size must be less than 5MB.';
        }
        
        else {
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/doctor_images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'doctor_' . $doctor_id . '_' . $image_type . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Deactivate existing image of same type
                    $stmt = $pdo->prepare("UPDATE doctor_images SET is_active = 0 WHERE doctor_id = ? AND image_type = ?");
                    $stmt->execute([$doctor_id, $image_type]);
                    
                    // Insert new image record
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_images (doctor_id, image_type, file_name, file_path, file_size, mime_type, uploaded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $doctor_id,
                        $image_type,
                        $filename,
                        $file_path,
                        $file['size'],
                        $file['type'],
                        $doctor_id // Using doctor_id as uploaded_by since no session
                    ]);
                    
                    $pdo->commit();
                    $message = ucfirst($image_type) . ' uploaded successfully!';
                    $selected_doctor_id = $doctor_id; // Keep doctor selected after upload
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    unlink($file_path); // Delete uploaded file
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Failed to upload file.';
            }
        }
    }
}

// Handle image deletion
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $image_id = (int)$_POST['image_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    
    try {
        // Get image details before deletion
        $stmt = $pdo->prepare("SELECT file_path FROM doctor_images WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$image_id, $doctor_id]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM doctor_images WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$image_id, $doctor_id]);
            
            // Delete physical file
            if (file_exists($image['file_path'])) {
                unlink($image['file_path']);
            }
            
            $message = 'Image deleted successfully!';
            $selected_doctor_id = $doctor_id; // Keep doctor selected after deletion
        } else {
            $error = 'Image not found.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting image: ' . $e->getMessage();
    }
}

// Get current doctor images if a doctor is selected
$current_images = [];
$doctor_info = null;

if (isset($_POST['doctor_id']) && $_POST['doctor_id']) {
    $selected_doctor_id = (int)$_POST['doctor_id'];
}

if ($selected_doctor_id) {
    $stmt = $pdo->prepare("
        SELECT id, image_type, file_name, file_path, file_size, created_at 
        FROM doctor_images 
        WHERE doctor_id = ? AND is_active = 1 
        ORDER BY image_type, created_at DESC
    ");
    $stmt->execute([$selected_doctor_id]);
    $current_images = $stmt->fetchAll();

    // Get doctor info
    $stmt = $pdo->prepare("SELECT doctor_name FROM doctors WHERE id = ?");
    $stmt->execute([$selected_doctor_id]);
    $doctor_info = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctor Images - Doctor Wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .doctor-selection {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .upload-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .upload-section h2 {
            margin-top: 0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .current-images {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .current-images h2 {
            margin-top: 0;
            color: #333;
        }
        
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .image-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
        }
        
        .image-type {
            font-weight: bold;
            color: #007bff;
            text-transform: capitalize;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .image-preview {
            width: 100%;
            max-height: 200px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background: white;
        }
        
        .image-info {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .no-images {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        
        .no-doctor-selected {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .file-requirements {
            background: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            color: #6c757d;
        }
        
        .file-requirements ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <a href="ad_index.php" class="back-link">&larr; Back to Dashboard</a>
    
    <div class="header">
        <h1>Manage Doctor Images</h1>
        <p>Upload logos, signatures, and seals for doctors</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="doctor-selection">
        <h2>Select Doctor</h2>
        <form method="POST">
            <div class="form-group">
                <label for="doctor_select">Choose Doctor:</label>
                <select name="doctor_id" id="doctor_select" onchange="this.form.submit()">
                    <option value="">Select a doctor...</option>
                    <?php foreach ($all_doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>" 
                                <?php echo ($selected_doctor_id == $doctor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    
    <?php if ($selected_doctor_id && $doctor_info): ?>
        <div class="upload-section">
            <h2>Upload New Image for <?php echo htmlspecialchars($doctor_info['doctor_name']); ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="doctor_id" value="<?php echo $selected_doctor_id; ?>">
                
                <div class="form-group">
                    <label for="image_type">Image Type:</label>
                    <select name="image_type" id="image_type" required>
                        <option value="">Select image type...</option>
                        <option value="logo">Logo (for receipt header)</option>
                        <option value="signature">Signature (for receipt signature)</option>
                        <option value="seal">Seal (for receipt authentication)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="image">Select Image File:</label>
                    <input type="file" name="image" id="image" accept="image/*" required>
                </div>
                
                <button type="submit" class="btn">Upload Image</button>
                
                <div class="file-requirements">
                    <strong>File Requirements:</strong>
                    <ul>
                        <li>Supported formats: JPEG, PNG, GIF</li>
                        <li>Maximum file size: 5MB</li>
                        <li>Recommended sizes:
                            <ul>
                                <li>Logo: 512x512 pixels (square)</li>
                                <li>Signature: 300x100 pixels (landscape)</li>
                                <li>Seal: 256x256 pixels (square)</li>
                            </ul>
                        </li>
                        <li>Use high-quality images for best print results</li>
                    </ul>
                </div>
            </form>
        </div>
        
        <div class="current-images">
            <h2>Current Images for <?php echo htmlspecialchars($doctor_info['doctor_name']); ?></h2>
            
            <?php if (empty($current_images)): ?>
                <div class="no-images">
                    No images uploaded yet for this doctor. Upload logo, signature, and seal above.
                </div>
            <?php else: ?>
                <div class="image-grid">
                    <?php foreach ($current_images as $image): ?>
                        <div class="image-card">
                            <div class="image-type"><?php echo htmlspecialchars($image['image_type']); ?></div>
                            
                            <?php if (file_exists($image['file_path'])): ?>
                                <img src="<?php echo htmlspecialchars($image['file_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($image['image_type']); ?>" 
                                     class="image-preview">
                            <?php else: ?>
                                <div style="height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
                                    File not found
                                </div>
                            <?php endif; ?>
                            
                            <div class="image-info">
                                <strong>File:</strong> <?php echo htmlspecialchars($image['file_name']); ?><br>
                                <strong>Size:</strong> <?php echo number_format($image['file_size'] / 1024, 1); ?> KB<br>
                                <strong>Uploaded:</strong> <?php echo date('M d, Y H:i', strtotime($image['created_at'])); ?>
                            </div>
                            
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Are you sure you want to delete this image?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                <input type="hidden" name="doctor_id" value="<?php echo $selected_doctor_id; ?>">
                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    
    <?php else: ?>
        <div class="no-doctor-selected">
            Please select a doctor above to manage their images.
        </div>
    <?php endif; ?>

    <script>
        // Preview selected file
        document.getElementById('image')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPEG, PNG, and GIF files are allowed');
                    e.target.value = '';
                    return;
                }
            }
        });
    </script>
</body>
</html>