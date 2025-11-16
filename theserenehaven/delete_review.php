<?php
include 'db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$reviewId = $data['id'];
$password = $data['password'];

if (!$reviewId || !$password) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit();
}

// Check if the review exists
$stmt = $conn->prepare("SELECT password FROM reviews WHERE id = ?");
$stmt->bind_param("i", $reviewId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Review not found"]);
    exit();
}

$stmt->bind_result($hashedPassword);
$stmt->fetch();

if (password_verify($password, $hashedPassword)) {
    // Delete the review
    $deleteStmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $deleteStmt->bind_param("i", $reviewId);

    if ($deleteStmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Failed to delete review"]);
    }

    $deleteStmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Incorrect password"]);
}

$stmt->close();
$conn->close();
?>
