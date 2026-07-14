<?php
// submit_support.php
// Collects support tickets and writes them to the database.

function strip_header_injection(string $value): string {
    return trim(str_replace(["\r", "\n"], '', $value));
}

function render_page(string $title, string $htmlContent) {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - KyberChat</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="/" class="logo-link">
                <img src="kyberchat_logo.png" alt="KyberChat Logo" class="logo-icon">
                <span class="logo-text">KyberChat</span>
            </a>
            <div class="nav-links">
                <a href="/about">Technology</a>
                <a href="/privacy">Privacy</a>
                <a href="/support" class="active">Support</a>
                <a href="/signup" class="btn-nav-signup">Join Beta</a>
            </div>
        </div>
    </nav>

    <div class="container form-container">
        <div class="form-card">
            {$htmlContent}
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2026 WhiteAcre Software. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
HTML;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database configuration
    $servername = "localhost";
    $username = getenv('DB_USER') ?: "root";
    $password = getenv('DB_PASS') ?: "";
    $dbname = getenv('DB_NAME') ?: "kyberchat_support";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        http_response_code(500);
        $html = <<<HTML
            <div class="success-card" style="text-align: center;">
                <div class="success-icon" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">✕</div>
                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Database Offline</h2>
                <p style="color: var(--text-dim); margin-bottom: 25px;">Unable to submit ticket due to a direct database connection failure. Please try again later.</p>
                <a href="/support" class="btn-submit" style="text-decoration: none; display: inline-block;">Return to Support Desk</a>
            </div>
HTML;
        render_page("Database Error", $html);
        exit();
    }

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO support_requests (name, email, type, message) VALUES (?, ?, ?, ?)");
    
    if ($stmt) {
        // Set parameters
        $name = strip_header_injection($_POST['name'] ?? '');
        $email = strip_header_injection($_POST['email'] ?? '');
        $type = strip_header_injection($_POST['type'] ?? '');
        $message = strip_header_injection($_POST['message'] ?? '');
        
        $stmt->bind_param("ssss", $name, $email, $type, $message);

        if ($stmt->execute()) {
            $html = <<<HTML
                <div class="success-card" style="text-align: center;">
                    <div class="success-icon">✓</div>
                    <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Ticket Received!</h2>
                    <p style="color: var(--text-dim); margin-bottom: 25px;">Thank you! Your support ticket has been logged successfully. An engineer will investigate and reply shortly.</p>
                    <a href="/" class="btn-submit" style="text-decoration: none; display: inline-block;">Back to Homepage</a>
                </div>
HTML;
            render_page("Ticket Received", $html);
        } else {
            error_log("Insert execution failed: " . $stmt->error);
            http_response_code(500);
            $html = <<<HTML
                <div class="success-card" style="text-align: center;">
                    <div class="success-icon" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">✕</div>
                    <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Error Submitting Request</h2>
                    <p style="color: var(--text-dim); margin-bottom: 25px;">An unexpected error occurred during statement execution. Please try again later.</p>
                    <a href="/support" class="btn-submit" style="text-decoration: none; display: inline-block;">Return to Support Desk</a>
                </div>
HTML;
            render_page("Submission Error", $html);
        }

        $stmt->close();
    } else {
        error_log("Statement prepare failed: " . $conn->error);
        http_response_code(500);
        $html = <<<HTML
            <div class="success-card" style="text-align: center;">
                <div class="success-icon" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">✕</div>
                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Pre-compilation Error</h2>
                <p style="color: var(--text-dim); margin-bottom: 25px;">Failed to prepare the SQL statement for storage. Please try again later.</p>
                <a href="/support" class="btn-submit" style="text-decoration: none; display: inline-block;">Return to Support Desk</a>
            </div>
HTML;
        render_page("Database Error", $html);
    }

    $conn->close();
} else {
    // Redirect back to form if accessed directly without POST
    header("Location: /support");
    exit();
}
?>
