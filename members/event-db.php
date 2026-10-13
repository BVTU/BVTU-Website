<?php
/**
 * event-db.php — seminars and workshops: the event, its custom questions, and
 * the people who sign up.
 *
 * Registration is open to anyone with the link — TTOCs, retirees and guests
 * from neighbouring locals should not need a portal account to attend a
 * seminar. Only the organiser side is gated.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp.php';
date_default_timezone_set('America/Vancouver');

/** The question types the builder offers. Keys are stored in the database. */
const EV_QUESTION_TYPES = [
    'short'  => 'Short answer',
    'long'   => 'Paragraph',
    'choice' => 'Choose one',
    'checks' => 'Choose any',
    'number' => 'Number',
];

/** Types whose answers come from a fixed list the organiser typed. */
const EV_TYPES_WITH_OPTIONS = ['choice', 'checks'];

function evEnsureTables(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS ev_events (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        slug             VARCHAR(80)   NOT NULL UNIQUE,
        title            VARCHAR(200)  NOT NULL,
        summary          VARCHAR(400),
        description      TEXT,
        starts_at        DATETIME,
        ends_at          DATETIME,
        location         VARCHAR(255),
        status           VARCHAR(20)   NOT NULL DEFAULT 'draft',
        confirm_message  TEXT,
        notify_email     VARCHAR(255),
        created_by       VARCHAR(255),
        created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_starts (starts_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS ev_questions (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        event_id    INT          NOT NULL,
        sort_order  INT          NOT NULL DEFAULT 0,
        label       VARCHAR(400) NOT NULL,
        help_text   VARCHAR(400),
        qtype       VARCHAR(20)  NOT NULL DEFAULT 'short',
        options     TEXT,
        required    TINYINT(1)   NOT NULL DEFAULT 0,
        INDEX idx_event (event_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS ev_registrations (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        event_id      INT          NOT NULL,
        name          VARCHAR(200) NOT NULL,
        email         VARCHAR(255) NOT NULL,
        member_email  VARCHAR(255),
        school        VARCHAR(200),
        status        VARCHAR(20)  NOT NULL DEFAULT 'registered',
        answers_json  TEXT,
        cancel_token  VARCHAR(64),
        ip_hash       CHAR(64),
        link_sent_at  DATETIME,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        cancelled_at  DATETIME,
        INDEX idx_event  (event_id, status),
        INDEX idx_token  (cancel_token),
        INDEX idx_email  (email),
        INDEX idx_rate   (ip_hash, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Slugs ─────────────────────────────────────────────────────────────────────

/**
 * A readable, stable URL fragment. The slug ends up on posters and in emails,
 * so it is generated once and then left alone even if the title is reworded.
 */
function evSlugify(string $text): string {
    $s = strtolower(trim($text));
    $s = str_replace(['&', '@'], ['and', 'at'], $s);
    // Transliterate what we can rather than dropping it: "Café" should not
    // become "caf".
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($t !== false) $s = strtolower($t);
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    if ($s === '') $s = 'seminar';
    return substr($s, 0, 70);
}

/** evSlugify() plus a numeric suffix if that slug is already taken. */
function evUniqueSlug(string $text, int $ignoreId = 0): string {
    $base = evSlugify($text);
    $db   = getDB();
    $slug = $base;
    for ($n = 2; $n < 200; $n++) {
        $s = $db->prepare("SELECT id FROM ev_events WHERE slug = ? AND id <> ? LIMIT 1");
        $s->execute([$slug, $ignoreId]);
        if (!$s->fetch()) return $slug;
        $slug = substr($base, 0, 66) . '-' . $n;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

// ── Events ────────────────────────────────────────────────────────────────────

function evCreate(string $title, string $createdBy): int {
    $db = getDB();
    $db->prepare(
        "INSERT INTO ev_events (slug, title, status, created_by, notify_email)
         VALUES (?,?,'draft',?,?)"
    )->execute([evUniqueSlug($title), evFit($title, 200), evFit($createdBy, 255),
                evFit($createdBy, 255)]);
    return (int)$db->lastInsertId();
}

function evGet(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM ev_events WHERE id = ? LIMIT 1");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function evBySlug(string $slug): ?array {
    $s = getDB()->prepare("SELECT * FROM ev_events WHERE slug = ? LIMIT 1");
    $s->execute([$slug]);
    return $s->fetch() ?: null;
}

/**
 * Every event with its signup count, newest date first. Drafts included —
 * this is the organiser's list, not the public one.
 */
function evAll(): array {
    $sql = "SELECT e.*,
                   (SELECT COUNT(*) FROM ev_registrations r
                    WHERE r.event_id = e.id AND r.status = 'registered') AS signups
            FROM ev_events e
            ORDER BY (e.starts_at IS NULL) ASC, e.starts_at DESC, e.id DESC";
    return getDB()->query($sql)->fetchAll();
}

function evUpdate(int $id, array $d): void {
    getDB()->prepare(
        "UPDATE ev_events SET
            title = ?, summary = ?, description = ?, starts_at = ?, ends_at = ?,
            location = ?, confirm_message = ?, notify_email = ?,
            updated_at = NOW()
         WHERE id = ?"
    )->execute([
        evFit($d['title'], 200), evFit($d['summary'] ?? '', 400),
        ($d['description'] ?? '') ?: null,
        $d['starts_at'] ?: null, $d['ends_at'] ?: null,
        evFit($d['location'] ?? '', 255), ($d['confirm_message'] ?? '') ?: null,
        evFit($d['notify_email'] ?? '', 255), $id,
    ]);
}

function evSetStatus(int $id, string $status): void {
    if (!in_array($status, ['draft', 'open', 'closed'], true)) return;
    getDB()->prepare("UPDATE ev_events SET status = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$status, $id]);
}

function evDelete(int $id): void {
    $db = getDB();
    // Children first — nothing in the schema enforces this for us.
    $db->prepare("DELETE FROM ev_registrations WHERE event_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM ev_questions     WHERE event_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM ev_events        WHERE id = ?")->execute([$id]);
}

/** Can someone sign up for this right now, and if not, why not? */
function evRegistrationState(array $event): array {
    if (($event['status'] ?? '') === 'draft') {
        return ['open' => false, 'why' => 'This seminar has not been published yet.'];
    }
    if (($event['status'] ?? '') === 'closed') {
        return ['open' => false, 'why' => 'Registration for this seminar has closed.'];
    }
    // Checked here rather than only in the page, so a stale form posted after
    // the seminar cannot still add someone to the list.
    if (evIsPast($event)) {
        return ['open' => false, 'why' => 'This seminar has already taken place.'];
    }
    return ['open' => true, 'why' => ''];
}

// ── Questions ─────────────────────────────────────────────────────────────────

function evQuestions(int $eventId): array {
    $s = getDB()->prepare(
        "SELECT * FROM ev_questions WHERE event_id = ? ORDER BY sort_order ASC, id ASC"
    );
    $s->execute([$eventId]);
    return $s->fetchAll();
}

/** The options an option-type question offers, one per line in the database. */
function evQuestionOptions(array $q): array {
    $raw = (string)($q['options'] ?? '');
    if ($raw === '') return [];
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line !== '') $out[] = $line;
    }
    return $out;
}

function evQuestionAdd(int $eventId, array $d): int {
    $db    = getDB();
    $next  = (int)$db->query(
        "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ev_questions WHERE event_id = " . (int)$eventId
    )->fetchColumn();
    $type  = isset(EV_QUESTION_TYPES[$d['qtype'] ?? '']) ? $d['qtype'] : 'short';
    $db->prepare(
        "INSERT INTO ev_questions (event_id, sort_order, label, help_text, qtype, options, required)
         VALUES (?,?,?,?,?,?,?)"
    )->execute([
        $eventId, $next, $d['label'], ($d['help_text'] ?? '') ?: null, $type,
        in_array($type, EV_TYPES_WITH_OPTIONS, true) ? (($d['options'] ?? '') ?: null) : null,
        !empty($d['required']) ? 1 : 0,
    ]);
    return (int)$db->lastInsertId();
}

function evQuestionUpdate(int $id, int $eventId, array $d): void {
    $type = isset(EV_QUESTION_TYPES[$d['qtype'] ?? '']) ? $d['qtype'] : 'short';
    getDB()->prepare(
        "UPDATE ev_questions
            SET label = ?, help_text = ?, qtype = ?, options = ?, required = ?
          WHERE id = ? AND event_id = ?"
    )->execute([
        $d['label'], ($d['help_text'] ?? '') ?: null, $type,
        in_array($type, EV_TYPES_WITH_OPTIONS, true) ? (($d['options'] ?? '') ?: null) : null,
        !empty($d['required']) ? 1 : 0, $id, $eventId,
    ]);
}

function evQuestionDelete(int $id, int $eventId): void {
    getDB()->prepare("DELETE FROM ev_questions WHERE id = ? AND event_id = ?")
           ->execute([$id, $eventId]);
}

/** Swap a question with its neighbour. $dir is -1 for up, +1 for down. */
function evQuestionMove(int $id, int $eventId, int $dir): void {
    $qs = evQuestions($eventId);
    $at = null;
    foreach ($qs as $i => $q) {
        if ((int)$q['id'] === $id) { $at = $i; break; }
    }
    if ($at === null) return;
    $to = $at + ($dir < 0 ? -1 : 1);
    if ($to < 0 || $to >= count($qs)) return;

    // Rewrite the whole run rather than swapping two values: sort_order can
    // contain ties or gaps from earlier deletes, and swapping those does
    // nothing visible.
    $order = array_column($qs, 'id');
    [$order[$at], $order[$to]] = [$order[$to], $order[$at]];
    $upd = getDB()->prepare("UPDATE ev_questions SET sort_order = ? WHERE id = ? AND event_id = ?");
    foreach ($order as $i => $qid) {
        $upd->execute([$i + 1, (int)$qid, $eventId]);
    }
}

/**
 * A stable, non-identifying handle for the visitor's network, for rate
 * limiting only. Hashed because the registration table should not become a
 * log of who was browsing from where; comparing hashes works just as well.
 */
function evIpHash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') return '';
    return hash('sha256', 'bvtu-ev|' . $ip);
}

/**
 * True when this network has already filed a suspicious number of signups in
 * the last hour. Registration is open to the whole internet, so something has
 * to stand between a bot and an unbounded table.
 *
 * The ceiling is deliberately far above real use. A school shares one public
 * address, so a staff meeting where everyone signs up at once is a single
 * "network" — a limit tight enough to catch a determined bot would turn away
 * most of a staffroom, and silently.
 */
function evRateLimited(int $max = 40): bool {
    $h = evIpHash();
    if ($h === '') return false;
    try {
        $s = getDB()->prepare(
            "SELECT COUNT(*) FROM ev_registrations
             WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $s->execute([$h]);
        return (int)$s->fetchColumn() >= $max;
    } catch (\Throwable $e) {
        // Never let the limiter itself turn away a real registration.
        error_log('evRateLimited: ' . $e->getMessage());
        return false;
    }
}

/** Trim to what the column will actually hold, counting characters not bytes. */
function evFit(?string $v, int $len): ?string {
    $v = trim((string)$v);
    return $v === '' ? null : mb_substr($v, 0, $len);
}

/**
 * Make a value safe to open in a spreadsheet.
 *
 * Excel and Sheets treat a cell starting with =, +, - or @ as a formula, so a
 * registrant typing one into a free-text answer gets it executed on the
 * organiser's machine. Prefixing an apostrophe keeps the text visible and
 * inert.
 */
function evCsvCell($v): string {
    $v = (string)$v;
    if ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) return "'" . $v;
    return $v;
}

// ── Registrations ─────────────────────────────────────────────────────────────

/**
 * Check a submitted set of answers against the event's questions.
 *
 * Returns [$answers, $errors]. $answers is stored as-is and keeps the question
 * label as it read at submission time, so renaming or deleting a question
 * later cannot rewrite what somebody already answered.
 */
function evCollectAnswers(array $questions, array $post): array {
    $answers = [];
    $errors   = [];

    foreach ($questions as $q) {
        $key   = 'q' . (int)$q['id'];
        $type  = $q['qtype'];
        $label = $q['label'];
        $given = $post[$key] ?? ($type === 'checks' ? [] : '');

        if ($type === 'checks') {
            $allowed = evQuestionOptions($q);
            $picked  = [];
            foreach ((array)$given as $v) {
                $v = trim((string)$v);
                if ($v !== '' && in_array($v, $allowed, true)) $picked[] = $v;
            }
            if (!$picked && !empty($q['required'])) {
                $errors[] = $label . ': please choose at least one.';
            }
            $value = implode(', ', $picked);
            $multi = $picked;   // kept separately; the joined string is for display only
        } elseif ($type === 'choice') {
            $allowed = evQuestionOptions($q);
            $value   = trim((string)$given);
            // A value that is not on the list did not come from our form.
            if ($value !== '' && !in_array($value, $allowed, true)) $value = '';
            if ($value === '' && !empty($q['required'])) {
                $errors[] = $label . ': please choose an option.';
            }
        } elseif ($type === 'number') {
            $value = mb_substr(trim((string)$given), 0, 40);
            if ($value !== '' && !is_numeric($value)) {
                $errors[] = $label . ': please enter a number.';
                $value = '';
            } elseif ($value === '' && !empty($q['required'])) {
                $errors[] = $label . ': this one is required.';
            }
        } else {
            $value = trim((string)$given);
            if ($value === '' && !empty($q['required'])) {
                $errors[] = $label . ': this one is required.';
            }
            // Long enough for a thoughtful paragraph, short enough that a bot
            // cannot fill the table with one request.
            $value = mb_substr($value, 0, $type === 'long' ? 4000 : 500);
        }

        $answer = [
            'qid'   => (int)$q['id'],
            'label' => $label,
            'type'  => $type,
            'value' => $value,
        ];
        if ($type === 'checks') $answer['values'] = $multi;
        $answers[] = $answer;
    }

    return [$answers, $errors];
}

/** Has this email already got a live spot at this event? */
function evExistingRegistration(int $eventId, string $email): ?array {
    $s = getDB()->prepare(
        "SELECT * FROM ev_registrations
         WHERE event_id = ? AND LOWER(TRIM(email)) = ? AND status = 'registered'
         ORDER BY id DESC LIMIT 1"
    );
    $s->execute([$eventId, strtolower(trim($email))]);
    return $s->fetch() ?: null;
}

function evRegister(int $eventId, array $d, array $answers): int {
    $db    = getDB();
    $token = bin2hex(random_bytes(24));
    $db->prepare(
        "INSERT INTO ev_registrations
         (event_id, name, email, member_email, school, status, answers_json, cancel_token, ip_hash)
         VALUES (?,?,?,?,?,'registered',?,?,?)"
    )->execute([
        $eventId, evFit($d['name'], 200), evFit($d['email'], 255),
        evFit($d['member_email'] ?? '', 255), evFit($d['school'] ?? '', 200),
        json_encode($answers), $token, evIpHash(),
    ]);
    return (int)$db->lastInsertId();
}

/** Replace the answers on an existing spot, for someone signing up twice. */
function evUpdateRegistration(int $id, array $d, array $answers): void {
    // The email may be corrected as part of an edit, but only by someone who
    // already holds the row — the caller decides that, not this function.
    if (array_key_exists('email', $d) && trim((string)$d['email']) !== '') {
        getDB()->prepare(
            "UPDATE ev_registrations
                SET name = ?, school = ?, email = ?, answers_json = ?,
                    status = 'registered', cancelled_at = NULL
              WHERE id = ?"
        )->execute([evFit($d['name'], 200), evFit($d['school'] ?? '', 200),
                    evFit($d['email'], 255), json_encode($answers), $id]);
        return;
    }
    getDB()->prepare(
        "UPDATE ev_registrations
            SET name = ?, school = ?, answers_json = ?, status = 'registered', cancelled_at = NULL
          WHERE id = ?"
    )->execute([evFit($d['name'], 200), evFit($d['school'] ?? '', 200), json_encode($answers), $id]);
}

function evRegistrations(int $eventId, bool $includeCancelled = false): array {
    $sql = "SELECT * FROM ev_registrations WHERE event_id = ?"
         . ($includeCancelled ? '' : " AND status = 'registered'")
         . " ORDER BY created_at ASC, id ASC";
    $s = getDB()->prepare($sql);
    $s->execute([$eventId]);
    return $s->fetchAll();
}

function evRegistrationByToken(string $token): ?array {
    if (strlen($token) < 20) return null;
    $s = getDB()->prepare("SELECT * FROM ev_registrations WHERE cancel_token = ? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function evCancel(int $id): void {
    getDB()->prepare(
        "UPDATE ev_registrations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?"
    )->execute([$id]);
}

/** The stored answers of one registration, as evCollectAnswers() wrote them. */
function evAnswersOf(array $reg): array {
    $a = json_decode((string)($reg['answers_json'] ?? ''), true);
    return is_array($a) ? $a : [];
}

/** One request value as a string. Arrays arrive from crafted ?x[]= input. */
function evStr($v): string {
    return is_scalar($v) ? trim((string)$v) : '';
}

/** Answers whose question has since been deleted, newest label first seen. */
function evOrphanAnswers(array $questions, array $rows): array {
    $live = [];
    foreach ($questions as $q) $live[(int)$q['id']] = true;
    $orphans = [];
    foreach ($rows as $r) {
        foreach (evAnswersOf($r) as $a) {
            $qid = (int)($a['qid'] ?? 0);
            if ($qid && !isset($live[$qid]) && !isset($orphans[$qid])) {
                $orphans[$qid] = (string)($a['label'] ?? 'Removed question');
            }
        }
    }
    return $orphans;
}

// ── Presentation ──────────────────────────────────────────────────────────────

/** "Thursday, 12 November 2026, 4:00–6:30 pm", as much of it as we know. */
function evWhen(array $event): string {
    $start = $event['starts_at'] ?? null;
    if (!$start) return 'Date to be confirmed';
    $s   = strtotime($start);
    $out = date('l, F j, Y', $s) . ' at ' . evClock($s);
    $end = $event['ends_at'] ?? null;
    if ($end) {
        $e = strtotime($end);
        $out .= (date('Y-m-d', $e) === date('Y-m-d', $s))
            ? ' – ' . evClock($e)
            : ' – ' . date('l, F j, Y', $e) . ' at ' . evClock($e);
    }
    return $out;
}

function evClock(int $ts): string {
    return (date('i', $ts) === '00' ? date('g', $ts) : date('g:i', $ts)) . date(' a', $ts);
}

function evIsPast(array $event): bool {
    $when = $event['ends_at'] ?: $event['starts_at'];
    return $when ? strtotime($when) < time() : false;
}

/** The public link to an event, absolute so it can go in an email or a QR. */
function evPublicUrl(array $event, bool $absolute = true): string {
    $path = 'event.php?s=' . urlencode($event['slug']);
    if (!$absolute) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
    return $scheme . '://' . $host . '/' . $path;
}

function evCancelUrl(array $reg): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
    return $scheme . '://' . $host . '/event-cancel.php?t=' . urlencode($reg['cancel_token']);
}

// ── Email ─────────────────────────────────────────────────────────────────────

function evSendConfirmation(array $event, array $reg, array $answers): void {
    $lines = [
        'Hi ' . $reg['name'] . ',',
        '',
        "You're registered for " . $event['title'] . '.',
        '',
        'When:  ' . evWhen($event),
    ];
    if ($event['location']) $lines[] = 'Where: ' . $event['location'];

    $custom = trim((string)($event['confirm_message'] ?? ''));
    if ($custom !== '') {
        $lines[] = '';
        $lines[] = $custom;
    }

    if ($answers) {
        $lines[] = '';
        $lines[] = 'What you told us:';
        foreach ($answers as $a) {
            if (trim((string)$a['value']) === '') continue;
            $lines[] = '  ' . $a['label'] . ': ' . $a['value'];
        }
    }

    $lines[] = '';
    $lines[] = 'Need to change your answers or cancel? Use this link:';
    $lines[] = evCancelUrl($reg);
    $lines[] = '';
    $lines[] = 'Bulkley Valley Teachers\' Union';

    siteMail($reg['email'], 'Registered: ' . $event['title'], implode("\n", $lines));
}

/**
 * Send the change link to an address that is already registered.
 *
 * Someone typing an email into the public form is not proof that they own that
 * mailbox, so a second signup for an address we already hold does not get to
 * rewrite that registration. The link goes to the mailbox instead, and only
 * whoever can read it can make a change.
 */
/**
 * At most one "already registered" email per registration per hour.
 *
 * That branch sends mail to an address supplied by whoever is at the keyboard
 * without writing a row, so the per-network limiter never sees it. The bound
 * that matters is on the receiving mailbox, not on the sender.
 */
function evMaySendChangeLink(array $reg): bool {
    $last = $reg['link_sent_at'] ?? null;
    return !$last || strtotime($last) < time() - 3600;
}

function evSendChangeLink(array $event, array $reg): void {
    if (!evMaySendChangeLink($reg)) return;
    getDB()->prepare("UPDATE ev_registrations SET link_sent_at = NOW() WHERE id = ?")
           ->execute([(int)$reg['id']]);

    $lines = [
        'Hi ' . $reg['name'] . ',',
        '',
        'Someone just filled in the registration form for ' . $event['title']
            . ' using this address, which is already registered.',
        '',
        "You are still registered — nothing has changed. If that was you and you "
            . "wanted to change your answers, use this link:",
        evCancelUrl($reg),
        '',
        'If it was not you, you can safely ignore this message.',
        '',
        'When:  ' . evWhen($event),
    ];
    if ($event['location']) $lines[] = 'Where: ' . $event['location'];
    $lines[] = '';
    $lines[] = 'Bulkley Valley Teachers\' Union';

    siteMail($reg['email'], 'Already registered: ' . $event['title'], implode("\n", $lines));
}

function evSendCancelNotice(array $event, array $reg): void {
    $to = trim((string)($event['notify_email'] ?? '')) ?: trim((string)($event['created_by'] ?? ''));
    if ($to === '') return;
    $left = count(evRegistrations((int)$event['id']));
    siteMail($to, 'Cancellation: ' . $event['title'], implode("\n", [
        $reg['name'] . ' (' . $reg['email'] . ') has cancelled their spot at ' . $event['title'] . '.',
        '',
        'That leaves ' . $left . ' registered.',
    ]));
}

function evSendOrganiserNotice(array $event, array $reg, array $answers, bool $isUpdate = false): void {
    $to = trim((string)($event['notify_email'] ?? '')) ?: trim((string)($event['created_by'] ?? ''));
    if ($to === '') return;

    $count = count(evRegistrations((int)$event['id']));
    $lines = [
        ($isUpdate ? 'Updated registration' : 'New registration') . ' for ' . $event['title'],
        '',
        'Name:   ' . $reg['name'],
        'Email:  ' . $reg['email'],
    ];
    if (!empty($reg['school'])) $lines[] = 'School: ' . $reg['school'];
    foreach ($answers as $a) {
        if (trim((string)$a['value']) === '') continue;
        $lines[] = $a['label'] . ': ' . $a['value'];
    }
    $lines[] = '';
    $lines[] = 'That makes ' . $count . ' registered so far.';

    siteMail($to, ($isUpdate ? 'Updated' : 'New') . ' registration: ' . $event['title'],
             implode("\n", $lines));
}
