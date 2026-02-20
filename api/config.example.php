<?php
/**
 * Configuration — Copy this file to config.php and fill in your values.
 * NEVER commit config.php to version control.
 */

return [
    // ─── MySQL Database ──────────────────────────────────────────
    // Create the database + user in cPanel → MySQL Databases.
    // Import schema.sql via phpMyAdmin to create the guests table.
    'mysql' => [
        'host'     => 'localhost',
        'dbname'   => 'YOUR_DB_NAME',
        'username' => 'YOUR_DB_USER',
        'password' => 'YOUR_DB_PASSWORD',
    ],

    // ─── Tenant ID ────────────────────────────────────────────────
    // Unique identifier for this wedding website.
    // All guest records for this site use this tenant_id.
    'tenant_id' => 'isaac-and-corine',

    // ─── SMTP (PHPMailer) ─────────────────────────────────────────
    'mail' => [
        'host'       => 'mail.isaacandcorine.com',
        'port'       => 465,
        'encryption' => 'ssl',      // 'ssl' or 'tls'
        'username'   => 'rsvp@isaacandcorine.com',
        'password'   => 'YOUR_EMAIL_PASSWORD',
        'from_email' => 'rsvp@isaacandcorine.com',
        'from_name'  => 'Isaac & Corine Wedding',
        'to_email'   => 'couple@example.com',
        'to_name'    => 'Isaac & Corine',
    ],

    // ─── Security ─────────────────────────────────────────────────
    'security' => [
        // Used to sign CSRF tokens — change to a long random string
        'token_secret'  => 'CHANGE_THIS_TO_A_RANDOM_64_CHAR_STRING',
        // Token lifetime in seconds (10 minutes)
        'token_ttl'     => 600,
        // Rate limit directory (auto-created)
        'rate_limit_dir' => __DIR__ . '/rate_limits/',
        // Max requests per IP per minute
        'rate_limit_rpm' => 10,
    ],
];
