<?php
session_start();

// Clear all patient session variables
unset($_SESSION['patient_logged_in']);
unset($_SESSION['patient_account_id']);
unset($_SESSION['patient_id']);
unset($_SESSION['patient_type']);
unset($_SESSION['patient_doctor_id']);
unset($_SESSION['patient_name']);
unset($_SESSION['patient_phone']);

// Clear registration session variables if any
unset($_SESSION['registration_patient_type']);
unset($_SESSION['registration_patient_id']);
unset($_SESSION['registration_patient_data']);
unset($_SESSION['registration_linked_kids']);
unset($_SESSION['registration_otp']);
unset($_SESSION['registration_password']);
unset($_SESSION['registration_otp_time']);

// Destroy session
session_destroy();

// Redirect to login
header('Location: patient_login.php');
exit;
?>