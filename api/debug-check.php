<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';

// Show structure without revealing actual secret values
$mongoConfig = $config['mongodb'] ?? [];
echo json_encode([
    'mongodb_keys' => array_keys($mongoConfig),
    'data_api_url_set' => !empty($mongoConfig['data_api_url']),
    'data_api_url_length' => strlen($mongoConfig['data_api_url'] ?? ''),
    'api_key_set' => !empty($mongoConfig['api_key']),
    'api_key_length' => strlen($mongoConfig['api_key'] ?? ''),
    'cluster_set' => !empty($mongoConfig['cluster']),
    'database_set' => !empty($mongoConfig['database']),
    'connection_string_set' => !empty($mongoConfig['connection_string']),
    'config_top_keys' => array_keys($config),
], JSON_PRETTY_PRINT);
