<?php
$servername = "sql207.infinityfree.com";
$username = "if0_38838607";
$password = "anuhas2011";
$dbname = "if0_38838607_accounts";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if POST data exists
if(isset($_POST['username']) && isset($_POST['password'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Fetch stored password
    $sql = "SELECT password FROM users WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();

    echo "Entered Username: " . htmlspecialchars($user) . "<br>";
    echo "Entered Password: " . htmlspecialchars($pass) . "<br>";
    //echo "Stored Password in DB: " . htmlspecialchars($hashed_password) . "<br>";

    // Verify password
    if ($stmt->num_rows > 0 && $pass == $hashed_password) {
        echo "✅ Login Successful!";
        header("Location: http://serenehavenbk.ct.ws/booking.html");
        exit();
    } else {
        echo "❌ Username or Password is Incorrect!";
    }

    $stmt->close();
} else {
    echo "Please submit the login form";
}

$conn->close();
?>