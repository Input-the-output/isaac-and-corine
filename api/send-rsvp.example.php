<?php
/**
 * Isaac & Corine Wedding — RSVP Mailer
 * COPY this file to send-rsvp.php and fill in your credentials.
 * NEVER commit send-rsvp.php to git.
 */

// ============ CONFIGURATION ============
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USER',       'your-email@gmail.com');
define('SMTP_PASS',       'xxxx xxxx xxxx xxxx');
define('MAIL_TO',         'your-email@gmail.com');
define('MAIL_TO_NAME',    'Isaac & Corine');
define('ALLOWED_ORIGIN',  'https://isaacandcorine.com');
define('TOKEN_SECRET',    'CHANGE_THIS_TO_A_RANDOM_STRING');
define('RATE_LIMIT_MAX',  5);
define('RATE_LIMIT_HOURS', 1);
// =======================================
// See send-rsvp.php for full implementation
