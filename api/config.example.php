<?php
/**
 * Configuration — Copy this file to config.php and fill in your values.
 * NEVER commit config.php to version control.
 */

return [
    // ─── MongoDB Atlas Data API ───────────────────────────────────
    // 1. Create a free cluster at https://cloud.mongodb.com
    // 2. Enable the Data API: App Services → Data API → Enable
    // 3. Create an API key: App Services → Authentication → API Keys
    'mongodb' => [
        'data_api_url' => 'https://data.mongodb-api.com/app/YOUR_APP_ID/endpoint/data/v1',
        'api_key'      => 'YOUR_DATA_API_KEY',
        'cluster'      => 'Cluster0',
        'database'     => 'wedding_websites',
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
