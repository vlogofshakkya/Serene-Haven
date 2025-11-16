<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
    $lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $service = isset($_POST['service']) ? trim($_POST['service']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    $status = "pending";
    $date = date("Y-m-d H:i:s");

    // Debugging - Log received data
    file_put_contents("debug_log.txt", print_r($_POST, true), FILE_APPEND);

    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($service) || empty($message)) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }

    $sql = "INSERT INTO inquiries (first_name, last_name, email, phone, service, message, newsletter, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "SQL Error: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("sssssssis", $firstName, $lastName, $email, $phone, $service, $message, $newsletter, $status, $date);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Inquiry submitted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
}
?>
