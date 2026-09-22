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

// ── Site URL (no trailing slash) ──────────────────────────────────────────────
// Used in email notifications and internal links. Update when switching domains.
define('SITE_URL', 'https://bvtu.ca');

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

// ── Expense Reimbursement Portal ──────────────────────────────────────────────
// Email that always has admin + Local President access to the expense portal.
// Falls back to PROD_ADMIN_EMAIL if this is not set.
// define('EXPENSE_ADMIN_EMAIL', 'your-email@bctf.ca');

// ── SMTP Email (required for all site emails) ─────────────────────────────────
// Create a mailbox in Hostinger hPanel → Emails → Email Accounts, then fill in:
// define('SMTP_HOST',      'smtp.hostinger.com');
// define('SMTP_PORT',      587);                   // 587 = STARTTLS, 465 = SSL
// define('SMTP_USER',      'noreply@bvtu.ca');      // the mailbox you created
// define('SMTP_PASS',      'your-mailbox-password');
// define('SMTP_FROM_NAME', 'BVTU Member Portal');

// ── OneDrive Doc Upload (optional) ────────────────────────────────────────────
// From an Azure app registration — see the setup steps on members/onedrive.php.
// Redirect URI must be exactly: https://<your-host>/members/onedrive-connect.php
// define('MS_CLIENT_ID',     'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
// define('MS_CLIENT_SECRET', 'the Value from Certificates & secrets, not the ID');
// define('MS_TENANT',        'consumers'); // personal Microsoft account

// ── Contacts / Mailchimp (optional) ───────────────────────────────────────────
// API key: Mailchimp → Account → Extras → API keys. The data centre is the
// suffix of the key itself (…-us21), so no separate setting is needed.
// Audience ID: Audience → Settings → Audience name and defaults.
// Webhook secret: any long random string you choose; it goes in the webhook URL
// and is what authenticates incoming calls, so treat it like a password.
// NOTE: the newsletter tool already uses MC_API_KEY. If it is defined above,
// do NOT add it again — PHP keeps the first definition and warns about the rest.
// Contacts shares the same key; only MC_LIST_ID and MC_WEBHOOK_SECRET are new.
// define('MC_API_KEY',        'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us21');
// define('MC_LIST_ID',        'xxxxxxxxxx');
// define('MC_WEBHOOK_SECRET', 'a-long-random-string');
