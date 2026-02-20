<?php
header('Content-Type: application/json');
error_reporting(E_ALL);

$steps = [];

try {
    // Step 1: Config
    $config = require __DIR__ . '/config.php';
    $steps[] = 'config loaded';

    // Step 2: Rate limiting
    $rateDir = $config['security']['rate_limit_dir'];
    $steps[] = 'rate_limit_dir: ' . $rateDir;

    if (!is_dir($rateDir)) {
        mkdir($rateDir, 0700, true);
    }
    $steps[] = 'rate dir OK';

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateFile = $rateDir . md5($ip) . '.json';
    $rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
    $steps[] = 'rate data loaded: ' . gettype($rateData);

    $now = time();
    $rateData = array_filter($rateData ?? [], function ($ts) use ($now) {
        return ($now - $ts) < 60;
    });
    $steps[] = 'rate filtered: ' . count($rateData);

    // Step 3: Token check
    $token = $_SERVER['HTTP_X_RSVP_TOKEN'] ?? '';
    $steps[] = 'token present: ' . (!empty($token) ? 'yes' : 'no');

    if (empty($token)) {
        $steps[] = 'WOULD RETURN 403 here';
        echo json_encode(['steps' => $steps]);
        exit;
    }

    // Step 4: Token validation
    $parts = explode('.', $token);
    $steps[] = 'token parts: ' . count($parts);

    $payload = json_decode(base64_decode($parts[0]), true);
    $signature = $parts[1] ?? '';
    $steps[] = 'payload decoded';

    $expectedSig = hash_hmac('sha256', $parts[0], $config['security']['token_secret']);
    $steps[] = 'sig match: ' . (hash_equals($expectedSig, $signature) ? 'yes' : 'no');
    $steps[] = 'token expired: ' . (($payload['exp'] ?? 0) < time() ? 'yes' : 'no');

    // Step 5: Parse input
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $steps[] = 'input name: ' . $name;

    // Step 6: MongoDB
    require_once __DIR__ . '/MongoAtlas.php';
    $steps[] = 'MongoAtlas loaded';

    $mongo = new MongoAtlas($config['mongodb']);
    $steps[] = 'MongoAtlas constructed';

    $guest = $mongo->findOne('guests', [
        'tenant_id' => $config['tenant_id'],
        'name_lower' => strtolower($name),
    ]);
    $steps[] = 'MongoDB query done: ' . ($guest ? 'found' : 'not found');

    echo json_encode(['steps' => $steps, 'guest' => $guest], JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    $steps[] = 'ERROR: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine();
    echo json_encode(['steps' => $steps]);
}
