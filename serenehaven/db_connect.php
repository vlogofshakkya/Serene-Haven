<?php
$host = "sql213.infinityfree.com";
$username = "if0_37486342";
$password = "HtZtPMwf0POmCk";
$dbname = "if0_37486342_inquary";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
