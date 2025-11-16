<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log incoming request
error_log("Received request: " . json_encode($_POST));

$servername = "sql207.infinityfree.com";
$username = "if0_38838607";
$password = "anuhas2011";
$dbname = "if0_38838607_hotel_db";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Validate input data
    if (!isset($_POST['checkIn']) || !isset($_POST['checkOut']) || !isset($_POST['roomType'])) {
        throw new Exception("Missing required fields");
    }

    $checkIn = $_POST['checkIn'];
    $checkOut = $_POST['checkOut'];
    $roomType = $_POST['roomType'];

    // Log validated data
    error_log("Processing request - Check-in: $checkIn, Check-out: $checkOut, Room Type: $roomType");

    $roomLimits = [
        "deluxe" => 2,
        "suite" => 2,
        "family" => 1
    ];

    // Validate room type
    if (!isset($roomLimits[$roomType])) {
        throw new Exception("Invalid room type");
    }

    $query = "SELECT COUNT(*) as booked FROM bookings WHERE room_type = ? AND (check_in < ? AND check_out > ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("sss", $roomType, $checkOut, $checkIn);
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $bookedRooms = $row['booked'];
    $availableRooms = $roomLimits[$roomType] - $bookedRooms;

    // Log results
    error_log("Query results - Booked: $bookedRooms, Available: $availableRooms");

    $response = [
        "available" => $availableRooms > 0,
        "message" => $availableRooms > 0 ? "Room is available ($availableRooms left)" : "No rooms available",
        "status" => "success"
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in check-availability.php: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred: " . $e->getMessage(),
        "available" => false
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>