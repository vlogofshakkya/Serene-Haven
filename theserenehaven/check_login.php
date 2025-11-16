<?php
session_start();
$logged_in = isset($_SESSION['username']);
echo json_encode(['logged_in' => $logged_in]);
?>