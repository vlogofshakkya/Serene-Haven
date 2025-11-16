<?php
// Laboratory Logout
session_start();

// Destroy all laboratory session variables
unset($_SESSION['lab_user_id']);
unset($_SESSION['lab_user_type']);
unset($_SESSION['lab_name']);

// Redirect to login page
header('Location: lab_login.php');
exit;
?>