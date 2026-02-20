<?php
/**
 * RSVP Submission Handler
 *
 * POST /api/send-rsvp.php
 * Body: { "guest_id": "...", "name": "...", "plus_one_name": "...", "attending": "yes|no" }
 * Headers: X-RSVP-Token: <token>
 *
 * Updates the guest record in MongoDB and sends a notification email.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Load config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration missing']);
    exit;
}
$config = require $configPath;

// ─── Rate limiting ────────────────────────────────────────────
$rateDir = $config['security']['rate_limit_dir'];
if (!is_dir($rateDir)) {
    mkdir($rateDir, 0700, true);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $rateDir . md5($ip . '_rsvp') . '.json';
$rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
if (!is_array($rateData)) $rateData = [];
$now = time();
$rateData = array_filter($rateData, function ($ts) use ($now) {
    return is_int($ts) && ($now - $ts) < 60;
});
if (count($rateData) >= 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please wait a moment.']);
    exit;
}
$rateData[] = $now;
file_put_contents($rateFile, json_encode(array_values($rateData)), LOCK_EX);

// ─── Token validation ─────────────────────────────────────────
$token = $_SERVER['HTTP_X_RSVP_TOKEN'] ?? '';
if (empty($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Missing security token']);
    exit;
}

$parts = explode('.', $token);
if (count($parts) !== 2) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$payload = json_decode(base64_decode($parts[0]), true);
$signature = $parts[1];

if (!$payload || !isset($payload['exp'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$expectedSig = hash_hmac('sha256', $parts[0], $config['security']['token_secret']);
if (!hash_equals($expectedSig, $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

if ($payload['exp'] < time()) {
    http_response_code(403);
    echo json_encode(['error' => 'Token expired. Please refresh and try again.']);
    exit;
}

// ─── Parse input ──────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

$guestId      = $input['guest_id'] ?? null;
$name         = trim(strip_tags($input['name'] ?? ''));
$plusOneName  = trim(strip_tags($input['plus_one_name'] ?? ''));
$attending    = $input['attending'] ?? '';

if (empty($name) || !in_array($attending, ['yes', 'no'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid submission']);
    exit;
}

// ─── Update guest record in MongoDB ───────────────────────────
require_once __DIR__ . '/MongoAtlas.php';
$mongo = new MongoAtlas($config['mongodb']);

$rsvpStatus = $attending === 'yes' ? 'attending' : 'declined';

// Build filter — use guest_id if available, otherwise name + tenant
$filter = ['tenant_id' => $config['tenant_id']];
if ($guestId) {
    $filter['_id'] = MongoAtlas::objectId($guestId);
} else {
    $filter['name_lower'] = strtolower($name);
}

$mongo->updateOne('guests', $filter, [
    '$set' => [
        'rsvp_status' => $rsvpStatus,
        'rsvp_date'   => date('c'),
        'updated_at'  => date('c'),
    ],
]);

// ─── Send notification email ──────────────────────────────────
try {
    require_once __DIR__ . '/PHPMailer-7.0.2/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer-7.0.2/src/SMTP.php';
    require_once __DIR__ . '/PHPMailer-7.0.2/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['mail']['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['mail']['username'];
    $mail->Password   = $config['mail']['password'];
    $mail->SMTPSecure = $config['mail']['encryption'];
    $mail->Port       = $config['mail']['port'];

    $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
    $mail->addAddress($config['mail']['to_email'], $config['mail']['to_name']);

    $statusLabel = $attending === 'yes' ? 'Joyfully Attending' : 'Regretfully Declining';
    $plusOneInfo = $plusOneName ? "\nPlus One: {$plusOneName}" : '';

    $mail->isHTML(false);
    $mail->Subject = "RSVP: {$name} — {$statusLabel}";
    $mail->Body    = "New RSVP Received\n"
        . "──────────────────\n"
        . "Name: {$name}{$plusOneInfo}\n"
        . "Response: {$statusLabel}\n"
        . "Date: " . date('F j, Y g:i A') . "\n"
        . "──────────────────\n";

    $mail->send();
} catch (\Exception $e) {
    // Log but don't fail — the RSVP was recorded in the database
    error_log('RSVP mail error: ' . $e->getMessage());
}

echo json_encode(['success' => true]);
