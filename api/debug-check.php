<?php
header('Content-Type: application/json');

$steps = [];

try {
    $config = require __DIR__ . '/config.php';
    $steps[] = 'config loaded';

    // Rate limiting (with fix)
    $rateDir = $config['security']['rate_limit_dir'];
    if (!is_dir($rateDir)) mkdir($rateDir, 0700, true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateFile = $rateDir . md5($ip) . '.json';
    $rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
    if (!is_array($rateData)) $rateData = [];
    $now = time();
    $rateData = array_filter($rateData, function ($ts) use ($now) {
        return is_int($ts) && ($now - $ts) < 60;
    });
    $steps[] = 'rate limiting OK';

    // Token
    $token = $_SERVER['HTTP_X_RSVP_TOKEN'] ?? '';
    if (!empty($token)) {
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[0]), true);
        $expectedSig = hash_hmac('sha256', $parts[0], $config['security']['token_secret']);
        $steps[] = 'token valid: ' . (hash_equals($expectedSig, $parts[1] ?? '') ? 'yes' : 'no');
    } else {
        $steps[] = 'no token';
    }

    // Input
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $steps[] = 'name: ' . $name;

    // Sanitize
    $name = strip_tags($name);
    $steps[] = 'regex match: ' . (preg_match('/^[\p{L}\d\s\'\-\.]+$/u', $name) ? 'yes' : 'no');

    // MongoDB
    require_once __DIR__ . '/MongoAtlas.php';
    $steps[] = 'MongoAtlas required';

    $mongo = new MongoAtlas($config['mongodb']);
    $steps[] = 'MongoAtlas constructed';

    $guest = $mongo->findOne('guests', [
        'tenant_id' => $config['tenant_id'],
        'name_lower' => strtolower($name),
    ]);
    $steps[] = 'findOne done: ' . ($guest ? 'found' : 'null');

    if (!$guest) {
        $guests = $mongo->find('guests', [
            'tenant_id' => $config['tenant_id'],
            'name_lower' => ['$regex' => preg_quote(strtolower($name), '/'), '$options' => 'i'],
        ]);
        $steps[] = 'fuzzy search: ' . count($guests) . ' results';
    }

    echo json_encode(['steps' => $steps, 'guest' => $guest], JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    $steps[] = 'ERROR: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
    echo json_encode(['steps' => $steps]);
}
