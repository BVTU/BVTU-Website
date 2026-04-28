<?php
/**
 * diag.php — temporary diagnostic page
 * DELETE THIS FILE after fixing the login issue.
 * Visit: /members/diag.php
 */

// Basic auth so this isn't exposed publicly
if (($_GET['key'] ?? '') !== 'bvtu-diag-2026') {
    http_response_code(403);
    die('Forbidden — add ?key=bvtu-diag-2026 to the URL.');
}

require_once __DIR__ . '/config.php';

echo "<h2>BVTU Login Diagnostics</h2><pre>";

// 1. Config constants
echo "=== Config ===\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
echo "DB_PASS: " . (defined('DB_PASS') ? (DB_PASS ? '(set)' : '(empty)') : 'NOT DEFINED') . "\n\n";

// 2. PDO extension
echo "=== PHP Extensions ===\n";
echo "PDO loaded:       " . (extension_loaded('pdo')       ? 'YES' : 'NO') . "\n";
echo "PDO MySQL loaded: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "\n\n";

// 3. Database connection
echo "=== Database Connection ===\n";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Connection: OK\n\n";

    // 4. Tables
    echo "=== Tables ===\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if ($tables) {
        foreach ($tables as $t) echo "  - $t\n";
    } else {
        echo "  No tables found — setup.sql has not been run.\n";
    }

    echo "\n=== valid_employee_numbers rows ===\n";
    if (in_array('valid_employee_numbers', $tables)) {
        $rows = $pdo->query("SELECT * FROM valid_employee_numbers")->fetchAll();
        echo $rows ? print_r($rows, true) : "  Table is empty — no employee numbers added yet.\n";
    } else {
        echo "  Table does not exist.\n";
    }

    echo "\n=== members rows ===\n";
    if (in_array('members', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
        echo "  $count registered member(s).\n";
    } else {
        echo "  Table does not exist.\n";
    }

} catch (PDOException $e) {
    echo "Connection FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== PHP Version ===\n";
echo phpversion() . "\n";

echo "</pre>";
echo "<p style='color:red;font-weight:bold'>⚠️ Delete members/diag.php from the server once you're done diagnosing.</p>";
