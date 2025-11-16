<?php
include 'db_connect.php';
header('Content-Type: application/json');

$query = "SELECT id, name, review, rating, DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at FROM reviews ORDER BY created_at DESC";
$result = $conn->query($query);

$reviews = [];

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row; 
}

echo json_encode($reviews);
$conn->close();
?>
