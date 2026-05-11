<?php
// ============================================================
//  BVTU Members Portal — Database Configuration
//
//  INSTRUCTIONS:
//  1. Copy this file and rename it to config.php
//  2. Fill in your Hostinger database credentials
//     (found in Hostinger hPanel → Databases → MySQL Databases)
//  3. config.php is gitignored — it will NEVER be uploaded to GitHub
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');   // e.g. u123456789_bvtu
define('DB_USER', 'your_database_user');   // e.g. u123456789_bvtu
define('DB_PASS', 'your_database_password');

// Security key — change this to any long random string
define('SESSION_SECRET', 'change-this-to-a-long-random-string-xyz');

// ── Claude API (for ask.php, ca-ask.php, Pro-D receipt scanning) ─────────────
// define('CLAUDE_API_KEY', 'sk-ant-...');

// ── Pro-D Portal ──────────────────────────────────────────────────────────────
// Email that gets admin access to the Pro-D review queue (approve/reject claims).
// Set to the president's or treasurer's email.
// define('PROD_ADMIN_EMAIL', 'your-email@bctf.ca');

// ── Mailchimp (Newsletter Archive) ───────────────────────────────────────────
// API key from Mailchimp → Account → Extras → API Keys
// define('MC_API_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us9');

// ── CA Assistant token monitoring (ca-ask.php) ───────────────────────────────
// Monthly token budget before an alert email is sent to the union president.
// Default: 500,000 tokens/month (~$0.50 at Haiku pricing).
// Set lower for stricter monitoring, e.g. 200000 for ~$0.20/month.
// define('TOKEN_ALERT_THRESHOLD', 500000);
// define('TOKEN_ALERT_EMAIL',     'lp54@bctf.ca');
