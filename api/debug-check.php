<?php
header('Content-Type: application/json');

$checks = [];

// 1. Config loads?
$configPath = __DIR__ . '/config.php';
$checks['config_exists'] = file_exists($configPath);

if ($checks['config_exists']) {
    $config = require $configPath;
    $checks['config_is_array'] = is_array($config);
    $checks['has_mongodb'] = isset($config['mongodb']);
    $checks['has_security'] = isset($config['security']);
    $checks['has_rate_limit_dir'] = isset($config['security']['rate_limit_dir']);
    $checks['rate_limit_dir'] = $config['security']['rate_limit_dir'] ?? 'NOT SET';
    $checks['rate_dir_exists'] = is_dir($config['security']['rate_limit_dir'] ?? '');
    $checks['rate_dir_writable'] = is_writable(dirname($config['security']['rate_limit_dir'] ?? ''));
}

// 2. MongoAtlas.php exists?
$checks['mongo_atlas_exists'] = file_exists(__DIR__ . '/MongoAtlas.php');

// 3. cURL available?
$checks['curl_available'] = function_exists('curl_init');

// 4. PHP version
$checks['php_version'] = PHP_VERSION;

// 5. Try mkdir
$rateDir = $config['security']['rate_limit_dir'] ?? __DIR__ . '/rate_limits/';
if (!is_dir($rateDir)) {
    $mkdirResult = @mkdir($rateDir, 0700, true);
    $checks['mkdir_result'] = $mkdirResult;
    $checks['mkdir_error'] = $mkdirResult ? null : error_get_last()['message'] ?? 'unknown';
} else {
    $checks['mkdir_result'] = 'already exists';
}

echo json_encode($checks, JSON_PRETTY_PRINT);
