<?php
// submit_beta_signup.php
// Collects Android beta tester applications and emails them via SMTP (STARTTLS + AUTH LOGIN).

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
        return [false, "DATA command failed: {$dataResp}"];
    }

    $headers = "From: KyberChat Beta <{$from}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Reply-To: <{$replyTo}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Escape lines starting with a lone "." per RFC 5321 dot-stuffing.
    $escapedBody = preg_replace('/^\./m', '..', $body);

    $write($headers . "\r\n" . $escapedBody . "\r\n.");
    $sendResp = $read();
    if (!$expect($sendResp, '250')) {
        fclose($sock);
        return [false, "Message send failed: {$sendResp}"];
    }

    $write("QUIT");
    fclose($sock);

    return [true, ''];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = strip_header_injection($_POST['first_name'] ?? '');
    $lastName = strip_header_injection($_POST['last_name'] ?? '');
    $email = strip_header_injection($_POST['email'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "<h2>Please fill out all fields with a valid email address.</h2>";
        echo "<p><a href='android_beta.html'>Return to Sign-Up Page</a></p>";
        exit();
    }

    $smtpHost = getenv('SMTP_HOST');
    $smtpPort = (int) getenv('SMTP_PORT');
    $smtpUsername = getenv('SMTP_USERNAME');
    $smtpPassword = getenv('SMTP_PASSWORD');

    if (!$smtpHost || !$smtpPort || !$smtpUsername || !$smtpPassword) {
        error_log("Beta signup email failed: SMTP environment variables are not configured");
        http_response_code(500);
        echo "<h2>Error submitting your application. Please try again later.</h2>";
        echo "<p><a href='android_beta.html'>Return to Sign-Up Page</a></p>";
        exit();
    }

    $toEmail = 'kyber.android@tomw.net';
    $replyToEmail = 'no-reply@tomw.net';

    $subject = "New Android Beta Tester Application";
    $body = "A new Android beta tester application has been submitted:\n\n"
        . "First Name: {$firstName}\n"
        . "Last Name: {$lastName}\n"
        . "Email: {$email}\n";

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
        echo "<h2>Thank you! Your application has been submitted successfully.</h2>";
        echo "<p><a href='android_beta.html'>Return to Sign-Up Page</a></p>";
    } else {
        error_log("Beta signup email failed: {$error}");
        http_response_code(500);
        echo "<h2>Error submitting your application. Please try again later.</h2>";
        echo "<p><a href='android_beta.html'>Return to Sign-Up Page</a></p>";
    }
} else {
    header("Location: android_beta.html");
    exit();
}

