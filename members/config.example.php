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
