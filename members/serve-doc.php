<?php
/**
 * Secure document server — checks login before serving any PDF.
 * PDFs live in protected-docs/ which is blocked from direct access.
 */
require_once 'auth.php';
requireLogin(); // redirects to login.php if not signed in

$file     = basename($_GET['file'] ?? '');            // strip any path traversal
$doc_path = __DIR__ . '/protected-docs/' . $file;

// Validate: file param provided, exists, is a PDF
if (!$file || !file_exists($doc_path) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(404);
    echo "Document not found. <a href='dashboard.php'>Back to dashboard</a>";
    exit;
}

// Serve the PDF inline (opens in browser) — change to attachment to force download
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($doc_path));
header('Cache-Control: private, no-store');
readfile($doc_path);
exit;
