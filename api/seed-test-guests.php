#!/usr/bin/env php
<?php
/**
 * Seed 5 test guest accounts into MongoDB.
 * Run once via CLI: php api/seed-test-guests.php
 */

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Error: api/config.php not found.\n");
    exit(1);
}
$config = require $configPath;

require_once __DIR__ . '/MongoAtlas.php';
$mongo = new MongoAtlas($config['mongodb']);

$now = date('c');

for ($i = 1; $i <= 5; $i++) {
    $name = "Georges ITO test {$i}";
    $nameLower = strtolower($name);

    // Check if already exists
    $existing = $mongo->findOne('guests', [
        'tenant_id'  => $config['tenant_id'],
        'name_lower' => $nameLower,
    ]);

    if ($existing) {
        echo "Skipped (already exists): {$name}\n";
        continue;
    }

    $doc = [
        'tenant_id'    => $config['tenant_id'],
        'name'         => $name,
        'name_lower'   => $nameLower,
        'plus_one'     => ($i === 1), // Only test 1 gets a plus-one
        'plus_one_name'=> ($i === 1) ? 'Test Plus One' : null,
        'rsvp_status'  => 'pending',
        'rsvp_date'    => null,
        'created_at'   => $now,
        'updated_at'   => $now,
    ];

    $id = $mongo->insertOne('guests', $doc);
    echo "Inserted: {$name} (id: {$id})\n";
}

echo "\nDone! 5 test accounts ready.\n";
