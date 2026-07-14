<?php
// submit_beta_signup.php
// Collects iOS or Android beta tester applications and emails them via SMTP (STARTTLS + AUTH LOGIN).

function strip_header_injection(string $value): string {
    return trim(str_replace(["\r", "\n"], '', $value));
}

function smtp_send(string $host, int $port, string $username, string $password, string $from, string $replyTo, string $to, string $subject, string $body): array {
    $errno = 0;
    $errstr = '';
    $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
    if (!$sock) {
        return [false, "Could not connect to SMTP server: {$errstr}"];
    }
    stream_set_timeout($sock, 15);

    $read = function () use ($sock) {
        $data = '';
        while ($line = fgets($sock, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function (string $cmd) use ($sock) {
        fwrite($sock, $cmd . "\r\n");
    };

    $expect = function (string $response, string $code) {
        return strpos($response, $code) === 0 || strpos($response, "\n{$code}") !== false || substr($response, 0, 3) === $code;
    };

    $banner = $read();
    if (!$expect($banner, '220')) {
        fclose($sock);
        return [false, "Unexpected SMTP banner: {$banner}"];
    }

    $write("EHLO localhost");
    $ehlo = $read();
    if (!$expect($ehlo, '250')) {
        fclose($sock);
        return [false, "EHLO failed: {$ehlo}"];
    }

    $write("STARTTLS");
    $starttls = $read();
    if (!$expect($starttls, '220')) {
        fclose($sock);
        return [false, "STARTTLS failed: {$starttls}"];
    }

    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($sock);
        return [false, "TLS negotiation failed"];
    }

    $write("EHLO localhost");
    $ehlo2 = $read();
    if (!$expect($ehlo2, '250')) {
        fclose($sock);
        return [false, "EHLO (after TLS) failed: {$ehlo2}"];
    }

    $write("AUTH LOGIN");
    $authResp = $read();
    if (!$expect($authResp, '334')) {
        fclose($sock);
        return [false, "AUTH LOGIN not accepted: {$authResp}"];
    }

    $write(base64_encode($username));
    $userResp = $read();
    if (!$expect($userResp, '334')) {
        fclose($sock);
        return [false, "SMTP username rejected: {$userResp}"];
    }

    $write(base64_encode($password));
    $passResp = $read();
    if (!$expect($passResp, '235')) {
        fclose($sock);
        return [false, "SMTP authentication failed: {$passResp}"];
    }

    $write("MAIL FROM:<{$from}>");
    $mailFrom = $read();
    if (!$expect($mailFrom, '250')) {
        fclose($sock);
        return [false, "MAIL FROM failed: {$mailFrom}"];
    }

    $write("RCPT TO:<{$to}>");
    $rcptTo = $read();
    if (!$expect($rcptTo, '250')) {
        fclose($sock);
        return [false, "RCPT TO failed: {$rcptTo}"];
    }

    $write("DATA");
    $dataResp = $read();
    if (!$expect($dataResp, '354')) {
        fclose($sock);
        return [false, "DATA command rejected: {$dataResp}"];
    }

    $headers = "From: <{$from}>\r\n"
        . "Reply-To: <{$replyTo}>\r\n"
        . "To: <{$to}>\r\n"
        . "Subject: {$subject}\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    $write($headers . "\r\n" . $body . "\r\n.");
    $sendResp = $read();
    if (!$expect($sendResp, '250')) {
        fclose($sock);
        return [false, "Failed to send message: {$sendResp}"];
    }

    $write("QUIT");
    $read();
    fclose($sock);
    return [true, ''];
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
                <a href="/support">Support</a>
                <a href="/signup" class="btn-nav-signup active">Join Beta</a>
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = strip_header_injection($_POST['first_name'] ?? '');
    $lastName = strip_header_injection($_POST['last_name'] ?? '');
    $email = strip_header_injection($_POST['email'] ?? '');
    $platform = strip_header_injection($_POST['platform'] ?? 'android');

    if ($firstName === '' || $lastName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        $html = <<<HTML
            <div class="success-card" style="text-align: center;">
                <div class="success-icon" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">✕</div>
                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Invalid Application Details</h2>
                <p style="color: var(--text-dim); margin-bottom: 25px;">Please fill out all fields with a valid email address.</p>
                <a href="/signup" class="btn-submit" style="text-decoration: none; display: inline-block;">Return to Sign-Up Page</a>
            </div>
HTML;
        render_page("Invalid Details", $html);
        exit();
    }

    $smtpHost = getenv('SMTP_HOST');
    $smtpPort = (int) getenv('SMTP_PORT');
    $smtpUsername = getenv('SMTP_USERNAME');
    $smtpPassword = getenv('SMTP_PASSWORD');

    if (!$smtpHost || !$smtpPort || !$smtpUsername || !$smtpPassword) {
        error_log("Beta signup email failed: SMTP environment variables are not configured");
        http_response_code(500);
        $html = <<<HTML
            <div class="success-card" style="text-align: center;">
                <div class="success-icon" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">✕</div>
                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Service Unavailable</h2>
                <p style="color: var(--text-dim); margin-bottom: 25px;">Error submitting your application due to a server configuration issue. Please try again later.</p>
                <a href="/signup" class="btn-submit" style="text-decoration: none; display: inline-block;">Return to Sign-Up Page</a>
            </div>
HTML;
        render_page("Submission Error", $html);
        exit();
    }

    $toEmail = 'kyber.android@tomw.net';
    $replyToEmail = 'no-reply@tomw.net';

    $subject = "New " . ucfirst($platform) . " Beta Tester Application";
    $body = "A new " . ucfirst($platform) . " beta tester application has been submitted:\n\n"
        . "First Name: {$firstName}\n"
        . "Last Name: {$lastName}\n"
        . "Email: {$email}\n"
        . "Platform: " . ucfirst($platform) . "\n";

    [$ok, $error] = smtp_send(
        $smtpHost,
        $smtpPort,
        $smtpUsername,
        $smtpPassword,
        $smtpUsername,
        $replyToEmail,
        $toEmail,
        $subject,
        $body
    );

    if ($ok) {
        $html = <<<HTML
            <div class="success-card" style="text-align: center;">
                <div class="success-icon">✓</div>
                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Application Submitted!</h2>
                <p style="color: var(--text-dim); margin-bottom: 25px;">Thank you! Your application for the KyberChat <b>{$platform}</b> beta has been received successfully. We will be in touch shortly.</p>
                <a href="/" class="btn-submit" style="text-decoration: none; display: inline-block;">Back to Homepage</a>
            </div>
HTML;
        render_page("Application Submitted", $html);
    } else {
        error_log("Beta signup email failed: {$error}");
        http_response_code(500);
        $html = <<<HTML
            <div class="success-card" style="text-align: center;">
                <div class="success-icon" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">✕</div>
                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 12px;">Submission Error</h2>
                <p style="color: var(--text-dim); margin-bottom: 25px;">We were unable to deliver your application. Please check your network and try again later.</p>
                <a href="/signup" class="btn-submit" style="text-decoration: none; display: inline-block;">Return to Sign-Up Page</a>
            </div>
HTML;
        render_page("Delivery Error", $html);
    }
} else {
    header("Location: /signup");
    exit();
}
?>
