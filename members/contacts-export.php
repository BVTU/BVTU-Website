<?php
/**
 * contacts-export.php — CSV and Excel export of the contact list.
 *
 * Authorisation is checked here, not inherited from whichever page linked in:
 * a download URL is guessable and must stand on its own.
 *
 * Generated per request and streamed; nothing is written into a web-readable
 * directory, so there are no stale exports lying around to be found later.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';
require_once __DIR__ . '/people-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { http_response_code(403); exit('Access denied.'); }

sendPrivateHeaders();
contactsEnsureTables();

$format = ($_GET['format'] ?? 'csv') === 'xlsx' ? 'xlsx' : 'csv';

// Export honours the filters on screen, so "export what I'm looking at" works.
$filters = [
    'q'                => trim($_GET['q'] ?? ''),
    'school_id'        => $_GET['school_id'] ?? '',
    'role'             => $_GET['role'] ?? '',
    'status'           => $_GET['status'] ?? '',
    'mc'               => $_GET['mc'] ?? '',
    'include_archived' => !empty($_GET['include_archived']),
    'sort'             => $_GET['sort'] ?? 'name',
    'dir'              => $_GET['dir'] ?? 'asc',
];
// The People page can also filter by account and invite state; honour those
// too, or "export what I'm looking at" would quietly export more than that.
$xAccount = in_array($_GET['account'] ?? '', ['yes','no'], true) ? $_GET['account'] : '';
$xState   = isset(PEOPLE_INVITE_LABELS[$_GET['state'] ?? '']) ? $_GET['state'] : '';
$xList    = peopleFilterEmails($xAccount, $xState);
if ($xList !== null) $filters['email_whitelist'] = $xList;

list($rows) = contactSearch($filters, 1, 100000);

$schools = [];
foreach (contactSchools() as $s) $schools[(int)$s['id']] = $s['name'];

$headers = ['First Name','Last Name','Preferred Name','Email','Secondary Email','Phone',
            'School','Position','Role','Internal Status','Mailchimp Status','Last Updated'];

$data = [];
foreach ($rows as $c) {
    $school = $c['school_id'] ? ($schools[(int)$c['school_id']] ?? '') : $c['school_other'];
    $data[] = [
        $c['first_name'], $c['last_name'], $c['preferred_name'], $c['email'],
        $c['secondary_email'], $c['phone'], $school, $c['position'], $c['role'],
        CONTACT_STATUSES[$c['status']] ?? $c['status'],
        MC_STATUSES[$c['mailchimp_status']] ?? $c['mailchimp_status'],
        $c['updated_at'] ? date('Y-m-d H:i', strtotime($c['updated_at'])) : '',
    ];
}

$stamp = date('Y-m-d');
contactAudit(null, $format === 'xlsx' ? 'export_xlsx' : 'export_csv',
             $member['email'], '', count($data) . ' contacts');

if ($format === 'xlsx') {
    require_once __DIR__ . '/xlsx-writer.php';
    $tmp = tempnam(sys_get_temp_dir(), 'cxl');
    $ok  = xlsxWrite($tmp, $headers, $data, 'Contacts', [16,16,16,30,30,16,26,20,18,14,16,16]);
    if (!$ok) { @unlink($tmp); http_response_code(500); exit('Could not build the workbook.'); }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="contacts-' . $stamp . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);           // temp file never outlives the request
    exit;
}

/**
 * A leading = + - @ makes Excel treat the cell as a formula, so a contact whose
 * name starts with one could execute on open. Prefix a single quote.
 */
function csvSafe($v): string {
    $v = (string)$v;
    return (isset($v[0]) && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="contacts-' . $stamp . '.csv"');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));   // BOM so Excel reads UTF-8
fputcsv($out, $headers);
foreach ($data as $row) fputcsv($out, array_map('csvSafe', $row));
fclose($out);
