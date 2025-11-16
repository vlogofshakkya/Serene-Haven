<?php
include 'db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$name = $data['name'];
$review = $data['review'];
$rating = $data['rating'];
$password = password_hash($data['password'], PASSWORD_DEFAULT); // Hash the password

if ($name && $review && $rating && $password) {
    $stmt = $conn->prepare("INSERT INTO reviews (name, review, rating, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $name, $review, $rating, $password);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
}

$conn->close();
?>
