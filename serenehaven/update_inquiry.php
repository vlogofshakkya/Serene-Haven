<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include 'db_connect.php';

// Get JSON data from request
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($data['id']) || !isset($data['status'])) {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit;
}

$id = intval($data['id']);
$status = $data['status'];

// Ensure the status is valid
$validStatuses = ["pending", "accepted", "rejected"];
if (!in_array($status, $validStatuses)) {
    echo json_encode(["success" => false, "message" => "Invalid status value."]);
    exit;
}

// Update the database
$sql = "UPDATE inquiries SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Inquiry status updated successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
