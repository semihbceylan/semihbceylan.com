<?php
/**
 * Contact form handler for semihbceylan.com
 *
 * Works on standard cPanel shared hosting (uses PHP's built-in mail()).
 * Upload this file next to index.html. Edit the settings below if needed.
 */

// ================== SETTINGS ==================

// Where messages are delivered.
$RECIPIENT_EMAIL = 'semihbceylan@gmail.com';

// "From" address. It MUST be an address on your own domain, otherwise most
// providers mark the mail as spam or reject it. Create it in cPanel > Email Accounts
// (it doesn't need to be checked; it only has to exist).
$FROM_EMAIL = 'noreply@semihbceylan.com';
$FROM_NAME  = 'semihbceylan.com';

// Spam protection.
$MIN_SECONDS_ON_PAGE = 3;    // forms submitted faster than this are bots
$MAX_PER_HOUR        = 5;    // messages allowed per visitor IP per hour

// ==============================================

header('X-Content-Type-Options: nosniff');

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

	// Fallback page for visitors without JavaScript.
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
	// Remove line breaks and control characters (prevents email header injection).
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

// ---------- Only accept POST ----------
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Allow: POST');
	respond(false, 'Method not allowed.', 405);
}

// ---------- Honeypot: real people never fill this ----------
if (!empty($_POST['website'])) {
	respond(true, 'Thanks! Your message has been sent.'); // pretend success so bots don't retry
}

// ---------- Too-fast submission ----------
$started = isset($_POST['started']) ? (int) $_POST['started'] : 0;
if ($started > 0 && (time() - $started) < $MIN_SECONDS_ON_PAGE) {
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
if (str_length($message) < 10) {
	respond(false, 'Your message is a bit short. Please add a few more words.', 422);
}
if (str_length($message) > 5000) {
	respond(false, 'Your message is too long (max 5000 characters).', 422);
}

// ---------- Rate limit per IP ----------
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$rateDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sbc_contact_rate';
if (!is_dir($rateDir)) {
	@mkdir($rateDir, 0700, true);
}
$rateFile = $rateDir . DIRECTORY_SEPARATOR . hash('sha256', $ip . '|' . __FILE__) . '.json';
$now = time();
$hits = array();
if (is_readable($rateFile)) {
	$decoded = json_decode((string) @file_get_contents($rateFile), true);
	if (is_array($decoded)) {
		foreach ($decoded as $t) {
			if (is_int($t) && $t > $now - 3600) {
				$hits[] = $t;
			}
		}
	}
}
if (count($hits) >= $MAX_PER_HOUR) {
	respond(false, 'You have sent several messages already. Please try again later or email me directly.', 429);
}

// ---------- Build and send the email ----------
$mailSubject = 'Portfolio contact: ' . ($subject !== '' ? $subject : 'Message from ' . $name);

$body  = "New message from the contact form on semihbceylan.com\n";
$body .= str_repeat('-', 50) . "\n";
$body .= "Name:    {$name}\n";
$body .= "Email:   {$email}\n";
$body .= 'Subject: ' . ($subject !== '' ? $subject : '(none)') . "\n";
$body .= str_repeat('-', 50) . "\n\n";
$body .= $message . "\n\n";
$body .= str_repeat('-', 50) . "\n";
$body .= 'Sent: ' . date('Y-m-d H:i:s T') . "\n";
$body .= "IP: {$ip}\n";
$body .= "Reply to this email to answer {$name} directly.\n";

$headers   = array();
$headers[] = 'From: ' . encode_header($FROM_NAME) . ' <' . $FROM_EMAIL . '>';
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'Content-Transfer-Encoding: 8bit';
$headers[] = 'X-Mailer: PHP/' . phpversion();
$headerString = implode("\r\n", $headers);

// Setting the envelope sender (-f) improves deliverability; retry without it
// in case the host doesn't allow it.
$sent = @mail($RECIPIENT_EMAIL, encode_header($mailSubject), $body, $headerString, '-f' . $FROM_EMAIL);
if (!$sent) {
	$sent = @mail($RECIPIENT_EMAIL, encode_header($mailSubject), $body, $headerString);
}

if (!$sent) {
	respond(false, 'Sorry, the message could not be sent right now. Please email me directly at ' . $RECIPIENT_EMAIL . '.', 500);
}

$hits[] = $now;
@file_put_contents($rateFile, json_encode($hits), LOCK_EX);

respond(true, "Thanks, {$name}! Your message has been sent. I'll get back to you soon.");
