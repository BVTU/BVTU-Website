<?php
/**
 * exp-payments-export.php — CSV export of paid expenses (Treasurer / Admin only)
 *
 * GET params: date_from, date_to, category, email_search
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

if (!expIsTreasurer($member['email']) && !expIsAdmin($member['email'])) {
    http_response_code(403);
    exit('Access denied.');
}

$dateFrom    = trim($_GET['date_from']    ?? '');
$dateTo      = trim($_GET['date_to']      ?? '');
$categoryF   = trim($_GET['category']    ?? '');
$emailSearch = trim($_GET['email_search'] ?? '');

$filters = ['status' => 'paid'];
if ($dateFrom)    $filters['date_from']    = $dateFrom;
if ($dateTo)      $filters['date_to']      = $dateTo;
if ($categoryF)   $filters['category']     = $categoryF;
if ($emailSearch) $filters['email_search'] = $emailSearch;

$payments = expGetAll($filters, 2000, 0);

$filename = 'bvtu-expenses-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

// Headers
fputcsv($out, [
    'Reference',
    'Date Paid',
    'Member Name',
    'Member Email',
    'Expense Date',
    'Category',
    'Amount',
    'Description',
    'Signer 1 (Treasurer)',
    'Signer 1 Date',
    'Signer 2',
    'Signer 2 Date',
    'Paid By',
    'Payment Note',
]);

foreach ($payments as $p) {
    fputcsv($out, [
        $p['ref_code'],
        $p['paid_at']    ? date('Y-m-d', strtotime($p['paid_at']))      : '',
        $p['user_name'],
        $p['user_email'],
        $p['expense_date'],
        ucfirst($p['category']),
        number_format((float)$p['amount'], 2),
        $p['description'],
        $p['signer1_name'] ?: '',
        $p['signer1_at']  ? date('Y-m-d', strtotime($p['signer1_at'])) : '',
        $p['signer2_name'] ?: '',
        $p['signer2_at']  ? date('Y-m-d', strtotime($p['signer2_at'])) : '',
        $p['paid_by_name'] ?: '',
        $p['payment_note'] ?: '',
    ]);
}

fclose($out);
