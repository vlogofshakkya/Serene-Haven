<?php
$host = "sql207.infinityfree.com";
$username = "if0_38838607";
$password = "anuhas2011";
$dbname = "if0_38838607_reviews";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
