#!/usr/bin/env php
<?php
/**
 * Seed 5 test guest accounts into MySQL.
 * Run once via CLI: php api/seed-test-guests.php
 */

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Error: api/config.php not found.\n");
    exit(1);
}
$config = require $configPath;

require_once __DIR__ . '/Database.php';
$db = new Database($config['mysql']);

for ($i = 1; $i <= 5; $i++) {
    $name = "Georges ITO test {$i}";
    $nameLower = strtolower($name);

    // Check if already exists
    $existing = $db->findOne('guests', [
        'tenant_id'  => $config['tenant_id'],
        'name_lower' => $nameLower,
    ]);

    if ($existing) {
        echo "Skipped (already exists): {$name}\n";
        continue;
    }

    $id = $db->insertOne('guests', [
        'tenant_id'    => $config['tenant_id'],
        'name'         => $name,
        'name_lower'   => $nameLower,
        'plus_one'     => ($i === 1) ? 1 : 0,
        'plus_one_name'=> ($i === 1) ? 'Test Plus One' : null,
        'rsvp_status'  => 'pending',
    ]);

    echo "Inserted: {$name} (id: {$id})\n";
}

echo "\nDone! 5 test accounts ready.\n";
