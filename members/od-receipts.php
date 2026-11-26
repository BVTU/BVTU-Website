<?php
/**
 * od-receipts.php — a copy of every expense receipt in the Treasurer's OneDrive.
 *
 * Filed by the day the money was spent, not the day it was claimed:
 *   !BVTU/BVTUTreasurer/26_27/Receipts and Invoices by month/November/
 *
 * The year folder runs July to June, because that is how the Treasurer's
 * folders already run (26_27 holds July and August). It is not the portal's
 * September school year, and must not be swapped for expSchoolYear().
 *
 * Covers member reimbursements (exp_batches, and the retired one-receipt
 * exp_expenses) and President's expenses (LP vouchers). Pro-D claims are a separate fund with their own treasurer.
 *
 * The base folder is never created here. If it is missing, OneDrive is
 * connected to some other account, and building the Treasurer's tree inside
 * it would scatter the union's receipts somewhere nobody looks.
 *
 * Idempotent: each receipt file is copied once, recorded in
 * onedrive_receipt_sync, and skipped on every later run. Submitting a voucher
 * copies its receipts after the page has been sent; the Expense receipts page
 * copies everything else, including all the vouchers from before this existed.
 */
require_once __DIR__ . '/onedrive-db.php';
require_once __DIR__ . '/lp-db.php';   // pulls in exp-db.php


/** Never copied: not yet claimed, or turned down. */
const ODR_SKIP_STATUSES = ['draft', 'rejected', 'withdrawn'];

function odrEnsureTable(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    getDB()->exec("CREATE TABLE IF NOT EXISTS onedrive_receipt_sync (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        source        VARCHAR(8)   NOT NULL,
        parent_id     INT          NOT NULL,
        line_id       INT          NOT NULL,
        file_hash     CHAR(40)     NOT NULL,
        status        VARCHAR(10)  NOT NULL DEFAULT 'working',
        folder_path   VARCHAR(500) NOT NULL DEFAULT '',
        file_name     VARCHAR(255) NOT NULL DEFAULT '',
        drive_item_id VARCHAR(100) NOT NULL DEFAULT '',
        web_url       VARCHAR(1000) NOT NULL DEFAULT '',
        error         VARCHAR(500) NOT NULL DEFAULT '',
        attempts      INT          NOT NULL DEFAULT 0,
        updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_receipt (source, line_id, file_hash),
        INDEX idx_parent (source, parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** 'Y-m-d' if this looks like a real date, else ''. */
function odrDate($v): string {
    $v = substr(trim((string)$v), 0, 10);
    return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && $v !== '0000-00-00' && strtotime($v)) ? $v : '';
}

/** Safe as a OneDrive file name. */
function odrCleanName(string $s): string {
    $s = preg_replace('~[\\\\/:*?"<>|\x00-\x1F]+~u', ' ', $s);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return rtrim($s, '. ');
}

/**
 * Every receipt file on claimed vouchers, one row each.
 * $source '' for both; $parentId 0 for all.
 */
function odrCandidates(string $source = '', int $parentId = 0): array {
    // Both portals make their tables the first time they are opened; one that
    // never has been would otherwise fail this query outright.
    if ($source === '' || $source === 'exp' || $source === 'old') expBatchEnsureTables();
    if ($source === '' || $source === 'lp')  lpEnsureTables();
    $db  = getDB();
    $out = [];
    $skip = implode(',', array_map(function ($s) { return "'" . $s . "'"; }, ODR_SKIP_STATUSES));

    if ($source === '' || $source === 'exp') {
        $sql = "SELECT * FROM exp_batches WHERE status NOT IN ($skip)" . ($parentId ? ' AND id=?' : '');
        $st  = $db->prepare($sql);
        $st->execute($parentId ? [$parentId] : []);
        foreach ($st->fetchAll() as $b) {
            foreach (expBatchGetItems((int)$b['id']) as $i) {
                $path = trim((string)($i['receipt_path'] ?? ''));
                if ($path === '') continue;
                $out[] = [
                    'source' => 'exp', 'parent_id' => (int)$b['id'], 'line_id' => (int)$i['id'],
                    'path'   => $path, 'dir' => EXP_RECEIPTS_DIR,
                    'date'   => odrDate($i['expense_date'] ?? '') ?: odrDate($i['extracted_date'] ?? '')
                             ?: odrDate($b['submitted_at'] ?? '') ?: odrDate($b['created_at'] ?? ''),
                    'ref'    => (string)($b['ref_code'] ?? ''),
                    'who'    => (string)($b['user_name'] ?? ''),
                    'desc'   => (string)($i['description'] ?? '') ?: (string)($i['extracted_vendor'] ?? ''),
                    'part'   => 0, 'parts' => 1,
                ];
            }
        }
    }

    // The retired one-receipt claims (exp_expenses). Nothing submits these any
    // more, so only the backfill reaches them; a source of their own because
    // their ids overlap exp_batch_items'.
    if ($source === '' || $source === 'old') {
        $sql = "SELECT * FROM exp_expenses WHERE status NOT IN ($skip)
                  AND COALESCE(receipt_path,'') <> ''" . ($parentId ? ' AND id=?' : '');
        $st  = $db->prepare($sql);
        $st->execute($parentId ? [$parentId] : []);
        foreach ($st->fetchAll() as $x) {
            $out[] = [
                'source' => 'old', 'parent_id' => (int)$x['id'], 'line_id' => (int)$x['id'],
                'path'   => trim((string)$x['receipt_path']), 'dir' => EXP_RECEIPTS_DIR,
                'date'   => odrDate($x['expense_date'] ?? '') ?: odrDate($x['extracted_date'] ?? '')
                         ?: odrDate($x['created_at'] ?? ''),
                'ref'    => (string)($x['ref_code'] ?? ''),
                'who'    => (string)($x['user_name'] ?? ''),
                'desc'   => (string)($x['description'] ?? '') ?: (string)($x['extracted_vendor'] ?? ''),
                'part'   => 0, 'parts' => 1,
            ];
        }
    }

    if ($source === '' || $source === 'lp') {
        $sql = "SELECT * FROM lp_vouchers WHERE status NOT IN ($skip)" . ($parentId ? ' AND id=?' : '');
        $st  = $db->prepare($sql);
        $st->execute($parentId ? [$parentId] : []);
        foreach ($st->fetchAll() as $v) {
            $ref = trim((string)($v['voucher_number'] ?? '')) !== ''
                 ? 'Voucher ' . trim((string)$v['voucher_number']) : 'LP-' . (int)$v['id'];
            $who = lpPayTo($v)['name'] ?: (string)($v['submitted_by'] ?? '');
            foreach (lpGetExpenses((int)$v['id']) as $e) {
                $files = lpRowReceipts($e);
                foreach ($files as $n => $r) {
                    $out[] = [
                        'source' => 'lp', 'parent_id' => (int)$v['id'], 'line_id' => (int)$e['id'],
                        'path'   => $r['path'], 'dir' => LP_RECEIPTS_DIR,
                        'date'   => odrDate($e['expense_date'] ?? '') ?: odrDate($v['submitted_at'] ?? '')
                                 ?: odrDate($v['created_at'] ?? ''),
                        'ref'    => $ref,
                        'who'    => $who,
                        'desc'   => (string)($e['description'] ?? ''),
                        'part'   => $n, 'parts' => count($files),
                    ];
                }
            }
        }
    }

    // Attach what is already known about each one.
    odrEnsureTable();
    $known = [];
    foreach ($db->query("SELECT * FROM onedrive_receipt_sync")->fetchAll() as $r) {
        $known[$r['source'] . ':' . $r['line_id'] . ':' . $r['file_hash']] = $r;
    }
    foreach ($out as &$c) {
        $c['hash'] = sha1($c['path']);
        $c['sync'] = $known[$c['source'] . ':' . $c['line_id'] . ':' . $c['hash']] ?? null;
    }
    unset($c);
    return $out;
}

/** The database's clock, which is the one updated_at is written in. */
function odrNow(): string {
    return (string)getDB()->query("SELECT NOW()")->fetchColumn();
}

/**
 * Take a receipt for this run. False if it is done, or another run has it.
 * A 'working' row older than ten minutes is a run that died, and is retaken.
 */
function odrClaim(array $c): bool {
    $db = getDB();
    $ins = $db->prepare("INSERT IGNORE INTO onedrive_receipt_sync
        (source, parent_id, line_id, file_hash, status, attempts) VALUES (?,?,?,?, 'working', 1)");
    $ins->execute([$c['source'], $c['parent_id'], $c['line_id'], $c['hash']]);
    if ($ins->rowCount() > 0) return true;
    $up = $db->prepare("UPDATE onedrive_receipt_sync SET status='working', attempts=attempts+1
        WHERE source=? AND line_id=? AND file_hash=?
          AND (status='error' OR (status='working' AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)))");
    $up->execute([$c['source'], $c['line_id'], $c['hash']]);
    return $up->rowCount() > 0;
}

function odrFinish(array $c, array $fields): void {
    $sets = [];
    $vals = [];
    foreach ($fields as $k => $v) { $sets[] = "`$k`=?"; $vals[] = $v; }
    $vals[] = $c['source']; $vals[] = $c['line_id']; $vals[] = $c['hash'];
    getDB()->prepare("UPDATE onedrive_receipt_sync SET " . implode(',', $sets)
        . " WHERE source=? AND line_id=? AND file_hash=?")->execute($vals);
}

/** Copy one receipt. '' on success, why not on failure, null if another run has it. */
function odrCopyOne(array $c): ?string {
    if (!odrClaim($c)) return null;

    $fail = function (string $why) use ($c): string {
        odrFinish($c, ['status' => 'error', 'error' => substr($why, 0, 500)]);
        return $why;
    };

    $base = realpath($c['dir']);
    $file = realpath($c['dir'] . $c['path']);
    if (!$base || !$file || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
        return $fail('The receipt file is missing from the website.');
    }
    if ($c['date'] === '') return $fail('The expense has no date.');

    [$yearFolder, $month] = odrFolderFor($c['date']);
    [$folderId, $why] = odrFolderId($yearFolder, $month);
    if ($folderId === '') return $fail($why);

    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $desc = function_exists('mb_substr') ? mb_substr(trim($c['desc']), 0, 60) : substr(trim($c['desc']), 0, 60);
    $name = odrCleanName($c['date'] . ' ' . $c['ref'] . ' ' . $c['who'] . ($desc !== '' ? ' - ' . $desc : '')
          . ($c['parts'] > 1 ? ' (' . ($c['part'] + 1) . ' of ' . $c['parts'] . ')' : ''));
    // Cut by bytes on a character boundary: half a character is a name Graph refuses.
    $cut  = function_exists('mb_strcut') ? mb_strcut($name, 0, 180, 'UTF-8') : substr($name, 0, 180);
    $name = ($cut !== '' ? rtrim($cut, '. ') : 'receipt') . ($ext !== '' ? '.' . $ext : '');

    $mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
              'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
              'heic' => 'image/heic', 'heif' => 'image/heif'];
    $mime = $mimes[$ext] ?? 'application/octet-stream';

    [$code, $data] = odUploadFile($folderId, $name, $file, $mime, 'rename');
    if ($code >= 400 || empty($data['id'])) {
        $msg = $data['error']['message'] ?? ('OneDrive refused the file (HTTP ' . $code . ').');
        return $fail($msg);
    }

    odrFinish($c, [
        'status'        => 'done',
        'error'         => '',
        'folder_path'   => $yearFolder . '/' . OD_RECEIPTS_MONTHLY . '/' . $month,
        'file_name'     => (string)($data['name'] ?? $name),
        'drive_item_id' => (string)$data['id'],
        'web_url'       => (string)($data['webUrl'] ?? ''),
    ]);
    return '';
}

/**
 * Copy what is waiting, up to $max files.
 *
 * $since, when given, is a database timestamp: a receipt that already failed
 * after it is left alone, so one bad file cannot keep a run going round.
 * Returns ['copied'=>n, 'failed'=>n, 'left'=>n].
 */
function odrRun(string $source = '', int $parentId = 0, int $max = 10, string $since = ''): array {
    $copied = 0; $failed = 0; $left = 0;
    foreach (odrCandidates($source, $parentId) as $c) {
        $s = $c['sync'];
        if ($s && $s['status'] === 'done') continue;
        if ($s && $s['status'] === 'error' && $since !== '' && $s['updated_at'] >= $since) continue;
        if ($copied + $failed >= $max) { $left++; continue; }
        $r = odrCopyOne($c);
        if ($r === null)    $left++;
        elseif ($r === '')  $copied++;
        else                $failed++;
    }
    return ['copied' => $copied, 'failed' => $failed, 'left' => $left];
}

/**
 * Copy one voucher's receipts once the page has gone to the browser.
 *
 * Called on submit. It never makes submitting slower or able to fail: the
 * person sees their confirmation first, and anything that goes wrong is left
 * waiting on the Expense receipts page rather than shown to them.
 */
function odrAfterResponse(string $source, int $parentId): void {
    register_shutdown_function(function () use ($source, $parentId) {
        try {
            if (!odIsConfigured() || !odGetAccount()) return;
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            ignore_user_abort(true);
            if (function_exists('litespeed_finish_request'))   litespeed_finish_request();
            elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            @set_time_limit(180);
            if (!odAccessToken()) return;
            odrRun($source, $parentId, 50);
        } catch (\Throwable $e) {
            error_log('odrAfterResponse ' . $source . ' #' . $parentId . ': ' . $e->getMessage());
        }
    });
}
