<?php
/**
 * Contact form handler for semihbceylan.com
 * SMTP version — works when mail() is disabled.
 */

header('X-Content-Type-Options: nosniff');

// Configure credentials in the server environment, never in source control.
$SMTP_HOST   = getenv('SMTP_HOST') ?: 'mail.semihbceylan.com';
$SMTP_PORT   = (int) (getenv('SMTP_PORT') ?: 587);
$SMTP_USER   = getenv('SMTP_USER') ?: 'noreply@semihbceylan.com';
$SMTP_PASS   = getenv('SMTP_PASS') ?: '';
$FROM_EMAIL  = getenv('SMTP_FROM') ?: 'noreply@semihbceylan.com';
$FROM_NAME   = 'semihbceylan.com';
$RECIPIENT   = getenv('CONTACT_RECIPIENT') ?: 'semihbceylan@gmail.com';
// ===================================================

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond($ok, $message, $status = 200)
{
    global $wantsJson;
    http_response_code($status);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('ok' => $ok, 'message' => $message));
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    $safe  = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $color = $ok ? '#3ecf8e' : '#ff6b81';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Contact | Semih B. Ceylan</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#0f1114;color:#ecedf3;font-family:system-ui,sans-serif">'
        . '<div style="max-width:440px;padding:2rem;text-align:center">'
        . '<p style="font-size:1.15rem;color:' . $color . '">' . $safe . '</p>'
        . '<p><a href="./#contact" style="color:#4f9fe0">&larr; Back to the website</a></p>'
        . '</div></body></html>';
    exit;
}

function clean_line($value, $maxLength)
{
    $value = is_string($value) ? $value : '';
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
}

function str_length($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function encode_header($value)
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_read_response($fp)
{
    $response = '';
    do {
        $line = fgets($fp, 4096);
        if ($line === false) {
            return false;
        }
        $response .= $line;
    } while (preg_match('/^\d{3}-/', $line));
    return $response;
}

function smtp_cmd($fp, $cmd)
{
    fwrite($fp, $cmd . "\r\n");
    return smtp_read_response($fp);
}

function smtp_send($to, $subject, $body, $from, $fromName, $replyTo)
{
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS;

    $useTls = ($SMTP_PORT === 587);
    $scheme = $useTls ? 'tcp://' : 'ssl://';
    $domain = substr(strrchr($from, '@'), 1);

    $fp = @stream_socket_client($scheme . $SMTP_HOST . ':' . $SMTP_PORT, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        return array(false, 'Could not connect to SMTP server: ' . $errstr . ' (' . $errno . ')');
    }
    stream_set_timeout($fp, 30);

    // Sends a command and checks the reply code; returns an error string on failure, null on success.
    $step = function ($cmd, $expect, $label) use ($fp) {
        $resp = $cmd === null ? smtp_read_response($fp) : smtp_cmd($fp, $cmd);
        if ($resp === false || substr($resp, 0, 3) !== $expect) {
            return $label . ' failed: ' . ($resp === false ? 'no response' : trim($resp));
        }
        return null;
    };
    $fail = function ($error) use ($fp) {
        fclose($fp);
        return array(false, $error);
    };

    if ($err = $step(null, '220', 'Greeting')) return $fail($err);
    if ($err = $step('EHLO ' . $domain, '250', 'EHLO')) return $fail($err);

    if ($useTls) {
        if ($err = $step('STARTTLS', '220', 'STARTTLS')) return $fail($err);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail('Could not enable TLS');
        }
        if ($err = $step('EHLO ' . $domain, '250', 'EHLO after TLS')) return $fail($err);
    }

    if ($err = $step('AUTH LOGIN', '334', 'AUTH LOGIN')) return $fail($err);
    if ($err = $step(base64_encode($SMTP_USER), '334', 'Username')) return $fail($err);
    if ($err = $step(base64_encode($SMTP_PASS), '235', 'Password')) return $fail($err);

    if ($err = $step('MAIL FROM:<' . $from . '>', '250', 'MAIL FROM')) return $fail($err);
    if ($err = $step('RCPT TO:<' . $to . '>', '250', 'RCPT TO')) return $fail($err);
    if ($err = $step('DATA', '354', 'DATA')) return $fail($err);

    $headers = array();
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
    $headers[] = 'From: ' . encode_header($fromName) . ' <' . $from . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Reply-To: <' . $replyTo . '>';
    $headers[] = 'Subject: ' . encode_header($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    // SMTP needs CRLF line endings, and lines starting with "." must be escaped.
    $body = str_replace(array("\r\n", "\r"), "\n", $body);
    $body = preg_replace('/^\./m', '..', $body);
    $body = str_replace("\n", "\r\n", $body);

    $msg = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    if ($err = $step($msg, '250', 'Message')) return $fail($err);

    smtp_cmd($fp, 'QUIT');
    fclose($fp);
    return array(true, '');
}

// ---------- Only accept POST ----------
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(false, 'Method not allowed.', 405);
}

// ---------- Honeypot ----------
if (!empty($_POST['website'])) {
    respond(true, 'Thanks! Your message has been sent.');
}

// ---------- Too-fast submission ----------
$started = isset($_POST['started']) ? (int) $_POST['started'] : 0;
if ($started > 0 && (time() - $started) < 3) {
    respond(true, 'Thanks! Your message has been sent.');
}

// ---------- Validate ----------
$name    = clean_line(isset($_POST['name']) ? $_POST['name'] : '', 100);
$email   = clean_line(isset($_POST['email']) ? $_POST['email'] : '', 254);
$subject = clean_line(isset($_POST['subject']) ? $_POST['subject'] : '', 150);
$message = isset($_POST['message']) && is_string($_POST['message']) ? trim($_POST['message']) : '';
$message = str_replace(array("\r\n", "\r"), "\n", $message);
$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'Please fill in your name, email and message.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}
if (strlen($message) < 10) {
    respond(false, 'Your message is a bit short. Please add a few more words.', 422);
}
if (strlen($message) > 5000) {
    respond(false, 'Your message is too long (max 5000 characters).', 422);
}

$mailSubject = 'Portfolio contact: ' . ($subject !== '' ? $subject : 'Message from ' . $name);

$body  = "New message from the contact form on semihbceylan.com\n";
$body .= str_repeat('-', 50) . "\n";
$body .= "Name:    {$name}\n";
$body .= "Email:   {$email}\n";
$body .= 'Subject: ' . ($subject !== '' ? $subject : '(none)') . "\n";
$body .= str_repeat('-', 50) . "\n\n";
$body .= $message . "\n\n";
$body .= str_repeat('-', 50) . "\n";
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$body .= 'Sent: ' . date('Y-m-d H:i:s T') . "\n";
$body .= "IP: {$ip}\n";
$body .= "Reply to this email to answer {$name} directly.\n";

$result = smtp_send($RECIPIENT, $mailSubject, $body, $FROM_EMAIL, $FROM_NAME, $email);

if ($result[0] === false) {
    error_log('contact.php SMTP error: ' . $result[1]);
    respond(false, 'Sorry, the message could not be sent right now. Please email me directly at semihbceylan@gmail.com.', 500);
}

respond(true, "Thanks, {$name}! Your message has been sent. I'll get back to you soon.");
