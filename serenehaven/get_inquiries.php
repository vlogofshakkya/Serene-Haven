<?php
include 'db_connect.php';

$sql = "SELECT * FROM inquiries ORDER BY created_at DESC";
$result = $conn->query($sql);

$inquiries = [];
while ($row = $result->fetch_assoc()) {
    $inquiries[] = $row;
}

echo json_encode($inquiries);

$conn->close();
?>
