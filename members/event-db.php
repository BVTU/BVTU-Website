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
require_once __DIR__ . '/event-state.php';
date_default_timezone_set('America/Vancouver');

/**
 * Where seminar handouts live.
 *
 * Behind a deny rule like the receipt folders, and served through event-doc.php
 * instead. A handout on a draft seminar is not public yet, and a direct path
 * into a world-readable folder would be.
 */
define('EV_DOCS_DIR', __DIR__ . '/event-docs/');

/** Per file, and the ceiling on what one email will carry. */
define('EV_DOC_MAX_BYTES',  12 * 1024 * 1024);

/**
 * "8M" and friends as a number of bytes.
 */
function evIniBytes(string $v): int {
    $v = trim($v);
    if ($v === '') return 0;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': return $n * 1024 * 1024 * 1024;
        case 'm': return $n * 1024 * 1024;
        case 'k': return $n * 1024;
    }
    return $n;
}

/**
 * What this server will actually take, not what we would like it to.
 *
 * EV_DOC_MAX_BYTES is our own rule; upload_max_filesize is the host's, and the
 * host wins. Telling somebody the limit is 12 MB on a server that stops at 2
 * sends them away to compress a file for a limit that was never real.
 */
function evMaxUploadBytes(): int {
    $ini = evIniBytes((string)ini_get('upload_max_filesize'));
    return $ini > 0 ? min(EV_DOC_MAX_BYTES, $ini) : EV_DOC_MAX_BYTES;
}

/** The ceiling on one submission — everything selected, together. */
function evMaxPostBytes(): int {
    return evIniBytes((string)ini_get('post_max_size'));
}

/** How many files this server accepts in a single upload. */
function evMaxFiles(): int {
    $n = (int)ini_get('max_file_uploads');
    return $n > 0 ? $n : 20;
}
define('EV_MAIL_MAX_BYTES', 15 * 1024 * 1024);

/**
 * What a set of attachments actually weighs once sent.
 *
 * Attachments travel base64-encoded, which is four bytes for every three, so
 * raw size understates the message by a third. Comparing raw bytes to a mail
 * server's limit is how a "14 MB" send arrives as 19 MB and bounces.
 */
function evEncodedBytes(int $raw): int {
    return (int)ceil($raw / 3) * 4;
}

/**
 * What may be uploaded, extension => media type.
 *
 * A PDF is the one that previews in the browser on every device, which is why
 * it is the format the send encourages. The rest download.
 */
const EV_DOC_TYPES = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
];

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

    $db->exec("CREATE TABLE IF NOT EXISTS ev_documents (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        event_id      INT          NOT NULL,
        stored_name   VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        title         VARCHAR(255),
        note          VARCHAR(400),
        bytes         INT          NOT NULL DEFAULT 0,
        ext           VARCHAR(10)  NOT NULL DEFAULT '',
        attach        TINYINT(1)   NOT NULL DEFAULT 1,
        sort_order    INT          NOT NULL DEFAULT 0,
        uploaded_by   VARCHAR(255),
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event (event_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // One row per send, so the page can say what went out and to how many, and
    // nobody has to guess whether they already sent the handouts.
    $db->exec("CREATE TABLE IF NOT EXISTS ev_sends (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        event_id    INT          NOT NULL,
        sent_by     VARCHAR(255),
        sent_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        recipients  INT          NOT NULL DEFAULT 0,
        failures    INT          NOT NULL DEFAULT 0,
        doc_count   INT          NOT NULL DEFAULT 0,
        attached    TINYINT(1)   NOT NULL DEFAULT 1,
        note        VARCHAR(400),
        INDEX idx_event (event_id, sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    evDocsDirReady();
    evEnsureMapColumns();

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

/**
 * Latitude and longitude, added after the table existed.
 */
function evEnsureMapColumns(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;

    $ok = true;
    foreach (['map_lat' => 'DECIMAL(10,7) DEFAULT NULL',
              'map_lng' => 'DECIMAL(10,7) DEFAULT NULL'] as $col => $type) {
        try {
            $has = (int)getDB()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ev_events'
                   AND COLUMN_NAME = '{$col}'"
            )->fetchColumn();
            if (!$has) {
                getDB()->exec("ALTER TABLE ev_events ADD COLUMN {$col} {$type}");
            }
        } catch (\Throwable $e) {
            // No ALTER grant, a locked table, a check that threw. The pin is
            // then unavailable — but saving a seminar's title and times must
            // not go with it, so this is remembered and evUpdate leaves the
            // two columns alone rather than dying on "Unknown column".
            error_log('evEnsureMapColumns ' . $col . ': ' . $e->getMessage());
            $ok = false;
        }
    }
    return $ok;
}

/** True when the pin columns are actually there to be written. */
function evMapColumnsOk(): bool {
    return evEnsureMapColumns();
}

/**
 * Coordinates out of whatever the organiser pasted.
 *
 * Accepts a bare "54.7801, -127.1686" or a link copied from Google, Apple or
 * OpenStreetMap. Parsing a pasted link rather than geocoding an address keeps
 * this free of an API key, a rate limit and a service that can be down when
 * somebody is trying to publish a seminar.
 *
 * Returns ['lat' => float, 'lng' => float] or null.
 */
function evParseLatLng(string $text): ?array {
    $t = trim($text);
    if ($t === '') return null;

    // The fraction is optional: "54, -127" is a coordinate, and a Google link
    // sitting on a whole degree reads as @54,-127,17z. Rejecting those told the
    // organiser to paste exactly what they had just pasted.
    $n = '(-?\d{1,3}(?:\.\d+)?)';
    $patterns = [
        '/@' . $n . ',\s*' . $n . '/',                        // google  /@54.78,-127.16,17z
        '/[?&]q=' . $n . ',\s*' . $n . '/',                   // google  ?q=lat,lng
        '/[?&]ll=' . $n . ',\s*' . $n . '/',                  // apple   ?ll=lat,lng
        '/[?&]mlat=' . $n . '.*?[?&]mlon=' . $n . '/',         // osm pin
        '/#map=\d+\/' . $n . '\/' . $n . '/',                  // osm     #map=17/lat/lng
        '/^\s*' . $n . '\s*,\s*' . $n . '\s*$/',               // plain pair
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $t, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180
                && !($lat == 0.0 && $lng == 0.0)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }
    }
    return null;
}

function evHasMap(array $event): bool {
    return isset($event['map_lat'], $event['map_lng'])
        && $event['map_lat'] !== null && $event['map_lng'] !== null;
}

/**
 * A link that opens the location in whatever maps app the reader has.
 *
 * Coordinates when we have them, the typed address otherwise — so the link is
 * useful even on a seminar nobody has pinned.
 */
function evMapsLink(array $event): string {
    if (evHasMap($event)) {
        return 'https://www.google.com/maps/search/?api=1&query='
             . rawurlencode($event['map_lat'] . ',' . $event['map_lng']);
    }
    $where = trim((string)($event['location'] ?? ''));
    if ($where === '') return '';
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($where);
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
    $newId = (int)$db->lastInsertId();
    evStateRebuild();
    return $newId;
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
    $withMap = evMapColumnsOk();

    $sql = "UPDATE ev_events SET
                title = ?, summary = ?, description = ?, starts_at = ?, ends_at = ?,
                location = ?, confirm_message = ?, notify_email = ?"
         . ($withMap ? ", map_lat = ?, map_lng = ?" : "")
         . ", updated_at = NOW()
            WHERE id = ?";

    $args = [
        evFit($d['title'], 200), evFit($d['summary'] ?? '', 400),
        ($d['description'] ?? '') ?: null,
        $d['starts_at'] ?: null, $d['ends_at'] ?: null,
        evFit($d['location'] ?? '', 255), ($d['confirm_message'] ?? '') ?: null,
        evFit($d['notify_email'] ?? '', 255),
    ];
    if ($withMap) {
        $args[] = $d['map_lat'] ?? null;
        $args[] = $d['map_lng'] ?? null;
    }
    $args[] = $id;

    getDB()->prepare($sql)->execute($args);
    evStateRebuild();
}

function evSetStatus(int $id, string $status): void {
    if (!in_array($status, ['draft', 'open', 'closed'], true)) return;
    getDB()->prepare("UPDATE ev_events SET status = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$status, $id]);
    evStateRebuild();
}

function evDelete(int $id): void {
    $db = getDB();
    // Children first — nothing in the schema enforces this for us.
    $db->prepare("DELETE FROM ev_registrations WHERE event_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM ev_questions     WHERE event_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM ev_events        WHERE id = ?")->execute([$id]);
    evStateRebuild();
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

// ── Documents ─────────────────────────────────────────────────────────────────

/**
 * Make the handouts folder, and make sure it is closed.
 *
 * The deny rule is not decoration: a direct path to a file in here would
 * bypass event-doc.php and with it the check that a draft seminar's handouts
 * are not public yet. Anything that creates this folder must write the rule in
 * the same breath, or the first upload after a folder is lost reopens it.
 */
function evDocsDirReady(): bool {
    if (!is_dir(EV_DOCS_DIR)) @mkdir(EV_DOCS_DIR, 0750, true);
    if (!is_dir(EV_DOCS_DIR)) return false;
    $ht = EV_DOCS_DIR . '.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n");
    return is_file($ht);
}

function evDocuments(int $eventId): array {
    try {
        $s = getDB()->prepare(
            "SELECT * FROM ev_documents WHERE event_id = ? ORDER BY sort_order ASC, id ASC"
        );
        $s->execute([$eventId]);
        return $s->fetchAll();
    } catch (\Throwable $e) {
        error_log('evDocuments: ' . $e->getMessage());
        return [];
    }
}

function evDocument(int $id, int $eventId): ?array {
    // event-doc.php is public; a missing table here should be a 404, not a 500.
    try {
        $s = getDB()->prepare("SELECT * FROM ev_documents WHERE id = ? AND event_id = ? LIMIT 1");
        $s->execute([$id, $eventId]);
        return $s->fetch() ?: null;
    } catch (\Throwable $e) {
        error_log('evDocument: ' . $e->getMessage());
        return null;
    }
}

/** The handouts that go into the email, in order, skipping any missing file. */
function evAttachableDocs(int $eventId): array {
    $out = [];
    foreach (evDocuments($eventId) as $d) {
        if (empty($d['attach'])) continue;
        if (!is_file(EV_DOCS_DIR . $d['stored_name'])) continue;
        $out[] = $d;
    }
    return $out;
}

function evDocBytes(array $docs): int {
    $n = 0;
    foreach ($docs as $d) $n += (int)$d['bytes'];
    return $n;
}

function evHumanBytes(int $n): string {
    if ($n >= 1048576) return number_format($n / 1048576, 1) . ' MB';
    if ($n >= 1024)    return number_format($n / 1024) . ' KB';
    return $n . ' bytes';
}

/**
 * Take one uploaded handout.
 *
 * Returns ['ok' => bool, 'error' => string]. The stored name is ours, not
 * theirs: the name a browser sends is attacker-controlled and ends up in a
 * filesystem path.
 */
function evDocAdd(int $eventId, array $file, string $title, string $note, string $by): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'was not sent.'];
    }
    if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'is larger than the server will accept ('
              . evHumanBytes(evMaxUploadBytes()) . ').'];
    }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => 'did not arrive in one piece.'];
    }
    $cap = evMaxUploadBytes();
    if ((int)$file['size'] > $cap) {
        return ['ok' => false, 'error' => 'is ' . evHumanBytes((int)$file['size'])
              . ' — the limit is ' . evHumanBytes($cap) . '.'];
    }

    $orig = (string)($file['name'] ?? 'document');
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!isset(EV_DOC_TYPES[$ext])) {
        return ['ok' => false, 'error' => 'is not a kind of file we take. Allowed: '
              . implode(', ', array_keys(EV_DOC_TYPES)) . '.'];
    }

    if (!evDocsDirReady()) {
        return ['ok' => false, 'error' => 'could not be saved — the handouts folder could not '
              . 'be prepared on the server.'];
    }
    if (!is_writable(EV_DOCS_DIR)) {
        return ['ok' => false, 'error' => 'could not be saved — the handouts folder is not '
              . 'writable on the server.'];
    }

    $stored = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], EV_DOCS_DIR . $stored)) {
        return ['ok' => false, 'error' => 'could not be saved. Check server permissions.'];
    }
    @chmod(EV_DOCS_DIR . $stored, 0640);

    $db   = getDB();
    $next = (int)$db->query(
        "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ev_documents WHERE event_id = " . (int)$eventId
    )->fetchColumn();

    try {
        $db->prepare(
            "INSERT INTO ev_documents
             (event_id, stored_name, original_name, title, note, bytes, ext, attach, sort_order, uploaded_by)
             VALUES (?,?,?,?,?,?,?,1,?,?)"
        )->execute([
            $eventId, $stored, evFit($orig, 255), evFit($title, 255), evFit($note, 400),
            (int)$file['size'], $ext, $next, evFit($by, 255),
        ]);
    } catch (\Throwable $e) {
        // The file is already on disk. Without this it stays there referenced
        // by nothing, and the organiser gets a 500 rather than a sentence.
        @unlink(EV_DOCS_DIR . $stored);
        error_log('evDocAdd: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'could not be recorded, so it was not kept.'];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * PHP hands a multiple-file field back inside out: ['name' => [a, b], 'size'
 * => [1, 2]] rather than a list of files. Turn it the right way round.
 */
function evNormaliseUploads(array $field): array {
    if (!isset($field['name'])) return [];
    if (!is_array($field['name'])) return [$field];   // single-file field

    $out = [];
    foreach (array_keys($field['name']) as $i) {
        $out[] = [
            'name'     => $field['name'][$i]     ?? '',
            'type'     => $field['type'][$i]     ?? '',
            'tmp_name' => $field['tmp_name'][$i] ?? '',
            'error'    => $field['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
            'size'     => $field['size'][$i]     ?? 0,
        ];
    }
    return $out;
}

/**
 * Take a whole selection at once.
 *
 * Each file stands or falls on its own: one that is too big or of the wrong
 * kind is reported and skipped, and the rest still land. Rejecting the batch
 * because of one file would mean re-picking the other nine.
 *
 * A title and note are only applied when a single file was chosen — there is
 * no sensible way to spread one title across six documents, and each keeps its
 * own filename instead.
 *
 * Returns ['added' => int, 'errors' => string[]].
 */
function evDocAddMany(int $eventId, array $field, string $title, string $note, string $by): array {
    $files = array_values(array_filter(evNormaliseUploads($field), function ($f) {
        return ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE || ($f['name'] ?? '') !== '';
    }));

    if (!$files) return ['added' => 0, 'errors' => ['Choose at least one file to upload.']];

    /*
     * PHP keeps the first max_file_uploads files and drops the rest without a
     * word — no error entry, nothing. Landing exactly on the cap is the only
     * sign we get, so it is worth saying out loud rather than reporting "20
     * uploaded" over a selection of twenty-five.
     */
    $cap     = evMaxFiles();
    $atCap   = count($files) >= $cap;
    $single  = count($files) === 1;
    $added  = 0;
    $errors = [];

    foreach ($files as $f) {
        $res = evDocAdd(
            $eventId, $f,
            $single ? $title : '',
            $single ? $note  : '',
            $by
        );
        if ($res['ok']) {
            $added++;
        } else {
            $shown = trim((string)($f['name'] ?? 'That file'));
            if ($shown === '') $shown = 'That file';
            $errors[] = mb_substr($shown, 0, 80) . ' ' . $res['error'];
        }
    }

    if ($atCap) {
        $errors[] = 'This server takes at most ' . $cap . ' files at a time, and that many '
                  . 'arrived. If you picked more, the rest were not received — add them in '
                  . 'a second batch and check the list above.';
    }

    return ['added' => $added, 'errors' => $errors];
}

function evDocDelete(int $id, int $eventId): void {
    $d = evDocument($id, $eventId);
    if (!$d) return;
    getDB()->prepare("DELETE FROM ev_documents WHERE id = ? AND event_id = ?")->execute([$id, $eventId]);
    $path = EV_DOCS_DIR . $d['stored_name'];
    if (is_file($path)) @unlink($path);
}

/** Rename a handout after the fact — the way a batch upload gets tidied up. */
function evDocRename(int $id, int $eventId, string $title, string $note): void {
    getDB()->prepare("UPDATE ev_documents SET title = ?, note = ? WHERE id = ? AND event_id = ?")
           ->execute([evFit($title, 255), evFit($note, 400), $id, $eventId]);
}

function evDocSetAttach(int $id, int $eventId, bool $on): void {
    getDB()->prepare("UPDATE ev_documents SET attach = ? WHERE id = ? AND event_id = ?")
           ->execute([$on ? 1 : 0, $id, $eventId]);
}

/** What a reader should see this called. */
function evDocLabel(array $d): string {
    $t = trim((string)($d['title'] ?? ''));
    return $t !== '' ? $t : (string)$d['original_name'];
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

/** A link straight to one handout, so nobody has to go hunting for it. */
function evDocUrl(array $event, array $doc): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
    return $scheme . '://' . $host . '/event-doc.php?s=' . urlencode($event['slug'])
         . '&d=' . (int)$doc['id'];
}

function evCancelUrl(array $reg): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
    return $scheme . '://' . $host . '/event-cancel.php?t=' . urlencode($reg['cancel_token']);
}

// ── Email ─────────────────────────────────────────────────────────────────────

function evSendConfirmation(array $event, array $reg, array $answers): void {
    require_once __DIR__ . '/email-templates-db.php';

    // Only the prose is editable. The date, the organiser's own note, what the
    // person answered and the link they need are printed here, so wording that
    // drifts can never cost somebody the link to cancel.
    $vars = [
        '{{name}}'  => (string)$reg['name'],
        '{{title}}' => (string)$event['title'],
        '{{when}}'  => evWhen($event),
    ];

    $lines = [emailTplBlock('event_registered', 'intro', $vars), ''];

    $lines[] = 'When:  ' . evWhen($event);
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
    $lines[] = emailTplBlock('event_registered', 'change', $vars);
    $lines[] = evCancelUrl($reg);
    $lines[] = '';
    $lines[] = emailTplBlock('event_registered', 'signoff', $vars);

    siteMail($reg['email'], emailTplSubject('event_registered', $vars), implode("\n", $lines));
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

// ── Sending the handouts ──────────────────────────────────────────────────────

function evSends(int $eventId): array {
    try {
        $s = getDB()->prepare("SELECT * FROM ev_sends WHERE event_id = ? ORDER BY sent_at DESC, id DESC");
        $s->execute([$eventId]);
        return $s->fetchAll();
    } catch (\Throwable $e) {
        error_log('evSends: ' . $e->getMessage());
        return [];
    }
}

/**
 * Email everyone still registered, with the handouts.
 *
 * Sent one message at a time rather than one message to everybody: each
 * registrant gets their own link to change or cancel, and a shared To line
 * would hand every attendee's address to every other attendee.
 *
 * Attachments are dropped — not the email — when the handouts together exceed
 * what mail will carry. The message then links to them instead, because an
 * email that silently fails to arrive is worse than one without a PDF on it.
 *
 * Returns ['sent' => int, 'failed' => int, 'attached' => bool, 'docs' => int].
 */
function evSendMaterials(array $event, string $sentBy, string $message = ''): array {
    $people = evRegistrations((int)$event['id']);
    $docs   = evAttachableDocs((int)$event['id']);
    $bytes  = evDocBytes($docs);

    // Over the ceiling, everyone gets links rather than some people getting a
    // bounce. All or nothing, so two registrants never receive different things.
    $attach = $docs && evEncodedBytes($bytes) <= EV_MAIL_MAX_BYTES;

    $files = [];
    if ($attach) {
        foreach ($docs as $d) {
            $files[] = ['path' => EV_DOCS_DIR . $d['stored_name'],
                        'name' => evDocFileName($d)];
        }
    }

    $sent = 0; $failedTo = [];
    foreach ($people as $r) {
        $body = evMaterialsBody($event, $r, $docs, $attach, $message);
        $subj = 'Before ' . $event['title'];
        try {
            $ok = $files
                ? siteMailWithAttachments($r['email'], $subj, $body, $files)
                : siteMail($r['email'], $subj, $body);
        } catch (\Throwable $e) {
            error_log('evSendMaterials: ' . $r['email'] . ' — ' . $e->getMessage());
            $ok = false;
        }
        if ($ok) { $sent++; } else { $failedTo[] = (string)$r['email']; }
    }
    $failed = count($failedTo);

    try {
        getDB()->prepare(
            "INSERT INTO ev_sends (event_id, sent_by, recipients, failures, doc_count, attached, note)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([(int)$event['id'], evFit($sentBy, 255), $sent, $failed,
                    count($docs), $attach ? 1 : 0, evFit($message, 400)]);
    } catch (\Throwable $e) {
        error_log('evSendMaterials log: ' . $e->getMessage());
    }

    return ['sent' => $sent, 'failed' => $failed, 'failed_to' => $failedTo,
            'attached' => $attach, 'docs' => count($docs)];
}

/** A readable filename for the attachment, not our storage name. */
function evDocFileName(array $d): string {
    $label = evDocLabel($d);
    $ext   = strtolower((string)$d['ext']);
    $base  = preg_replace('/[^A-Za-z0-9 ._-]+/', '', $label);
    $base  = trim(preg_replace('/\s+/', ' ', (string)$base));
    if ($base === '') $base = 'handout';
    if (strtolower(pathinfo($base, PATHINFO_EXTENSION)) === $ext) {
        $base = (string)pathinfo($base, PATHINFO_FILENAME);
    }
    // Trim the name, then add the extension. The other order ate it: a long
    // title came back as "....p" or with no extension at all, and an
    // attachment that has lost its .pdf opens in nothing.
    $base = rtrim(mb_substr($base, 0, 100));
    if ($base === '') $base = 'handout';
    return $base . '.' . $ext;
}

function evMaterialsBody(array $event, array $reg, array $docs, bool $attach, string $message): string {
    $lines = [
        'Hi ' . $reg['name'] . ',',
        '',
        $event['title'] . ' is coming up.',
        '',
        'When:  ' . evWhen($event),
    ];
    if ($event['location']) $lines[] = 'Where: ' . $event['location'];

    if (trim($message) !== '') {
        $lines[] = '';
        $lines[] = trim($message);
    }

    if ($docs) {
        $lines[] = '';
        $lines[] = $attach
            ? (count($docs) === 1 ? 'The handout is attached.' : 'The handouts are attached.')
            : 'Too large to attach, so here they are — one link each:';
        foreach ($docs as $d) {
            $lines[] = '';
            $lines[] = '  ' . evDocLabel($d);
            if (trim((string)$d['note']) !== '') $lines[] = '    ' . $d['note'];
            // A link to the file itself, not to the page it is listed on. The
            // whole point is not having to go looking for it.
            $lines[] = '    ' . evDocUrl($event, $d);
        }
        $lines[] = '';
        $lines[] = 'Seminar details: ' . evPublicUrl($event);
    } else {
        $lines[] = '';
        $lines[] = 'Seminar details: ' . evPublicUrl($event);
    }

    $lines[] = '';
    $lines[] = 'Cannot make it after all? Let us know here:';
    $lines[] = evCancelUrl($reg);
    $lines[] = '';
    $lines[] = 'Bulkley Valley Teachers\' Union';

    return implode("\n", $lines);
}
