<?php
// Laboratory Configuration
require_once '../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if laboratory user is logged in
function requireLaboratory() {
    if (!isset($_SESSION['lab_user_id']) || !isset($_SESSION['lab_user_type']) || $_SESSION['lab_user_type'] !== 'laboratory') {
        header('Location: lab_login.php');
        exit;
    }
}

// Function to get laboratory user info
function getLabUserInfo() {
    global $pdo;
    
    if (!isset($_SESSION['lab_user_id'])) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM laboratory_users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['lab_user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>