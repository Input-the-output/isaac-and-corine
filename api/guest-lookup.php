<?php
/**
 * Guest Lookup API
 *
 * POST /api/guest-lookup.php
 * Body: { "name": "John Doe" }
 * Headers: X-RSVP-Token: <token>
 *
 * Returns the guest record (with plus-one info) if found.
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
$rateFile = $rateDir . md5($ip) . '.json';
$rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
if (!is_array($rateData)) $rateData = [];
$now = time();
// Clean old entries (older than 60s)
$rateData = array_filter($rateData, function ($ts) use ($now) {
    return is_int($ts) && ($now - $ts) < 60;
});
if (count($rateData) >= ($config['security']['rate_limit_rpm'] ?? 10)) {
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
$name = trim($input['name'] ?? '');

if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid name.']);
    exit;
}

// Sanitize — strip tags, allow only letters, spaces, hyphens, apostrophes
$name = strip_tags($name);
if (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid name.']);
    exit;
}

// ─── MongoDB lookup ───────────────────────────────────────────
require_once __DIR__ . '/MongoAtlas.php';
$mongo = new MongoAtlas($config['mongodb']);

// Case-insensitive search by name within this tenant
$guest = $mongo->findOne('guests', [
    'tenant_id' => $config['tenant_id'],
    'name_lower' => strtolower($name),
]);

if (!$guest) {
    // Try partial match (first + last name fuzzy)
    $guests = $mongo->find('guests', [
        'tenant_id' => $config['tenant_id'],
        'name_lower' => ['$regex' => preg_quote(strtolower($name), '/'), '$options' => 'i'],
    ]);

    if (count($guests) === 1) {
        $guest = $guests[0];
    } elseif (count($guests) > 1) {
        // Multiple matches — ask user to be more specific
        echo json_encode([
            'error' => 'Multiple guests found. Please enter your full name as it appears on your invitation.',
        ]);
        exit;
    } else {
        echo json_encode(['guest' => null]);
        exit;
    }
}

// Return guest data (only safe fields)
echo json_encode([
    'guest' => [
        '_id'            => $guest['_id'] ?? null,
        'name'           => $guest['name'] ?? '',
        'plus_one'       => $guest['plus_one'] ?? false,
        'plus_one_name'  => $guest['plus_one_name'] ?? null,
        'rsvp_status'    => $guest['rsvp_status'] ?? 'pending',
    ],
]);
