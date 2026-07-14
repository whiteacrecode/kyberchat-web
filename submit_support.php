<?php
// submit_support.php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database credentials
    $servername = getenv('DB_HOST') ?: "localhost";
    $username = getenv('DB_USER') ?: "root"; 
    $password = getenv('DB_PASSWORD') ?: ""; 
    $dbname = getenv('DB_NAME') ?: "kyberchat_db"; // Update with actual DB name

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO support_requests (name, email, type, message) VALUES (?, ?, ?, ?)");
    
    if ($stmt) {
        // Set parameters
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $type = $_POST['type'] ?? '';
        $message = $_POST['message'] ?? '';
        
        $stmt->bind_param("ssss", $name, $email, $type, $message);

        if ($stmt->execute()) {
            echo "<h2>Thank you! Your request has been submitted successfully.</h2>";
            echo "<p><a href='/support'>Return to Support Page</a></p>";
        } else {
            echo "<h2>Error submitting request. Please try again later.</h2>";
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "<h2>Database error: Failed to prepare statement.</h2>";
    }

    $conn->close();
} else {
    // Redirect back to form if accessed directly without POST
    header("Location: /support");
    exit();
}
?>
