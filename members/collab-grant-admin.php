<?php
/**
 * collab-grant-admin.php — Collaboration Grant admin review panel
 * Access: executive members only
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/collab-grant-db.php';
requireLogin();

$member = getMember();

if (!cgIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : cgCurrentYear();
// A school year or nothing. "?year=abc" casts to 0, which is not a year any
// application can belong to and would put a "0–1" option in the picker.
if ($year < 2000 || $year > 2100) $year = cgCurrentYear();
$apps = cgGetApplications($year);

// ── CSV export — must happen before any HTML output ───────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="collab-grant-' . $year . '-' . ($year+1) . '.csv"');
    require_once __DIR__ . '/xlsx-writer.php';   // csvSafeText(), shared guard
    $out = fopen('php://output', 'w');
    // The reconciling columns only. The long prose fields, the contact details
    // and the "who reviewed it" trail are all on the application itself; in a
    // spreadsheet they pushed the figures you are actually checking off the
    // right-hand edge.
    fputcsv($out, [
        'ID', 'Status', 'Submitted',
        'Applicant Name', 'School', 'Position',
        'Collaborator Name',
        'Days Requested', 'Days Granted', 'Proposed Dates', 'Reviewed At',
        'Atrieve Logged', 'Atrieve Confirmed', 'Invoice Number', 'Release Cost',
    ]);
    foreach ($apps as $a) {
        $pdArr = json_decode($a['proposed_dates'] ?? '[]', true);
        $pdStr = is_array($pdArr)
            ? implode(', ', array_map(fn($d) => date('D M j Y', strtotime($d)), $pdArr))
            : '';
        fputcsv($out, array_map('csvSafeText', [
            $a['id'],
            ucfirst($a['status']),
            date('Y-m-d', strtotime($a['submitted_at'])),
            $a['applicant_name'],
            $a['school'],
            $a['position'],
            // Gated on has_collaborator, not just on the name being there. The
            // form hides its collaborator inputs rather than disabling them, so
            // a name typed and then "Not yet" chosen still arrives — and with
            // the Has Collaborator column gone there is nothing left in the
            // sheet to contradict it. A release day reconciled against a
            // teacher the application does not name is the worst kind of wrong.
            !empty($a['has_collaborator']) ? $a['collaborator_name'] : '',
            $a['days_requested'],
            ($a['days_approved'] ?? null) !== null ? (int)$a['days_approved'] : '',
            $pdStr,
            $a['reviewed_at'] ? date('Y-m-d', strtotime($a['reviewed_at'])) : '',
            !empty($a['atrieve_confirmed']) ? 'Yes' : 'No',
            !empty($a['atrieve_confirmed_at']) ? date('Y-m-d', strtotime($a['atrieve_confirmed_at'])) : '',
            $a['invoice_number'] ?? '',
            ($a['release_cost'] ?? null) !== null
                ? number_format((float)$a['release_cost'], 2, '.', '') : '',
        ]));
    }
    fclose($out);
    exit;
}

// ── Days-per-person export ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'days') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="collab-days-' . $year . '-' . ($year+1) . '.csv"');
    require_once __DIR__ . '/xlsx-writer.php';   // csvSafeText(), shared guard
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Teacher', 'Email', 'Days Approved', 'Days Pending', 'Days Used',
                   'Days Left', 'Over Limit', 'Took Part As', 'Identified By',
                   'Yearly Cap']);
    foreach (cgDaysLedger($year) as $lp) {
        $roles = array_unique(array_column($lp['apps'], 'role'));
        sort($roles);
        fputcsv($out, array_map('csvSafeText', [
            $lp['name'],
            $lp['email'],
            $lp['approved'],
            $lp['pending'],
            $lp['used'],
            empty($lp['capped']) ? 'n/a' : ($lp['over'] ? 0 : $lp['left']),
            empty($lp['capped']) ? 'n/a' : ($lp['over'] ? 'Yes, by ' . ($lp['used'] - CG_DAY_CAP) : 'No'),
            implode(' + ', $roles),
            // The same note the row carries. Someone adding the applications up
            // against this export needs to know why the total is lower.
            trim(($lp['exact'] ? 'Email address'
                : (strpos((string)$lp['key'], 'n:') === 0
                    ? 'No match for this name — days sit under the name as typed'
                    : 'Some days matched by name'))
              . (empty($lp['shared_days']) ? ''
                 : '; ' . count($lp['shared_days']) . ' day(s) claimed by more than one '
                   . 'application, counted once')),
            empty($lp['capped']) ? 'Not capped — ' . $lp['exempt_reason'] : 'Applies',
        ]));
    }
    fclose($out);
    exit;
}

$notice = '';

// ── Send someone the link to change their own application ─────────────────
// Separate from the status buttons because it changes nothing about the
// application — it just puts the link in the applicant's hands.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_link') {
    csrfCheck();
    $target = cgGetApplication((int)($_POST['app_id'] ?? 0));
    if (!$target) {
        $notice = 'Could not find that application.';
    } elseif (cgSendEditLink($target, $member['email'])) {
        $notice = 'Edit link sent to ' . $target['applicant_email'] . '.';
    } else {
        $notice = 'The email would not send. Check the mail settings and try again.';
    }
    $apps = cgGetApplications($year);
}

// ── Who the three-day cap applies to ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['exempt_on', 'exempt_off'], true)) {
    csrfCheck();
    $person = ['key'   => (string)($_POST['person_key'] ?? ''),
               'email' => (string)($_POST['person_email'] ?? ''),
               'name'  => (string)($_POST['person_name'] ?? '')];
    if (($_POST['action'] ?? '') === 'exempt_off') {
        $notice = cgExemptRemove($person)
            ? 'The three-day limit applies to ' . $person['name'] . ' again, from '
              . $year . '–' . (($year + 1) % 100) . ' on.'
            : 'Nothing changed — no exemption was on record for them.';
    } else {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') $reason = 'Release costs the local nothing';
        // Recorded against the year being viewed and carried forward, so marking
        // someone today does not rewrite a year already closed off.
        $notice = cgExemptAdd($person, $reason, $member['email'], $year)
            ? $person['name'] . ' is not capped from ' . $year . '–' . (($year + 1) % 100)
              . ' on — ' . $reason
            : 'Could not save that. The problem has been logged.';
    }
}

// ── Clearing a dispute once it has been dealt with ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'collab-dispute-ack') {
    csrfCheck();
    $aid = (int)($_POST['app_id'] ?? 0);
    if ($aid && cgCollabDisputeAck($aid)) {
        $notice = 'Flag cleared. What they said is still on the application.';
    } else {
        $notice = 'Could not clear that flag.';
    }
    $apps = cgGetApplications($year);
}

// ── Deciding not to write to a collaborator at all ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['collab-waive', 'collab-unwaive', 'collab-waive-untried'], true)) {
    csrfCheck();
    $act = (string)$_POST['action'];
    $by  = (string)(getMember()['email'] ?? '');

    if ($act === 'collab-waive-untried') {
        // The ids the button was offering when the page was drawn, so the press
        // clears what the user was looking at and nothing approved since.
        $offered = array_values(array_filter(array_map(
            'intval', (array)($_POST['ids'] ?? [])
        )));
        // Always a whitelist, even when empty: "?: null" turned a POST with no
        // ids into "clear the lot", which is precisely what naming them was
        // meant to stop.
        $n = cgCollabWaiveUntried($by, $offered);
        if ($n > 0) {
            $notice = $n . ' cleared. Nothing was emailed — they are still on their '
                    . 'applications, marked as not written to.';
        } elseif (!cgHasCollabColumn('collab_waived_at')) {
            // Rows are still on screen, so "nothing to clear" would read as a
            // lie. Name the actual reason.
            $notice = 'Nothing could be cleared — the collab_waived_at column is missing '
                    . 'on this server. Tell the site admin.';
        } else {
            $notice = 'Nothing to clear. Any rows left are sends that were tried and failed.';
        }
    } else {
        $aid = (int)($_POST['app_id'] ?? 0);
        $app = $aid ? cgGetApplication($aid) : null;
        if (!$app) {
            $notice = 'Could not find that application.';
        } elseif ($act === 'collab-waive') {
            $notice = cgCollabWaive($aid, $by)
                ? 'Cleared. ' . $app['collaborator_name'] . ' was not emailed, and the '
                    . 'application says so.'
                : 'Could not clear that — the collab_waived_at column is missing on this '
                    . 'server. Tell the site admin.';
        } else {
            $notice = cgCollabUnwaive($aid)
                ? $app['collaborator_name'] . ' is back on the list to be emailed.'
                : 'Could not undo that.';
        }
    }
    $apps = cgGetApplications($year);
}

// ── Reaching a collaborator we could not write to ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'collab-email') {
    csrfCheck();
    $cid  = (int)($_POST['app_id'] ?? 0);
    $addr = reqPostStr('collaborator_email');
    $app  = $cid ? cgGetApplication($cid) : null;

    if (!$app) {
        $notice = 'Could not find that application.';
    } elseif ($addr !== '' && !filter_var($addr, FILTER_VALIDATE_EMAIL)) {
        $notice = 'That does not look like an email address.';
    } elseif ($addr !== '' && !cgSetCollaboratorEmail($cid, $addr)) {
        // The address was fine; we simply have nowhere to keep it. Saying
        // "that is not an email" would have them retyping a good one for ever.
        $notice = 'That address could not be saved — the collaborator_email column '
                . 'is missing on this server. Tell the site admin.';
    } else {
        try {
            $res = cgSendCollaboratorEmail(cgGetApplication($cid) ?: $app, true);
            $notice = $res['sent']
                ? $app['collaborator_name'] . ' has been emailed.'
                : 'Still could not send — ' . lcfirst($res['why']);
        } catch (\Throwable $e) {
            error_log('collab resend: ' . $e->getMessage());
            $notice = 'Something went wrong sending that. It has been logged.';
        }
    }
    $apps = cgGetApplications($year);
}

// ── Status updates ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'])
    && in_array($_POST['action'] ?? '', ['approved', 'declined', 'waitlisted', 'pending'], true)) {
    csrfCheck();
    $id     = (int)$_POST['app_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['admin_notes'] ?? '');

    // Blank or absent falls back to what was asked for, so the button still works
    // without touching the box.
    $grantDays = null;
    if ($action === 'approved') {
        $raw       = trim((string)($_POST['days_approved'] ?? ''));
        $asked     = cgGetApplication($id);
        $grantDays = $raw === '' ? (int)($asked['days_requested'] ?? 1) : (int)$raw;
        $grantDays = max(1, min(CG_DAY_CAP, $grantDays));
    }
    cgUpdateStatus($id, $action, $member['email'], $notes, $grantDays);

    if ($action === 'approved') {
        $target = cgGetApplication($id);
        $notice = 'Application approved — notification email sent to the applicant.';
        if ($target) {
            // The collaborator first, so the applicant's email can say truthfully
            // whether we reached them — but never at the cost of the approval
            // itself. The status is already committed; a throw here would leave
            // the grant approved, the applicant untold and a 500 on screen.
            try {
                $collab = cgSendCollaboratorEmail($target);
            } catch (\Throwable $e) {
                error_log('collab notify: ' . $e->getMessage());
                $collab = ['sent' => false, 'why' => 'Something went wrong sending it.'];
            }
            if (!empty($target['collaborator_name']) && !empty($target['has_collaborator'])) {
                if ($collab['sent']) {
                    $notice .= ' ' . $target['collaborator_name'] . ' has been emailed too.';
                } elseif (!empty($target['collab_waived_at'])) {
                    // Deliberately not emailed. Reporting that as a failure, and
                    // pointing at a panel row that is hidden precisely because
                    // of the decision, reads as something gone wrong.
                    $notice .= ' ' . $target['collaborator_name'] . ' was not emailed — it was '
                             . 'decided on '
                             . date('M j', strtotime((string)$target['collab_waived_at']))
                             . ' not to write to them.';
                } elseif (!empty($target['collab_notified_at'])) {
                    // Re-approving to change the day count is a normal thing to
                    // do. Reporting it as a failure, with a pointer to a panel
                    // row that does not exist, reads as something gone wrong.
                    $notice .= ' ' . $target['collaborator_name'] . ' was already emailed on '
                             . date('M j', strtotime((string)$target['collab_notified_at']))
                             . ' — use "Send again" on the application if the details have changed.';
                } else {
                    $notice .= ' We could not email ' . $target['collaborator_name']
                             . ' — ' . lcfirst($collab['why'])
                             . ' Add an address below and send it.';
                }
            }
            try {
                cgSendApprovalEmail(cgGetApplication($id) ?: $target);
            } catch (\Throwable $e) {
                error_log('approval email: ' . $e->getMessage());
                $notice .= ' The applicant\'s own email did not send — check the mail settings.';
            }
        }
    } else {
        $notice = 'Status updated to ' . ucfirst($action) . '.';
    }
    // Refresh apps after update
    $apps = cgGetApplications($year);
}

// ── Follow-through: Atrieve and the district invoice ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fulfilment') {
    csrfCheck();
    $id = (int)($_POST['app_id'] ?? 0);
    // The box is always submitted, so its contents are always applied: a figure
    // records one, an emptied box clears it back to "not recorded".
    $ok = cgSetFulfilment($id, !empty($_POST['atrieve']),
                          (string)($_POST['invoice_number'] ?? ''), $member['email'],
                          (string)($_POST['release_cost'] ?? ''));
    $notice = $ok ? 'Saved.' : 'Could not save. The problem has been logged.';
    $apps   = cgGetApplications($year);
}

/*
 * Reviewing applications and chasing paperwork are different jobs on different
 * days, and they used to be stacked in one list. 'review' is pending only —
 * what you open on meeting night; 'followup' is approved grants still missing
 * an Atrieve confirmation, an invoice number or a cost; 'all' is everything.
 */
$view = $_GET['view'] ?? 'review';
if (!in_array($view, ['review', 'followup', 'all', 'print', 'read'], true)) $view = 'review';
if ($view === 'read') $view = 'print';    // the old toggle's name, still linked from bookmarks

/** Approved, but the district paperwork is not finished. */
$cgNeedsFollowUp = function (array $a): bool {
    return $a['status'] === 'approved'
        && (empty($a['atrieve_confirmed'])
            || trim((string)($a['invoice_number'] ?? '')) === ''
            || ($a['release_cost'] ?? null) === null);
};
$pendingApps  = array_values(array_filter($apps, fn($a) => $a['status'] === 'pending'));
$followUpApps = array_values(array_filter($apps, $cgNeedsFollowUp));
$shownApps    = $view === 'review' ? $pendingApps
              : ($view === 'followup' ? $followUpApps : $apps);
// 'print' is the plain read-through for a meeting; everything else is workable.
$cgReadOnly   = ($view === 'print');

$statusColour = [
    'pending'    => ['bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e', 'label' => 'Pending'],
    'approved'   => ['bg' => '#f0f9f3', 'border' => '#b3d9bf', 'text' => '#1a5c2e', 'label' => 'Approved'],
    'declined'   => ['bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b', 'label' => 'Declined'],
    'waitlisted' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e40af', 'label' => 'Waitlisted'],
];

$totalDaysApproved = array_sum(array_map('cgEffectiveDays',
    array_filter($apps, fn($a) => $a['status'] === 'approved')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="../">
  <title>Collaboration Grant — Admin Review</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: var(--off-white); }

    .admin-wrap {
      max-width: 1000px;
      margin: 0 auto;
      padding: calc(var(--hdr-h) + 2rem) 1.5rem 3rem;
    }
    .admin-title {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: .25rem;
    }
    .admin-sub {
      font-size: .9rem;
      color: var(--gray-500);
      margin-bottom: 1.5rem;
    }

    /* ── Top toolbar ── */
    .admin-toolbar {
      display: flex;
      align-items: center;
      gap: .75rem;
      flex-wrap: wrap;
      margin-bottom: 1.75rem;
    }
    .view-toggle {
      display: flex;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      overflow: hidden;
      background: var(--white);
    }
    .view-toggle a {
      padding: .45rem 1rem;
      font-size: .83rem;
      font-weight: 600;
      color: var(--gray-500);
      text-decoration: none;
      border-right: 1px solid var(--border);
      transition: background .15s, color .15s;
    }
    .view-toggle a:last-child { border-right: none; }
    .view-toggle a.active { background: var(--primary); color: #fff; }
    .view-toggle a:not(.active):hover { background: var(--off-white); color: var(--primary); }

    .export-btn {
      display: flex;
      align-items: center;
      gap: .4rem;
      padding: .45rem 1rem;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-size: .83rem;
      font-weight: 600;
      color: var(--gray-600);
      text-decoration: none;
      transition: border-color .15s, color .15s;
    }
    .export-btn:hover { border-color: var(--primary); color: var(--primary); }
    .export-btn svg { width: 14px; height: 14px; }

    /* ── Summary cards ── */
    .summary-row {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 2rem;
    }
    .summary-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: .9rem 1.4rem;
      min-width: 140px;
    }
    .summary-card .val {
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .summary-card .lbl {
      font-size: .76rem;
      color: var(--gray-500);
      margin-top: .2rem;
    }

    .notice {
      background: #f0f9f3;
      border: 1.5px solid #b3d9bf;
      border-radius: 8px;
      padding: .85rem 1.1rem;
      font-size: .9rem;
      color: #1a5c2e;
      margin-bottom: 1.5rem;
    }

    /* ══ REVIEW VIEW — accordion cards ══════════════════════ */
    .app-list { display: flex; flex-direction: column; gap: 1rem; }

    .app-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .app-card-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.25rem;
      cursor: pointer;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .app-card-head:hover { background: var(--off-white); }
    .app-name { font-weight: 700; color: var(--text); font-size: .97rem; }
    .app-meta { font-size: .82rem; color: var(--gray-500); margin-top: .15rem; }
    .app-status-badge {
      font-size: .75rem;
      font-weight: 700;
      padding: .25rem .7rem;
      border-radius: 20px;
      border: 1px solid;
      white-space: nowrap;
    }
    .app-card-chevron {
      color: var(--gray-400);
      transition: transform .2s;
      flex-shrink: 0;
    }
    .app-card.open .app-card-chevron { transform: rotate(180deg); }

    .app-card-body {
      display: none;
      border-top: 1px solid var(--border);
      padding: 1.25rem;
    }
    .app-card.open .app-card-body { display: block; }

    .app-detail-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
      gap: .75rem 1.25rem;
      margin-bottom: 1.25rem;
    }
    .app-detail-item .dl { color: var(--gray-400); font-size: .74rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .15rem; }
    .app-detail-item .dd { color: var(--text); font-size: .88rem; font-weight: 500; }

    /* ── The decision, as one block ───────────────────────────────
       Everything needed to say yes or no, together and visibly apart
       from the reading above it. */
    .cg-decision { background: var(--off-white); border: 1px solid var(--border);
      border-radius: 10px; padding: 1rem 1.1rem; margin-top: 1.25rem; }
    .cg-decision .app-action-form { margin: 0; }
    .cg-decision .btn-approve { font-size: .92rem; padding: .55rem 1.1rem; }

    /* One line per person, scannable rather than read. */
    .cg-standing { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap;
      font-size: .85rem; margin-bottom: .3rem; }
    .cg-standing-who { font-weight: 700; color: var(--gray-800); }
    .cg-standing-role { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em;
      color: var(--gray-400); font-weight: 700; }
    .cg-standing-days { color: var(--gray-600); font-weight: 600; }
    .cg-standing-days.over { color: #991b1b; }
    .cg-standing-foot { font-size: .78rem; color: var(--gray-500); margin-top: .2rem; }
    .cg-tag { font-size: .7rem; font-weight: 700; padding: .1rem .45rem; border-radius: 999px;
      background: var(--gray-100); color: var(--gray-600); white-space: nowrap; }
    .cg-tag.over  { background: #fef2f2; color: #991b1b; }
    .cg-tag.good  { background: #f0fdf4; color: #166534; }
    .cg-tag.approx{ background: #fffbeb; color: #92400e; cursor: help; }

    /* ── The things you need twice a term ─────────────────────── */
    .cg-field-note { display: block; font-weight: 400; font-size: .76rem;
      color: var(--gray-500); margin-top: .25rem; }
    .cg-followup-hint { font-size: .82rem; color: var(--gray-500); margin: .9rem 0 0; }
    .app-more { margin-top: .9rem; }
    .app-more > summary { cursor: pointer; font-size: .82rem; font-weight: 600;
      color: var(--gray-500); list-style: none; padding: .3rem 0; }
    .app-more > summary::-webkit-details-marker { display: none; }
    .app-more > summary::before { content: '+ '; font-weight: 800; }
    .app-more[open] > summary::before { content: '\2212 '; }
    .app-more > summary:hover { color: var(--primary); }
    .app-more-body { font-size: .85rem; padding: .25rem 0 .25rem .9rem;
      border-left: 2px solid var(--border); }
    .app-more-body p { margin: 0 0 .5rem; }
    .app-more-note { color: var(--gray-400); }
    .app-more-btn { border: 1px solid var(--border); background: #fff; color: var(--gray-700);
      font-size: .82rem; padding: .3rem .7rem; }

    .app-text-label {
      font-size: .74rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--gray-400);
      margin-bottom: .35rem;
    }
    .app-text-block {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: .85rem 1rem;
      font-size: .9rem;
      color: var(--gray-700);
      line-height: 1.65;
      margin-bottom: .85rem;
      white-space: pre-wrap;
    }

    .app-action-form {
      border-top: 1px solid var(--border);
      padding-top: 1.1rem;
      margin-top: 1rem;
      display: flex;
      flex-direction: column;
      gap: .75rem;
    }
    .app-action-form textarea {
      border: 1.5px solid var(--gray-200);
      border-radius: 8px;
      padding: .65rem .9rem;
      font-size: .88rem;
      font-family: inherit;
      width: 100%;
      resize: vertical;
    }
    .app-action-form textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .cg-year-pick { display: flex; align-items: center; gap: .4rem; margin: 0; }
    .cg-year-pick label { font-size: .74rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em; color: var(--gray-500); }
    .cg-year-pick select { font: inherit; font-size: .84rem; padding: .3rem .5rem;
        border: 1px solid var(--border); border-radius: 7px; background: #fff; cursor: pointer; }
    .cg-edit-link { font-size: .74rem; font-weight: 700; color: var(--primary);
        text-decoration: none; margin-left: .45rem; border-bottom: 1px solid transparent; }
    .cg-edit-link:hover { border-bottom-color: var(--primary); }
    .app-action-btns { display: flex; gap: .6rem; flex-wrap: wrap; }
    .app-action-btns .btn {
      padding: .5rem 1.1rem;
      border-radius: 7px;
      font-size: .85rem;
      font-weight: 600;
      cursor: pointer;
      transition: background .15s;
    }
    .btn-approve  { background: var(--primary); color: #fff; border: none; }
    .btn-approve:hover  { background: #155a2a; }
    /* A link wearing the row's button, so it lines up with the real buttons
       beside it rather than looking like text that wandered in. */
    .btn-edit { background: #fff; color: var(--primary); border: 1px solid var(--primary);
      text-decoration: none; display: inline-flex; align-items: center; }
    .btn-edit:hover { background: var(--accent); }
    .btn-decline  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .btn-decline:hover  { background: #fee2e2; }

    /* ══ READ VIEW — full expanded list ═════════════════════ */
    .read-list { display: flex; flex-direction: column; gap: 2.5rem; }

    .read-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .read-card-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 1.1rem 1.4rem .9rem;
      background: var(--off-white);
      border-bottom: 1px solid var(--border);
      gap: 1rem;
      flex-wrap: wrap;
    }
    .read-card-head h3 {
      font-size: 1rem;
      font-weight: 800;
      color: var(--primary);
      margin: 0 0 .2rem;
    }
    .read-card-head .rc-meta {
      font-size: .82rem;
      color: var(--gray-500);
    }
    .read-card-body { padding: 1.25rem 1.4rem; }

    .read-facts {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem 1.5rem;
      margin-bottom: 1.25rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--gray-100);
    }
    .read-fact { font-size: .85rem; color: var(--gray-600); }
    .read-fact strong { color: var(--text); }

    .read-section-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--gray-400);
      margin: 1rem 0 .35rem;
    }
    .read-text {
      font-size: .92rem;
      color: var(--gray-700);
      line-height: 1.7;
      white-space: pre-wrap;
    }

    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--gray-400);
      font-size: .95rem;
    }

    @media print {
      .site-header, .admin-toolbar, .summary-row, .app-action-form { display: none !important; }
      .read-card { break-inside: avoid; }
    }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/../inc/nav.php'; bvtuNav(['base' => '../']); ?>

  <div class="admin-wrap">

    <div class="admin-title">Collaboration Grant</div>
    <div class="admin-sub"><?= $year ?>–<?= $year + 1 ?> school year · <?= count($apps) ?> application<?= count($apps) !== 1 ? 's' : '' ?></div>

    <!-- Toolbar -->
    <div class="admin-toolbar">
      <div class="view-toggle">
        <a href="?view=review&year=<?= $year ?>" class="<?= $view === 'review' ? 'active' : '' ?>">
          To review<?= count($pendingApps) ? ' (' . count($pendingApps) . ')' : '' ?></a>
        <a href="?view=followup&year=<?= $year ?>" class="<?= $view === 'followup' ? 'active' : '' ?>">
          Follow-through<?= count($followUpApps) ? ' (' . count($followUpApps) . ')' : '' ?></a>
        <a href="?view=all&year=<?= $year ?>" class="<?= $view === 'all' ? 'active' : '' ?>">All</a>
        <a href="?view=print&year=<?= $year ?>" class="<?= $cgReadOnly ? 'active' : '' ?>"
           title="A plain read-through, for printing or reading at a meeting">Print view</a>
      </div>

      <?php
        /*
         * A school year to look at.
         *
         * The page has always defaulted to the current year and had no way to
         * reach another, so every application from a previous year was
         * unreachable except by typing ?year= into the address bar — including
         * when the current year is simply empty and the page looks broken.
         *
         * The list is the years that actually have applications, plus the
         * current one, so it never offers an empty year nobody has used.
         */
        $cgYears = [];
        foreach (cgApplicationsAllYears() as $a) {
            $y = (int)($a['school_year'] ?? 0);
            if ($y > 0) $cgYears[$y] = true;
        }
        $cgYears[cgCurrentYear()] = true;
        // And whatever year is actually being shown — a stale bookmark can
        // name a real year with nothing in it. Without this the picker said
        // 2026 while the page showed something else, and choosing 2026 fired
        // no change event, so there was no way back.
        $cgYears[$year] = true;
        $cgYears = array_keys($cgYears);
        rsort($cgYears);
      ?>
      <?php if (count($cgYears) > 1): ?>
      <form method="get" class="cg-year-pick">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
        <label for="cg-year">Year</label>
        <select id="cg-year" name="year" onchange="this.form.submit()">
          <?php foreach ($cgYears as $y): ?>
          <option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>>
            <?= $y ?>&ndash;<?= ($y + 1) % 100 ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
      <a href="?export=csv&year=<?= $year ?>" class="export-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </a>
      <a href="?export=days&year=<?= $year ?>" class="export-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Days per person
      </a>
      <?php if ($cgReadOnly): ?>
      <button onclick="window.print()" class="export-btn" style="cursor:pointer;border:1.5px solid var(--border);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
      </button>
      <?php endif; ?>
    </div>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <?php
    // Collaborators nobody could write to, and anyone who has said it is not
    // them. Both are things only a person can settle, so they sit at the top
    // rather than inside an application nobody has a reason to reopen.
    $toFix    = cgCollaboratorsToFix();
    $disputed = array_values(array_filter(cgApplicationsAllYears(), function ($a) {
        return !empty($a['collab_disputed_at']);
    }));
    ?>

    <?php if ($disputed): ?>
    <div class="cg-flag cg-flag-hot">
      <h2>&#9888; Someone says they were named by mistake</h2>
      <?php foreach ($disputed as $a): ?>
      <div class="cg-flag-row">
        <div>
          <strong><?= htmlspecialchars($a['collaborator_name']) ?></strong>
          was named by <?= htmlspecialchars($a['applicant_name']) ?>
          on application #<?= (int)$a['id'] ?>, and says it is not them.
          <?php if (trim((string)$a['collab_dispute_note']) !== ''): ?>
          <div class="cg-flag-note">&ldquo;<?= htmlspecialchars($a['collab_dispute_note']) ?>&rdquo;</div>
          <?php endif; ?>
          <div class="cg-flag-when">
            Told us <?= htmlspecialchars(date('M j', strtotime($a['collab_disputed_at']))) ?>.
            Nothing has been changed — the days still stand against that name until you decide.
          </div>
        </div>
        <form method="POST" style="flex:0 0 auto;"
              onsubmit="return confirm('Clear this flag? The note is kept on the application.');">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="collab-dispute-ack">
          <input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
          <button type="submit" class="btn btn-outline" style="font-size:.82rem;padding:.38rem .8rem;">
            I have dealt with this
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($toFix): ?>
    <div class="cg-flag">
      <?php $untriedRows = cgCollabUntried($toFix); $untried = count($untriedRows); ?>
      <h2>Collaborators who have not been told</h2>
      <p class="cg-flag-lead">
        These grants are approved and the applicant has been told. The person they named
        has not &mdash; so they do not know a release day is counted against their three.
        Each row says whether a send was tried and failed, or whether nothing has gone
        out yet.
        <?php if ($untried > 0): ?>
        Grants approved before collaborator emails existed, and any we have no address
        for, show as &ldquo;nothing sent yet&rdquo; &mdash; if those are water under the
        bridge, clear them all at once. A send that was tried and failed is left for you
        to retry.
        <?php endif; ?>
      </p>
      <?php if ($untried > 0): ?>
      <form method="POST" style="margin:0 0 .9rem;"
            onsubmit="return confirm(<?= htmlspecialchars(json_encode(
                'Clear ' . $untried . ' where nothing was ever sent? No email will go out. '
                . 'Rows where a send was tried and failed are left alone.'), ENT_QUOTES) ?>);">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="collab-waive-untried">
        <?php foreach ($untriedRows as $u): ?>
        <input type="hidden" name="ids[]" value="<?= (int)$u['id'] ?>">
        <?php endforeach; ?>
        <button type="submit" class="btn btn-outline" style="font-size:.82rem;padding:.38rem .8rem;">
          Clear the <?= $untried ?> where nothing was sent
        </button>
      </form>
      <?php endif; ?>
      <?php foreach ($toFix as $a): ?>
      <form method="POST" class="cg-flag-row">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="collab-email">
        <input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
        <div style="flex:1 1 14rem;min-width:0;">
          <strong><?= htmlspecialchars($a['collaborator_name']) ?></strong>
          <?php if (trim((string)$a['collaborator_school']) !== ''): ?>
          <span class="cg-flag-when">&middot; <?= htmlspecialchars($a['collaborator_school']) ?></span>
          <?php endif; ?>
          <div class="cg-flag-when">
            Named by <?= htmlspecialchars($a['applicant_name']) ?> &middot; #<?= (int)$a['id'] ?>
            <?php // The year, so a 2019 leftover and a grant approved last week
                  // are not the same undifferentiated row. ?>
            <?php if (!empty($a['school_year'])): ?>
            &middot; <?= (int)$a['school_year'] ?>&ndash;<?= ((int)$a['school_year'] + 1) % 100 ?>
            <?php endif; ?>
            &middot; <?= htmlspecialchars($a['_why']) ?>
          </div>
        </div>
        <?php // The address the send would actually use, Contacts match included,
              // so the box agrees with the sentence above it. ?>
        <?php $known = trim((string)($a['_addr'] ?? ''));
              // _addr is empty when the stored address is unusable. Showing it
              // anyway gives the near miss to correct, instead of an empty box
              // demanding it be typed from nothing.
              if ($known === '') $known = trim((string)($a['collaborator_email'] ?? '')); ?>
        <input type="email" name="collaborator_email" class="cg-flag-input"
               value="<?= htmlspecialchars($known) ?>"
               placeholder="their email address"<?= $known === '' ? ' required' : '' ?>>
        <button type="submit" class="btn btn-primary" style="font-size:.84rem;padding:.42rem .9rem;">
          <?= $known === '' ? 'Save &amp; send' : 'Send' ?>
        </button>
      </form>
      <?php // Its own form: a GET-less dismiss cannot share the send form above,
            // or pressing it would submit the address field as a send too. ?>
      <form method="POST" class="cg-flag-dismiss"
            onsubmit="return confirm(<?= htmlspecialchars(json_encode(
                'Clear ' . $a['collaborator_name'] . ' without emailing them?'), ENT_QUOTES) ?>);">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="collab-waive">
        <input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
        <button type="submit" class="cg-flag-link">No email needed &mdash; clear it</button>
      </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <style>
      .cg-flag { background:#fffdf7; border:1px solid #ecd9a8; border-radius:10px;
                 padding:1.1rem 1.25rem; margin-bottom:1.5rem; }
      .cg-flag-hot { background:#fdecea; border-color:#f5c6c2; }
      .cg-flag h2 { font-size:1rem; margin:0 0 .35rem; color:#6b4a00; }
      .cg-flag-hot h2 { color:#9c3a1e; }
      .cg-flag-lead { font-size:.86rem; color:#7a6333; margin:0 0 .9rem; line-height:1.55; }
      .cg-flag-row { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;
                     padding:.7rem 0; border-top:1px solid #f0e3c4; font-size:.9rem; }
      .cg-flag-hot .cg-flag-row { border-top-color:#f5c6c2; }
      .cg-flag-input { flex:1 1 13rem; min-width:0; padding:.45rem .6rem; font:inherit;
                       font-size:.88rem; border:1px solid var(--border); border-radius:6px; }
      .cg-flag-when { font-size:.78rem; color:var(--gray-500); margin-top:.1rem; }
      .cg-flag-note { font-size:.86rem; color:var(--gray-700); font-style:italic; margin-top:.3rem; }
      /* Sits under its row, not beside the Send button: the quiet way out of a
         row should not compete with the thing we actually want pressed. */
      .cg-flag-dismiss { flex:1 1 100%; margin:-.35rem 0 .35rem; }
      .cg-flag-link { background:none; border:0; padding:0; font:inherit; font-size:.78rem;
                      color:var(--gray-500); text-decoration:underline; cursor:pointer; }
      .cg-flag-link:hover { color:var(--gray-700); }
    </style>

    <?php $rc = cgYearReleaseCost($year); ?>
    <?php // Three figures, not five. "Total applications" and "Approved" are both
          // in the view tabs above, and the costing caveat belongs with the cost
          // rather than in a banner of its own. ?>
    <div class="summary-row">
      <div class="summary-card">
        <div class="val"><?= count($apps) ?></div>
        <div class="lbl"><?= $year ?>–<?= ($year + 1) % 100 ?> applications</div>
      </div>
      <div class="summary-card">
        <div class="val"><?= $totalDaysApproved ?></div>
        <div class="lbl">Release days approved</div>
      </div>
      <div class="summary-card">
        <?php if (!empty($rc['error'])): ?>
          <div class="val" style="color:#991b1b;font-size:1rem;">unavailable</div>
          <div class="lbl">Release cost could not be worked out</div>
        <?php else: ?>
          <div class="val">$<?= number_format($rc['total'], 2) ?></div>
          <div class="lbl">
            Release cost
            <?php if ($rc['approved'] > $rc['costed']): ?>
              <span style="color:#92400e;">· so far; <?= (int)($rc['approved'] - $rc['costed']) ?>
                not yet costed</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php // Who has spent what, for the three-day cap and for the FAQ's promise
          // that teachers who haven't used the fund get priority.
          // Only people who have spent a day appear, so there is no "never used"
          // count to take from here — that shows per application instead, where
          // the person being considered is known.
          $ledger = cgDaysLedger($year);
          $anyApprox = (bool)array_filter($ledger, fn($lp) => !$lp['exact']);
    ?>
    <?php
      /* Two rows, one name, different addresses. Never merged on this evidence:
       * two teachers really do share a name, and adding their days together
       * silently would be worse than the split rows it fixed. Recording the
       * second address on the contact is what merges them — and that fixes it
       * everywhere, not only on this page. */
      $cgDupes = $ledger ? cgLedgerDuplicates($year) : [];
    ?>
    <?php if ($cgDupes): ?>
    <div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:10px;
                padding:.9rem 1rem;margin-bottom:1rem;font-size:.88rem;">
      <strong>Possibly the same person twice.</strong>
      <?= count($cgDupes) === 1 ? 'One name appears' : count($cgDupes) . ' names appear' ?>
      under more than one address, so their days are counted separately and the
      <?= CG_DAY_CAP ?>-day cap cannot see the total. If it is one teacher, add the second
      address to their contact record as a secondary email and they will count as one
      person here and everywhere else. If they are two different people, leave it.
      <ul style="margin:.6rem 0 0;padding-left:1.1rem;">
        <?php foreach ($cgDupes as $d): ?>
          <li style="margin-bottom:.25rem;">
            <strong><?= htmlspecialchars($d['name']) ?></strong>
            — <?= htmlspecialchars(implode(' and ', $d['addresses'])) ?>
            <span style="color:var(--gray-500);">
              (<?= (int)$d['used'] ?> <?= (int)$d['used'] === 1 ? 'day' : 'days' ?> between them)</span>
            <a href="people.php?q=<?= urlencode($d['name']) ?>" style="margin-left:.35rem;">Open contact</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($ledger): ?>
    <details style="margin-bottom:1.75rem;" <?= $cgReadOnly ? 'open' : '' ?>>
      <summary style="cursor:pointer;font-weight:700;color:var(--primary);font-size:.95rem;
                      padding:.6rem 0;">
        Days used by person — <?= count($ledger) ?> <?= count($ledger) === 1 ? 'teacher' : 'teachers' ?>
        this year
      </summary>
      <div style="background:var(--white);border:1.5px solid var(--border);border-radius:10px;
                  padding:1rem;margin-top:.5rem;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:.86rem;">
          <thead>
            <tr style="text-align:left;color:var(--gray-500);font-size:.78rem;
                       text-transform:uppercase;letter-spacing:.04em;">
              <th style="padding:.4rem .5rem;">Teacher</th>
              <th style="padding:.4rem .5rem;">Approved</th>
              <th style="padding:.4rem .5rem;">Pending</th>
              <th style="padding:.4rem .5rem;">Used</th>
              <th style="padding:.4rem .5rem;">Left</th>
              <th style="padding:.4rem .5rem;">As</th>
              <th style="padding:.4rem .5rem;">Yearly cap</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ledger as $lp): ?>
              <?php
                $roles = array_unique(array_column($lp['apps'], 'role'));
                sort($roles);
              ?>
              <tr style="border-top:1px solid var(--border);">
                <td style="padding:.45rem .5rem;">
                  <strong><?= htmlspecialchars($lp['name']) ?></strong>
                  <?php
                    // Every address folded into this row, not just the first.
                    // A teacher who applies from work and from home is one
                    // person here, and the page should show what it merged
                    // rather than quietly print one of the two.
                    $addrs = $lp['emails'] ?? ($lp['email'] ? [$lp['email']] : []);
                  ?>
                  <?php if ($addrs): ?>
                    <span style="color:var(--gray-500);">· <?= htmlspecialchars(implode(' · ', $addrs)) ?></span>
                  <?php endif; ?>
                  <?php
                    /* Two very different situations used to share one label.
                     * A collaborator's name that matched a known teacher is
                     * the system working; a name that matched nobody is days
                     * charged to a person we cannot identify, and only that
                     * one is worth acting on. Printing the same words on both
                     * put the second on every other row and buried it. */
                    $unmatched = strpos((string)$lp['key'], 'n:') === 0;
                  ?>
                  <?php if (!empty($lp['shared_days'])): ?>
                    <?php
                      /* The total is deliberately lower than the applications
                       * add up to, so it has to say why on the row. A figure
                       * that quietly disagrees with the paperwork behind it is
                       * read as a bug, and then nobody trusts the column. */
                      $sd = $lp['shared_days'];
                      $when = implode(', ', array_map(
                          function ($d) { return date('M j', strtotime($d)); },
                          array_slice($sd, 0, 4)));
                      if (count($sd) > 4) $when .= ' and ' . (count($sd) - 4) . ' more';
                    ?>
                    <div style="color:#92400e;font-size:.82rem;margin-top:.15rem;">
                      <?= count($sd) ?> <?= count($sd) === 1 ? 'day is' : 'days are' ?>
                      claimed by more than one application
                      (<?= htmlspecialchars($when) ?>) — counted once, because they can
                      only be away once.
                      <?php foreach ($lp['shared_apps'] as $sid): ?>
                        <a href="?year=<?= (int)$year ?>#app-<?= (int)$sid ?>">#<?= (int)$sid ?></a><?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($lp['undated']) && !empty($lp['shared_days'])): ?>
                    <div style="color:var(--gray-500);font-size:.82rem;">
                      Some of this total comes from applications with no usable dates, which
                      could not be checked for overlap.
                    </div>
                  <?php endif; ?>
                  <?php if ($unmatched): ?>
                    <span style="color:#92400e;"
                          title="This name matched no contact and no applicant this year, so these days are attributed to the name as typed. They may belong to someone already listed above under their email address.">·
                      no match for this name</span>
                  <?php elseif (!$lp['exact']): ?>
                    <span style="color:var(--gray-500);"
                          title="Some of these days come from an application where this person was named as a collaborator. Collaborators are typed names, so the days were attributed by matching the name to this person rather than by an email address.">·
                      some days matched by name</span>
                  <?php endif; ?>
                </td>
                <td style="padding:.45rem .5rem;"><?= (int)$lp['approved'] ?></td>
                <td style="padding:.45rem .5rem;"><?= (int)$lp['pending'] ?: '—' ?></td>
                <td style="padding:.45rem .5rem;font-weight:700;"><?= (int)$lp['used'] ?></td>
                <td style="padding:.45rem .5rem;<?= $lp['over'] ? 'color:#991b1b;font-weight:700;' : '' ?>">
                  <?php if (empty($lp['capped'])): ?>
                    <span style="color:var(--gray-500);">not capped</span>
                  <?php else: ?>
                    <?= $lp['over'] ? 'over by ' . ($lp['used'] - CG_DAY_CAP) : (int)$lp['left'] ?>
                  <?php endif; ?>
                </td>
                <td style="padding:.45rem .5rem;color:var(--gray-500);">
                  <?= htmlspecialchars(implode(' + ', $roles)) ?>
                </td>
                <td style="padding:.45rem .5rem;">
                  <form method="post" style="display:flex;gap:.3rem;align-items:center;">
                    <?= csrfField() ?>
                    <input type="hidden" name="person_key"   value="<?= htmlspecialchars($lp['key']) ?>">
                    <input type="hidden" name="person_email" value="<?= htmlspecialchars($lp['email']) ?>">
                    <input type="hidden" name="person_name"  value="<?= htmlspecialchars($lp['name']) ?>">
                    <?php if (empty($lp['capped'])): ?>
                      <span style="font-size:.8rem;color:var(--gray-500);"
                            title="<?= htmlspecialchars($lp['exempt_reason']) ?>">
                        <?= htmlspecialchars($lp['exempt_reason']) ?>
                      </span>
                      <button type="submit" name="action" value="exempt_off" class="btn"
                              style="border:1px solid var(--border);background:#fff;color:var(--gray-600);
                                     font-size:.75rem;padding:.2rem .5rem;white-space:nowrap;">
                        Apply the cap
                      </button>
                    <?php else: ?>
                      <input type="text" name="reason" maxlength="200"
                             placeholder="why, e.g. district support teacher"
                             style="width:190px;border:1px solid var(--border);border-radius:6px;
                                    padding:.2rem .4rem;font-size:.78rem;font-family:inherit;">
                      <button type="submit" name="action" value="exempt_on" class="btn"
                              style="border:1px solid var(--border);background:#fff;color:var(--gray-600);
                                     font-size:.75rem;padding:.2rem .5rem;white-space:nowrap;">
                        Not capped
                      </button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="font-size:.8rem;color:var(--gray-500);line-height:1.7;margin:.9rem 0 0;">
          Each application spends its days twice — once for the lead and once for the
          collaborator, who books their own absence for the same days.
          A release day is counted once per teacher however many applications claim it:
          where a lead and a collaborator each submitted an application for the same
          collaboration, that is one absence entered twice, not two. Applications whose
          dates and day count disagree, or that have no dates, are added on without that
          check, and rows carrying any say so.
          <?php if ($anyApprox): ?>
            Collaborators are typed names with no email address, so their days are
            attributed by matching the name against Contacts and against everyone who
            applied this year. "Some days matched by name" means that worked and names
            who it matched; <strong>"no match for this name"</strong> means it did not,
            and those days sit under the name as typed — they may belong to someone
            already listed above. Adding that teacher to Contacts, or typing their
            address on the application, settles it.
          <?php endif; ?>
          Anyone who has applied for nothing this year does not appear here at all.
          Mark someone "not capped" when releasing them costs the local nothing — a
          district support teacher, say. Their days still show; the limit stops applying.
        </p>
      </div>
    </details>
    <?php endif; ?>

    <?php if (empty($shownApps)): ?>
      <div class="empty-state">
        <?php if (empty($apps)): ?>
          No applications yet for this school year.
        <?php elseif ($view === 'review'): ?>
          Nothing waiting for a decision. <a href="?view=all&amp;year=<?= $year ?>">See all
          <?= count($apps) ?> applications</a>.
        <?php elseif ($view === 'followup'): ?>
          Every approved grant has its Atrieve confirmation, invoice number and cost recorded.
          <a href="?view=all&amp;year=<?= $year ?>">See all applications</a>.
        <?php endif; ?>
      </div>

    <?php elseif ($cgReadOnly): ?>
      <!-- ══ READ ALL VIEW ════════════════════════════════════════ -->
      <div class="read-list">
        <?php foreach ($shownApps as $app):
          $sc   = $statusColour[$app['status']] ?? $statusColour['pending'];
          $days = (int)$app['days_requested'];
        ?>
        <div class="read-card">
          <div class="read-card-head">
            <div>
              <h3><?= htmlspecialchars($app['applicant_name']) ?></h3>
              <div class="rc-meta">
                <?= htmlspecialchars($app['school']) ?> · <?= htmlspecialchars($app['position']) ?>
                · submitted <?= date('M j, Y', strtotime($app['submitted_at'])) ?>
              </div>
            </div>
            <span class="app-status-badge" style="background:<?= $sc['bg'] ?>;border-color:<?= $sc['border'] ?>;color:<?= $sc['text'] ?>;">
              <?= $sc['label'] ?>
            </span>
          </div>

          <div class="read-card-body">
            <div class="read-facts">
              <div class="read-fact"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>"><?= htmlspecialchars($app['applicant_email']) ?></a></div>
              <div class="read-fact"><strong>Time in role:</strong> <?= htmlspecialchars($app['years_in_role'] ?: '—') ?></div>
              <div class="read-fact"><strong>Days requested:</strong> <?= $days ?></div>
              <?php
                $pdDatesR = [];
                if (!empty($app['proposed_dates'])) {
                    $pdR = json_decode($app['proposed_dates'], true);
                    if (is_array($pdR)) $pdDatesR = $pdR;
                }
              ?>
              <?php if ($pdDatesR): ?>
              <div class="read-fact" style="width:100%;">
                <strong>Proposed dates:</strong>
                <?= implode(', ', array_map(fn($d) => date('D, M j Y', strtotime($d)), $pdDatesR)) ?>
              </div>
              <?php endif; ?>
              <div class="read-fact">
                <strong>Collaborator:</strong>
                <?php if ($app['has_collaborator'] && $app['collaborator_name']): ?>
                  <?= htmlspecialchars($app['collaborator_name']) ?><?= $app['collaborator_school'] ? ' (' . htmlspecialchars($app['collaborator_school']) . ')' : '' ?>
                <?php elseif ($app['needs_partner']): ?>
                  <em>Needs help finding partner</em>
                <?php else: ?>
                  None identified
                <?php endif; ?>
              </div>
            </div>

            <div class="read-section-label">Collaboration description</div>
            <div class="read-text"><?= htmlspecialchars($app['collaboration_desc']) ?></div>

            <div class="read-section-label">Goals</div>
            <div class="read-text"><?= htmlspecialchars($app['goals']) ?></div>

            <?php if ($app['admin_notes']): ?>
              <div class="read-section-label">Admin notes</div>
              <div class="read-text" style="color:var(--gray-500);font-style:italic;"><?= htmlspecialchars($app['admin_notes']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <!-- ══ REVIEW VIEW — accordion ══════════════════════════════ -->
      <div class="app-list">
        <?php foreach ($shownApps as $app):
          $sc   = $statusColour[$app['status']] ?? $statusColour['pending'];
          $days = (int)$app['days_requested'];
        ?>
        <?php // Open where the card is the job: pending in Review, paperwork in
              // Follow-through. In All it is a list to find something in. ?>
        <div class="app-card <?= ($view !== 'all' ) ? 'open' : '' ?>" id="app-<?= $app['id'] ?>">

          <div class="app-card-head" onclick="toggleCard(<?= $app['id'] ?>)">
            <div style="flex:1;min-width:0;">
              <div class="app-name"><?= htmlspecialchars($app['applicant_name']) ?></div>
              <div class="app-meta">
                <?= htmlspecialchars($app['school']) ?> · <?= htmlspecialchars($app['position']) ?>
                · <?= $days ?> day<?= $days !== 1 ? 's' : '' ?>
                · <?= date('M j', strtotime($app['submitted_at'])) ?>
              </div>
            </div>
            <span class="app-status-badge" style="background:<?= $sc['bg'] ?>;border-color:<?= $sc['border'] ?>;color:<?= $sc['text'] ?>;">
              <?= $sc['label'] ?>
            </span>
            <svg class="app-card-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </div>

          <div class="app-card-body">
            <div class="app-detail-grid">
              <div class="app-detail-item">
                <div class="dl">Email</div>
                <div class="dd"><a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>"><?= htmlspecialchars($app['applicant_email']) ?></a></div>
              </div>
              <div class="app-detail-item">
                <div class="dl">Time in role</div>
                <div class="dd"><?= htmlspecialchars($app['years_in_role'] ?: '—') ?></div>
              </div>
              <div class="app-detail-item">
                <div class="dl">Collaborator</div>
                <div class="dd">
                  <?php if ($app['has_collaborator'] && $app['collaborator_name']): ?>
                    <?= htmlspecialchars($app['collaborator_name']) ?>
                    <?php if ($app['collaborator_school']): ?>
                      <span style="color:var(--gray-400);font-weight:400;"> · <?= htmlspecialchars($app['collaborator_school']) ?></span>
                    <?php endif; ?>
                    <?php // Where the telling stands. A cleared row leaves the panel but
                          // must not leave the record: "we chose not to write to them" and
                          // "we emailed them" have to stay tellable apart for ever. ?>
                    <?php if (!empty($app['collab_notified_at'])): ?>
                      <div style="font-size:.78rem;color:var(--gray-500);font-weight:400;margin-top:.15rem;">
                        Emailed <?= htmlspecialchars(date('M j, Y', strtotime((string)$app['collab_notified_at']))) ?>
                      </div>
                    <?php elseif (!empty($app['collab_waived_at'])): ?>
                      <div style="font-size:.78rem;color:var(--gray-500);font-weight:400;margin-top:.15rem;">
                        Not emailed &mdash; cleared
                        <?= htmlspecialchars(date('M j, Y', strtotime((string)$app['collab_waived_at']))) ?><?php
                          if (trim((string)($app['collab_waived_by'] ?? '')) !== ''): ?>
                          by <?= htmlspecialchars($app['collab_waived_by']) ?><?php endif; ?>.
                        <form method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="collab-unwaive">
                          <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                          <button type="submit" class="cg-flag-link">Put it back on the list</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  <?php elseif ($app['needs_partner']): ?>
                    <em style="color:var(--gray-500);">Needs help finding partner</em>
                  <?php else: ?>
                    <em style="color:var(--gray-500);">None</em>
                  <?php endif; ?>
                </div>
              </div>
              <div class="app-detail-item">
                <div class="dl">Days requested</div>
                <div class="dd">
                  <?= $days ?>
                  <?php /*
                   * The editor has always taken an id for a signed-in admin and
                   * has always let one edit a locked or declined application —
                   * it was simply never linked from here, so the only way in
                   * was to email the applicant a link and ask them to do it.
                   *
                   * Here rather than in the actions below, because days are what
                   * people ask to change. Days requested is the number of
                   * proposed dates, so the editor changes the dates and the
                   * count follows; a box for the number on its own would let the
                   * two disagree.
                   */ ?>
                  <?php // The print view renders its own read-only card and never
                        // reaches this markup, so no guard is needed here. ?>
                  <a class="cg-edit-link"
                     href="../collab-grant-edit.php?id=<?= (int)$app['id'] ?>"
                     title="Change the dates, and anything else on the application">change</a>
                </div>
              </div>
              <?php
                $pdDates = [];
                if (!empty($app['proposed_dates'])) {
                    $pd = json_decode($app['proposed_dates'], true);
                    if (is_array($pd)) $pdDates = $pd;
                }
              ?>
              <?php if ($pdDates): ?>
              <div class="app-detail-item" style="grid-column:1/-1;">
                <div class="dl">Proposed dates</div>
                <div class="dd">
                  <?php foreach ($pdDates as $pd): ?>
                    <span style="display:inline-block;background:#e8f5ed;border:1px solid #b3d9bf;border-radius:20px;padding:.15rem .65rem;font-size:.82rem;font-weight:600;color:#1a5c2e;margin:.1rem .2rem .1rem 0;">
                      <?= date('D, M j Y', strtotime($pd)) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <?php if ($view !== 'followup'): ?>
            <div class="app-text-label">Collaboration description</div>
            <div class="app-text-block"><?= htmlspecialchars($app['collaboration_desc']) ?></div>

            <div class="app-text-label">Goals</div>
            <div class="app-text-block"><?= htmlspecialchars($app['goals']) ?></div>
            <?php endif; ?>



            <?php if ($view !== 'followup'): ?>
            <div class="cg-decision">
            <form class="app-action-form" method="post" id="decide-<?= (int)$app['id'] ?>">
              <?= csrfField() ?>
              <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
              <?php
                // Both people on an application spend days, so the lead's standing
                // and the collaborator's are both worth seeing before deciding.
                $standings = [['Applicant', cgPersonDays($year, $app['applicant_email'], $app['applicant_name']), $app['applicant_name']]];
                if (trim((string)$app['collaborator_name']) !== '' && !empty($app['has_collaborator'])) {
                    $standings[] = ['Collaborator', cgPersonDays($year, '', $app['collaborator_name']), $app['collaborator_name']];
                }
              ?>
              <div style="background:var(--off-white);border:1px solid var(--border);border-radius:8px;
                          padding:.7rem .85rem;margin:.75rem 0;font-size:.85rem;">
                <?php foreach ($standings as [$role, $p, $who]): ?>
                  <div class="cg-standing">
                    <span class="cg-standing-who"><?= htmlspecialchars($who) ?></span>
                    <span class="cg-standing-role"><?= $role ?></span>
                    <?php if (empty($p['capped'])): ?>
                      <span class="cg-standing-days"><?= (int)$p['used'] ?> days<?php
                        ?><?= $p['pending'] ? ' (' . (int)$p['pending'] . ' awaiting)' : '' ?></span>
                      <span class="cg-tag" title="<?= htmlspecialchars($p['exempt_reason']) ?>">not capped</span>
                    <?php else: ?>
                      <span class="cg-standing-days<?= $p['over'] ? ' over' : '' ?>">
                        <?= (int)$p['used'] ?> of <?= CG_DAY_CAP ?> days<?php
                          ?><?= $p['pending'] ? ' (' . (int)$p['pending'] . ' awaiting)' : '' ?>
                      </span>
                      <?php if ($p['over']): ?>
                        <span class="cg-tag over">over the limit</span>
                      <?php elseif ($p['used'] === 0): ?>
                        <span class="cg-tag good">hasn't used the fund</span>
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if (strpos((string)($p['key'] ?? ''), 'n:') === 0): ?>
                      <span class="cg-tag approx"
                            title="This name matched no contact and no applicant this year, so these days are attributed to the name as typed and could belong to someone else.">no match</span>
                    <?php elseif (!$p['exact']): ?>
                      <span class="cg-tag approx"
                            title="Part of this total comes from an application where the person was named as a collaborator. Collaborators are typed names, so those days were attributed by matching the name to this person rather than by an email address.">name match</span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
                <?php if (!in_array($app['status'], ['approved', 'pending'], true)): ?>
                  <?php // Worth saying only when it is surprising: a declined or
                        // waitlisted application is not in the figures above. ?>
                  <div class="cg-standing-foot">Not counted above —
                    this application is <?= htmlspecialchars($app['status']) ?>.</div>
                <?php endif; ?>
              </div>

              <?php if ($app['status'] === 'approved' && ($app['days_approved'] ?? null) !== null
                        && (int)$app['days_approved'] !== (int)$app['days_requested']): ?>
                <div style="font-size:.84rem;color:#92400e;background:#fffbeb;border:1px solid #fde68a;
                            border-radius:8px;padding:.5rem .7rem;margin-bottom:.6rem;">
                  <?= (int)$app['days_approved'] ?> <?= (int)$app['days_approved'] === 1 ? 'day' : 'days' ?>
                  granted, <?= (int)$app['days_requested'] ?> now requested.
                </div>
              <?php endif; ?>

              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);
                            display:block;margin-bottom:.6rem;max-width:230px;">
                Days to grant
                <input type="number" name="days_approved" min="1" max="<?= CG_DAY_CAP ?>" step="1"
                       value="<?= (int)(($app['days_approved'] ?? null) !== null
                                        ? $app['days_approved'] : $app['days_requested']) ?>"
                       style="width:100%;border:1px solid var(--border);border-radius:7px;
                              padding:.45rem .6rem;font-size:.9rem;font-family:inherit;
                              box-sizing:border-box;margin-top:.25rem;">
                <span class="cg-field-note">Charged to both teachers on this application.</span>
              </label>

              <?php /*
               * Edit takes the place Waitlist had.
               *
               * Editing on somebody's behalf is asked for far more often than
               * waitlisting, and it was folded away while Waitlist sat in the
               * decision row — so the common job was the hidden one. Waitlist
               * moves down with the other occasional actions rather than being
               * removed: it is a real status, applications are in it, and it
               * still shows on the card and in the figures.
               *
               * A link, not a submit: this form posts a decision, and Edit is
               * not one. It carries the button's look so the row reads as one
               * set of things you can do here.
               */ ?>
              <div class="app-action-btns">
                <button type="submit" name="action" value="approved"   class="btn btn-approve">✓ Approve &amp; notify applicant</button>
                <a href="../collab-grant-edit.php?id=<?= (int)$app['id'] ?>" class="btn btn-edit">Edit application</a>
                <button type="submit" name="action" value="declined"   class="btn btn-decline">Decline</button>
                <?php if ($app['status'] !== 'pending'): ?>
                  <button type="submit" name="action" value="pending" class="btn" style="border:1px solid var(--border);background:#fff;color:var(--gray-500);">Reset to pending</button>
                <?php endif; ?>
              </div>

              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);">
                Internal notes
                <textarea name="admin_notes" rows="2" placeholder="Only you see these…"><?= htmlspecialchars($app['admin_notes'] ?? '') ?></textarea>
              </label>
            </form>
            </div>
            <?php else: ?>
              <?php if (trim((string)$app['admin_notes']) !== ''): ?>
                <div class="app-text-label">Internal notes</div>
                <div class="app-text-block" style="font-style:italic;"><?= htmlspecialchars($app['admin_notes']) ?></div>
              <?php endif; ?>
              <p class="cg-followup-hint">Already approved — to change the decision or the
                days, open it under <a href="?view=all&amp;year=<?= $year ?>#app-<?= (int)$app['id'] ?>">All</a>.</p>
            <?php endif; ?>

            <?php /*
             * Kept out of the decision path on purpose — these are wanted
             * perhaps twice a term, and they used to sit between the goals and
             * the decision, which is the one place nothing should.
             *
             * But the summary said "other things you can do", which names
             * nothing, so the edit was effectively unfindable: somebody looking
             * for it read straight past this line and concluded the feature did
             * not exist. Folded away is fine. Unlabelled is not.
             */ ?>
            <details class="app-more">
              <summary><?= $view === 'followup'
                  ? 'Send them the edit link'
                  : 'Waitlist it, or send them the edit link' ?></summary>
              <div class="app-more-body">
                <?php /*
                 * Submits the decision form above rather than carrying its own.
                 *
                 * The handler writes admin_notes on every status change, so a
                 * separate form here would have had to repost the notes — and
                 * would still have thrown away anything typed into the box and
                 * not yet saved. Pointing at the real form keeps the notes, the
                 * day count and the token exactly as they are on screen.
                 */ ?>
                <?php // Only where the decision form exists. Follow-through does
                      // not render it, so the button had no owner there: the click
                      // did nothing at all while the summary promised otherwise. ?>
                <?php if ($view !== 'followup'): ?>
                <p style="margin:0 0 .6rem;">
                  <button type="submit" form="decide-<?= (int)$app['id'] ?>"
                          name="action" value="waitlisted" class="btn app-more-btn">
                    Waitlist this application
                  </button>
                  <span class="app-more-note">Holds it without deciding; the days are not counted.</span>
                </p>
                <?php endif; ?>
                <?php if (!empty($app['edited_at'])): ?>
                <p class="app-more-note" style="margin:0 0 .6rem;">
                  Last changed <?= date('M j, Y', strtotime($app['edited_at'])) ?>
                  by <?= htmlspecialchars($app['edited_by']) ?>.
                </p>
                <?php endif; ?>
                <form method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                  <input type="hidden" name="action" value="resend_link">
                  <button type="submit" class="btn app-more-btn">
                    <?= !empty($app['link_sent_at']) ? 'Send the edit link again' : 'Email them the edit link' ?>
                  </button>
                  <?php if (!empty($app['link_sent_at'])): ?>
                    <span class="app-more-note">Sent
                      <?= date('M j, Y', strtotime($app['link_sent_at'])) ?></span>
                  <?php endif; ?>
                </form>
              </div>
            </details>

            <?php // Only once approved: before that there is no absence to log
                  // and no invoice to record, and an empty box invites guessing. ?>
            <?php if ($app['status'] === 'approved'): ?>
            <form class="app-action-form fulfil" method="post" style="margin-top:.6rem;">
              <?= csrfField() ?>
              <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
              <input type="hidden" name="action" value="fulfilment">
              <div style="font-size:.78rem;font-weight:800;text-transform:uppercase;
                          letter-spacing:.05em;color:var(--gray-500);margin-bottom:.5rem;">
                Follow-through
              </div>
              <label style="display:flex;align-items:center;gap:.45rem;font-size:.88rem;
                            color:var(--gray-700);margin-bottom:.6rem;">
                <input type="checkbox" name="atrieve" value="1"
                       <?= !empty($app['atrieve_confirmed']) ? 'checked' : '' ?>>
                Absence logged in Atrieve
              </label>
              <?php if (!empty($app['atrieve_confirmed']) && !empty($app['atrieve_confirmed_at'])): ?>
                <div style="font-size:.78rem;color:var(--gray-500);margin:-.35rem 0 .6rem 1.6rem;">
                  Confirmed <?= date('M j, Y', strtotime($app['atrieve_confirmed_at'])) ?>
                  <?= $app['atrieve_confirmed_by'] ? 'by ' . htmlspecialchars($app['atrieve_confirmed_by']) : '' ?>
                </div>
              <?php endif; ?>
              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);">
                District invoice number
                <input type="text" name="invoice_number" maxlength="100"
                       placeholder="e.g. 45219"
                       value="<?= htmlspecialchars($app['invoice_number'] ?? '') ?>"
                       style="width:100%;border:1px solid var(--border);border-radius:7px;
                              padding:.45rem .6rem;font-size:.9rem;font-family:inherit;
                              box-sizing:border-box;margin-top:.25rem;">
              </label>
              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);display:block;margin-top:.6rem;">
                Cost of release time
                <input type="number" name="release_cost" step="0.01" min="0"
                       placeholder="not recorded yet"
                       value="<?= ($app['release_cost'] ?? null) !== null
                                  ? number_format((float)$app['release_cost'], 2, '.', '') : '' ?>"
                       style="width:100%;border:1px solid var(--border);border-radius:7px;
                              padding:.45rem .6rem;font-size:.9rem;font-family:inherit;
                              box-sizing:border-box;margin-top:.25rem;">
              </label>
              <div class="app-action-btns" style="margin-top:.6rem;">
                <button type="submit" class="btn" style="border:1px solid var(--border);background:#fff;color:var(--gray-700);">Save follow-through</button>
              </div>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>

  <script src="../js/site.js"></script>
  <script>
    /* Opens the card a #app-N link points at — the Follow-through hint sends you to
 * All for a specific application, and it would otherwise arrive collapsed. */
(function () {
  function openHashCard() {
    if (!/^#app-\d+$/.test(location.hash)) return;
    var el = document.querySelector(location.hash);
    if (!el) return;
    el.classList.add('open');
    el.scrollIntoView({ block: 'center' });
  }
  window.addEventListener('hashchange', openHashCard);
  openHashCard();
})();

function toggleCard(id) {
      document.getElementById('app-' + id).classList.toggle('open');
    }
  </script>
  <script src="../js/search.js?v=<?= @filemtime(__DIR__ . '/../js/search.js') ?>"></script>
</body>
</html>
