<?php
session_start();
require_once '../config.php';

// Check if user has profile access
if (!isset($_SESSION['profile_logged_in']) || $_SESSION['profile_logged_in'] !== true) {
    header('Location: profile_login.php');
    exit;
}

$doctor_id = $_SESSION['doctor_id'] ?? null;

if (!$doctor_id) {
    header('Location: profile_login.php');
    exit;
}

try {
    // Get doctor information to set up main session
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    
    if ($doctor) {
        // Set up main system session variables (this bridges to your main dashboard)
        $_SESSION['user_id'] = $doctor['id'];
        $_SESSION['user_type'] = 'doctor';
        $_SESSION['doctor_id'] = $doctor['id'];
        $_SESSION['doctor_name'] = $doctor['doctor_name'];
        $_SESSION['logged_in'] = true;
        
        // Keep profile session as well
        $_SESSION['profile_logged_in'] = true;
        
        // Redirect to main dashboard
        header('Location: ../index.php');
        exit;
    } else {
        // Doctor not found, redirect back to profile login
        session_destroy();
        header('Location: profile_login.php?error=doctor_not_found');
        exit;
    }
    
} catch (Exception $e) {
    // Database error, redirect back to profile login
    header('Location: profile_login.php?error=database_error');
    exit;
}
?>