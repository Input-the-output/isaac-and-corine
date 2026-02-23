<?php
/**
 * RSVP Submission Handler
 *
 * POST /api/send-rsvp.php
 * Body: { "guest_id": "...", "name": "...", "plus_one_name": "...", "attending": "yes|no" }
 * Headers: X-RSVP-Token: <token>
 *
 * Updates the guest record in MySQL and sends a notification email.
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

$guestId                 = $input['guest_id'] ?? null;
$name                    = trim(strip_tags($input['name'] ?? ''));
$plusOneName             = trim(strip_tags($input['plus_one_name'] ?? ''));
$attending               = $input['attending'] ?? '';
$preweddingAttending     = $input['prewedding_attending'] ?? null;
$plusOneAttending         = $input['plus_one_attending'] ?? null;
$plusOnePreweddingAttending = $input['plus_one_prewedding_attending'] ?? null;

if (empty($name) || !in_array($attending, ['yes', 'no'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid submission']);
    exit;
}

// ─── Update guest record in MySQL ─────────────────────────────
require_once __DIR__ . '/Database.php';
$db = new Database($config['mysql']);

$rsvpStatus = $attending === 'yes' ? 'attending' : 'declined';

// Build the update data
$updateData = [
    'rsvp_status' => $rsvpStatus,
    'rsvp_date'   => date('Y-m-d H:i:s'),
];

// Pre-wedding status
if ($preweddingAttending !== null) {
    $updateData['prewedding_status'] = $preweddingAttending === 'yes' ? 'attending' : 'declined';
}

// Plus-one name
if (!empty($plusOneName)) {
    $updateData['plus_one_name'] = $plusOneName;
}

// Plus-one wedding status
if ($plusOneAttending !== null) {
    $updateData['plus_one_status'] = $plusOneAttending === 'yes' ? 'attending' : 'declined';
}

// Plus-one pre-wedding status
if ($plusOnePreweddingAttending !== null) {
    $updateData['plus_one_prewedding_status'] = $plusOnePreweddingAttending === 'yes' ? 'attending' : 'declined';
}

// Build filter — use guest_id if available, otherwise name + tenant
$filter = ['tenant_id' => $config['tenant_id']];
if ($guestId) {
    $filter['id'] = (int) $guestId;
} else {
    $filter['name_lower'] = strtolower($name);
}

$db->updateOne('guests', $filter, $updateData);

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

    // Send to all recipients defined in config
    $toEmails = (array) $config['mail']['to_email'];
    $toNames  = (array) ($config['mail']['to_name'] ?? '');
    foreach ($toEmails as $i => $email) {
        $mail->addAddress($email, $toNames[$i] ?? '');
    }

    $statusLabel = $attending === 'yes' ? 'Joyfully Attending' : 'Regretfully Declining';
    $statusEmoji = $attending === 'yes' ? '&#10003;' : '&#10007;';
    $statusColor = $attending === 'yes' ? '#587042' : '#c0392b';
    $dateStr = date('F j, Y \a\t g:i A');

    // Build HTML email
    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f0eb;font-family:Georgia,serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0eb;padding:30px 0;">'
        . '<tr><td align="center">'
        . '<table width="500" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">'
        // Header
        . '<tr><td style="background:#587042;padding:28px 30px;text-align:center;">'
        . '<h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:400;letter-spacing:1px;">New RSVP Received</h1>'
        . '</td></tr>'
        // Guest name
        . '<tr><td style="padding:30px 30px 10px;text-align:center;">'
        . '<p style="margin:0;color:#a9b494;font-size:12px;text-transform:uppercase;letter-spacing:2px;">Guest</p>'
        . '<h2 style="margin:8px 0 0;color:#2c2c2c;font-size:26px;font-weight:400;">' . htmlspecialchars($name) . '</h2>'
        . '</td></tr>'
        // Status
        . '<tr><td style="padding:15px 30px 5px;text-align:center;">'
        . '<table cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>'
        . '<td style="padding:8px 20px;background:' . $statusColor . ';border-radius:20px;color:#ffffff;font-size:14px;letter-spacing:0.5px;">'
        . $statusEmoji . '&nbsp; ' . htmlspecialchars($statusLabel)
        . '</td></tr></table>'
        . '</td></tr>';

    // Pre-wedding
    if ($preweddingAttending !== null) {
        $preweddingLabel = $preweddingAttending === 'yes' ? 'Attending' : 'Declining';
        $preweddingColor = $preweddingAttending === 'yes' ? '#587042' : '#c0392b';
        $body .= '<tr><td style="padding:12px 30px 0;text-align:center;">'
            . '<p style="margin:0 0 6px;color:#a9b494;font-size:12px;text-transform:uppercase;letter-spacing:2px;">Pre-Wedding</p>'
            . '<span style="color:' . $preweddingColor . ';font-size:15px;">' . htmlspecialchars($preweddingLabel) . '</span>'
            . '</td></tr>';
    }

    // Divider
    $body .= '<tr><td style="padding:20px 30px 0;">'
        . '<hr style="border:none;border-top:1px solid #efdfd5;margin:0;">'
        . '</td></tr>';

    // Plus one
    if (!empty($plusOneName)) {
        $body .= '<tr><td style="padding:20px 30px 5px;text-align:center;">'
            . '<p style="margin:0;color:#a9b494;font-size:12px;text-transform:uppercase;letter-spacing:2px;">Plus One</p>'
            . '<h3 style="margin:8px 0 0;color:#2c2c2c;font-size:20px;font-weight:400;">' . htmlspecialchars($plusOneName) . '</h3>'
            . '</td></tr>';
        if ($plusOneAttending !== null) {
            $plusOneLabel = $plusOneAttending === 'yes' ? 'Attending' : 'Declining';
            $plusOneColor = $plusOneAttending === 'yes' ? '#587042' : '#c0392b';
            $body .= '<tr><td style="padding:10px 30px 0;text-align:center;">'
                . '<table cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>'
                . '<td style="padding:6px 16px;background:' . $plusOneColor . ';border-radius:20px;color:#ffffff;font-size:13px;">'
                . htmlspecialchars($plusOneLabel) . '</td></tr></table>'
                . '</td></tr>';
        }
        if ($plusOnePreweddingAttending !== null) {
            $plusOnePreweddingLabel = $plusOnePreweddingAttending === 'yes' ? 'Attending' : 'Declining';
            $plusOnePreweddingColor = $plusOnePreweddingAttending === 'yes' ? '#587042' : '#c0392b';
            $body .= '<tr><td style="padding:10px 30px 0;text-align:center;">'
                . '<p style="margin:0 0 6px;color:#a9b494;font-size:12px;text-transform:uppercase;letter-spacing:2px;">Pre-Wedding</p>'
                . '<span style="color:' . $plusOnePreweddingColor . ';font-size:14px;">' . htmlspecialchars($plusOnePreweddingLabel) . '</span>'
                . '</td></tr>';
        }
    }

    // Footer
    $body .= '<tr><td style="padding:25px 30px;text-align:center;">'
        . '<p style="margin:0;color:#999;font-size:12px;">' . $dateStr . '</p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject = "RSVP: " . $name . " - " . $statusLabel;
    $mail->Body    = $body;

    $mail->send();
    file_put_contents(__DIR__ . '/mail_debug.log', date('Y-m-d H:i:s') . " SUCCESS: Email sent to " . implode(', ', $toEmails) . "\n", FILE_APPEND);
} catch (\Exception $e) {
    // Log but don't fail — the RSVP was recorded in the database
    error_log('RSVP mail error: ' . $e->getMessage());
    file_put_contents(__DIR__ . '/mail_debug.log', date('Y-m-d H:i:s') . " FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
}

echo json_encode(['success' => true]);
