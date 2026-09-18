<?php
require_once __DIR__ . '/config.php';

// Everything the union does is in Vancouver time. Set here so it applies to
// every page, not only those including one of the *-db.php helpers.
date_default_timezone_set('America/Vancouver');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            // NOW() and CURRENT_TIMESTAMP use the database server's zone, which
            // is UTC on this host — so stored times read hours off once PHP
            // formats them as local. Pin the session to Vancouver instead.
            //
            // Sent as a numeric offset rather than 'America/Vancouver' because
            // the named zone needs MySQL's timezone tables, which shared hosts
            // often don't load. The offset is computed now, so it follows DST.
            try {
                $tz     = new DateTimeZone('America/Vancouver');
                $offset = $tz->getOffset(new DateTime('now', $tz));
                $sign   = $offset < 0 ? '-' : '+';
                $abs    = abs($offset);
                $pdo->exec(sprintf("SET time_zone = '%s%02d:%02d'",
                    $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60)));
            } catch (Exception $tzErr) {
                error_log('Could not set DB time zone: ' . $tzErr->getMessage());
            }
        } catch (PDOException $e) {
            error_log("DB connection failed: " . $e->getMessage());
            die("Unable to connect to the database. Please contact the union president.");
        }
    }
    return $pdo;
}
