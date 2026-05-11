<?php
/**
 * library-db.php — BVTU Resource Library database helpers
 */
require_once __DIR__ . '/db.php';
date_default_timezone_set('America/Vancouver');

define('LIB_UPLOAD_DIR', __DIR__ . '/library-uploads/');
define('LIB_MAX_BYTES',  20 * 1024 * 1024); // 20 MB
define('LIB_ALLOWED_EXT', ['pdf', 'docx', 'pptx']);
define('LIB_ALLOWED_MIME', [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
]);

const LIB_GRADES   = ['K','1','2','3','4','5','6','7','8','9','10','11','12'];

// Standard BC subjects — K-12.
// 'Other' must stay last; upload form shows a free-text input when it is selected.
const LIB_SUBJECTS = [
    // ── Elementary & cross-grade ─────────────────────────────
    'Math',
    'English / ELA',
    'Science',
    'Social Studies',
    'French',
    'Arts',
    'PE / Health',
    'ADST',
    // ── Secondary-specific ───────────────────────────────────
    'Physics',
    'Chemistry',
    'Biology',
    'Earth Science',
    'Computer Science',
    'Business Education',
    'Psychology',
    'Drama',
    'Visual Art',
    'Music',
    // ── Catch-all ────────────────────────────────────────────
    'Other',
];

const LIB_TYPES    = ['Lesson Plan','Unit Plan','Rubric','Activity','Assessment','Other'];

// ── Table creation ────────────────────────────────────────────────────────────
function libEnsureTables(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS library_resources (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        uploader_email  VARCHAR(255) NOT NULL,
        uploader_name   VARCHAR(255) NOT NULL,
        anonymous       TINYINT(1) DEFAULT 0,
        title           VARCHAR(500) NOT NULL,
        description     TEXT,
        grade_levels    VARCHAR(100) DEFAULT '',
        subject         VARCHAR(100) DEFAULT '',
        resource_type   VARCHAR(100) DEFAULT '',
        bc_curriculum   TEXT,
        time_required   VARCHAR(150),
        materials       TEXT,
        file_name       VARCHAR(255) NOT NULL,
        file_path       VARCHAR(500) NOT NULL,
        file_size       INT DEFAULT 0,
        file_ext        VARCHAR(10) DEFAULT '',
        status          VARCHAR(20) DEFAULT 'published',
        download_count  INT DEFAULT 0,
        avg_rating      DECIMAL(3,2) DEFAULT 0,
        rating_count    INT DEFAULT 0,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status   (status),
        INDEX idx_subject  (subject),
        INDEX idx_type     (resource_type),
        INDEX idx_uploader (uploader_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS library_ratings (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        resource_id INT NOT NULL,
        rater_email VARCHAR(255) NOT NULL,
        rater_name  VARCHAR(255) NOT NULL,
        rating      TINYINT NOT NULL,
        comment     TEXT,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_rating (resource_id, rater_email),
        INDEX idx_resource (resource_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS library_flags (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        resource_id     INT NOT NULL,
        reporter_email  VARCHAR(255) NOT NULL,
        reason          TEXT,
        reviewed        TINYINT(1) DEFAULT 0,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_resource (resource_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS library_bookmarks (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        member_email VARCHAR(255) NOT NULL,
        resource_id  INT NOT NULL,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_bookmark (member_email, resource_id),
        INDEX idx_bm_member   (member_email),
        INDEX idx_bm_resource (resource_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrations for existing tables
    try { $db->exec("ALTER TABLE library_resources ADD COLUMN tags VARCHAR(500) NOT NULL DEFAULT ''"); } catch (\PDOException $e) {}

    // FULLTEXT index — check before adding to avoid repeated attempts
    $ftExists = $db->query("SHOW INDEX FROM library_resources WHERE Key_name = 'ft_search'")->fetch();
    if (!$ftExists) {
        try { $db->exec("ALTER TABLE library_resources ADD FULLTEXT INDEX ft_search (title, description, tags, bc_curriculum)"); } catch (\PDOException $e) {}
    }

    // Ensure upload directory exists and is web-inaccessible
    if (!is_dir(LIB_UPLOAD_DIR)) mkdir(LIB_UPLOAD_DIR, 0750, true);
    $htaccess = LIB_UPLOAD_DIR . '.htaccess';
    if (!file_exists($htaccess)) file_put_contents($htaccess, "Require all denied\n");
}

// ── Resource CRUD ─────────────────────────────────────────────────────────────
function libSaveResource(array $d): int {
    libEnsureTables();
    $s = getDB()->prepare("INSERT INTO library_resources
        (uploader_email, uploader_name, anonymous, title, description,
         grade_levels, subject, resource_type, bc_curriculum, time_required,
         materials, tags, file_name, file_path, file_size, file_ext)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    // Normalise subject: title-case custom values; lowercase all tags
    $subject = libNormaliseSubject($d['subject'] ?? '');
    $tags    = libNormaliseTags($d['tags'] ?? '');

    $s->execute([
        $d['uploader_email'], $d['uploader_name'], $d['anonymous'] ? 1 : 0,
        $d['title'], $d['description'],
        $d['grade_levels'], $subject, $d['resource_type'],
        $d['bc_curriculum'] ?? null, $d['time_required'] ?? null,
        $d['materials'] ?? null,
        $tags,
        $d['file_name'], $d['file_path'], $d['file_size'], $d['file_ext'],
    ]);
    return (int)getDB()->lastInsertId();
}

function libGetResource(int $id): ?array {
    libEnsureTables();
    $s = getDB()->prepare("SELECT * FROM library_resources WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/**
 * Returns filtered, sorted resources.
 * $filters keys: grades (array), subject, type, tag, uploader (email),
 *                q (search), sort, status
 */
function libGetResources(array $filters = [], bool $adminView = false): array {
    libEnsureTables();
    $where       = [];
    $params      = [];
    $useFulltext = false;
    $ftScoreExpr = '';
    $ftParams    = [];

    if (!$adminView) {
        $where[] = "status = 'published'";
    } elseif (!empty($filters['status'])) {
        $where[]  = "status = ?";
        $params[] = $filters['status'];
    }

    // Grade filter — resource must include ANY of the selected grades
    if (!empty($filters['grades'])) {
        $gClauses = [];
        foreach ($filters['grades'] as $g) {
            $gClauses[] = "FIND_IN_SET(?, grade_levels)";
            $params[]   = $g;
        }
        $where[] = '(' . implode(' OR ', $gClauses) . ')';
    }

    if (!empty($filters['subject'])) {
        $where[]  = "LOWER(subject) = LOWER(?)";
        $params[] = $filters['subject'];
    }

    if (!empty($filters['type'])) {
        $where[]  = "resource_type = ?";
        $params[] = $filters['type'];
    }

    // Tag filter — tags stored lowercase so compare lowercase
    if (!empty($filters['tag'])) {
        $where[]  = "FIND_IN_SET(?, tags)";
        $params[] = strtolower(trim($filters['tag']));
    }

    // Uploader filter (non-anonymous only)
    if (!empty($filters['uploader'])) {
        $where[]  = "uploader_email = ? AND anonymous = 0";
        $params[] = $filters['uploader'];
    }

    // Keyword search — FULLTEXT if index exists, LIKE fallback
    if (!empty($filters['q'])) {
        $q = trim($filters['q']);
        static $hasFT = null;
        if ($hasFT === null) {
            $hasFT = (bool) getDB()->query("SHOW INDEX FROM library_resources WHERE Key_name = 'ft_search'")->fetch();
        }
        if ($hasFT) {
            // Boolean mode: each word becomes +word*, ensuring all words present
            $words = array_values(array_filter(array_map('trim', preg_split('/\s+/', preg_replace('/[+\-><\(\)~*"@]+/', ' ', $q)))));
            if ($words) {
                $ftq = implode(' ', array_map(fn($w) => '+' . $w . '*', $words));
                $where[]       = "MATCH(title, description, tags, bc_curriculum) AGAINST(? IN BOOLEAN MODE)";
                $params[]      = $ftq;
                $ftScoreExpr   = ", MATCH(title, description, tags, bc_curriculum) AGAINST(? IN BOOLEAN MODE) AS _score";
                $ftParams      = [$ftq];
                $useFulltext   = true;
            }
        } else {
            // LIKE fallback across all text fields
            $term     = '%' . $q . '%';
            $where[]  = "(title LIKE ? OR description LIKE ? OR tags LIKE ? OR bc_curriculum LIKE ? OR uploader_name LIKE ?)";
            $params   = array_merge($params, [$term, $term, $term, $term, $term]);
        }
    }

    // Build SELECT — prepend FT score params so they bind to the SELECT clause
    $sql    = "SELECT *" . $ftScoreExpr . " FROM library_resources";
    $allParams = array_merge($ftParams, $params);

    if ($where) $sql .= " WHERE " . implode(" AND ", $where);

    $sort = $filters['sort'] ?? 'newest';
    if ($useFulltext && $sort === 'newest') {
        $sql .= " ORDER BY _score DESC, created_at DESC";
    } elseif ($sort === 'downloads') {
        $sql .= " ORDER BY download_count DESC, created_at DESC";
    } elseif ($sort === 'rating') {
        $sql .= " ORDER BY avg_rating DESC, rating_count DESC, created_at DESC";
    } else {
        $sql .= " ORDER BY created_at DESC";
    }

    $s = getDB()->prepare($sql);
    $s->execute($allParams);
    return $s->fetchAll();
}

function libUpdateStatus(int $id, string $status): void {
    $s = getDB()->prepare("UPDATE library_resources SET status=? WHERE id=?");
    $s->execute([$status, $id]);
}

function libDelete(int $id): void {
    $r = libGetResource($id);
    if ($r) {
        $path = LIB_UPLOAD_DIR . $r['file_path'];
        if (file_exists($path)) @unlink($path);
    }
    $db = getDB();
    $db->prepare("DELETE FROM library_ratings    WHERE resource_id=?")->execute([$id]);
    $db->prepare("DELETE FROM library_flags     WHERE resource_id=?")->execute([$id]);
    $db->prepare("DELETE FROM library_bookmarks WHERE resource_id=?")->execute([$id]);
    $db->prepare("DELETE FROM library_resources WHERE id=?")->execute([$id]);
}

function libIncrementDownload(int $id): void {
    getDB()->prepare("UPDATE library_resources SET download_count=download_count+1 WHERE id=?")->execute([$id]);
}

// ── Ratings ───────────────────────────────────────────────────────────────────
function libAddRating(int $resourceId, string $email, string $name, int $rating, string $comment): void {
    libEnsureTables();
    $db = getDB();
    $db->prepare("INSERT INTO library_ratings (resource_id, rater_email, rater_name, rating, comment)
                  VALUES (?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment), created_at=NOW()")
       ->execute([$resourceId, $email, $name, $rating, $comment]);

    // Recalculate avg
    $s = $db->prepare("SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM library_ratings WHERE resource_id=?");
    $s->execute([$resourceId]);
    $row = $s->fetch();
    $db->prepare("UPDATE library_resources SET avg_rating=?, rating_count=? WHERE id=?")
       ->execute([round((float)$row['avg'], 2), (int)$row['cnt'], $resourceId]);
}

function libGetRatings(int $resourceId): array {
    libEnsureTables();
    $s = getDB()->prepare("SELECT * FROM library_ratings WHERE resource_id=? ORDER BY created_at DESC");
    $s->execute([$resourceId]);
    return $s->fetchAll();
}

function libGetMemberRating(int $resourceId, string $email): ?array {
    $s = getDB()->prepare("SELECT * FROM library_ratings WHERE resource_id=? AND rater_email=?");
    $s->execute([$resourceId, $email]);
    return $s->fetch() ?: null;
}

// ── Flags ─────────────────────────────────────────────────────────────────────
function libAddFlag(int $resourceId, string $email, string $reason): void {
    libEnsureTables();
    // One flag per member per resource
    $s = getDB()->prepare("SELECT id FROM library_flags WHERE resource_id=? AND reporter_email=?");
    $s->execute([$resourceId, $email]);
    if ($s->fetch()) return;
    getDB()->prepare("INSERT INTO library_flags (resource_id, reporter_email, reason) VALUES (?,?,?)")
           ->execute([$resourceId, $email, $reason]);
}

function libGetFlags(bool $unreviewedOnly = false): array {
    libEnsureTables();
    $sql = "SELECT f.*, r.title AS resource_title, r.status AS resource_status
            FROM library_flags f
            JOIN library_resources r ON r.id = f.resource_id";
    if ($unreviewedOnly) $sql .= " WHERE f.reviewed=0";
    $sql .= " ORDER BY f.created_at DESC";
    return getDB()->query($sql)->fetchAll();
}

function libMarkFlagReviewed(int $flagId): void {
    getDB()->prepare("UPDATE library_flags SET reviewed=1 WHERE id=?")->execute([$flagId]);
}

// ── Stats ─────────────────────────────────────────────────────────────────────
function libStats(): array {
    libEnsureTables();
    $db = getDB();
    return [
        'total'     => (int)$db->query("SELECT COUNT(*) FROM library_resources")->fetchColumn(),
        'published' => (int)$db->query("SELECT COUNT(*) FROM library_resources WHERE status='published'")->fetchColumn(),
        'downloads' => (int)$db->query("SELECT SUM(download_count) FROM library_resources")->fetchColumn(),
        'ratings'   => (int)$db->query("SELECT COUNT(*) FROM library_ratings")->fetchColumn(),
        'flags'     => (int)$db->query("SELECT COUNT(*) FROM library_flags WHERE reviewed=0")->fetchColumn(),
    ];
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Title-case a custom subject value.
 * Standard subjects are returned unchanged; "Other" is returned as-is.
 */
function libNormaliseSubject(string $subject): string {
    $subject = trim($subject);
    if (!$subject) return '';
    // Already a known subject — keep the canonical capitalisation
    foreach (LIB_SUBJECTS as $s) {
        if (strtolower($s) === strtolower($subject)) return $s;
    }
    // Custom subject — title-case it
    return ucwords(strtolower($subject));
}

/**
 * Normalise a comma-separated tags string: lowercase, trim, deduplicate.
 */
function libNormaliseTags(string $raw): string {
    $tags = array_values(array_unique(array_filter(
        array_map(fn($t) => strtolower(trim($t)), explode(',', $raw)),
        fn($t) => strlen($t) >= 2 && strlen($t) <= 40
    )));
    return implode(',', $tags);
}

/**
 * Returns subjects in the DB that are not in the standard LIB_SUBJECTS list.
 * Used by the filter sidebar to surface teacher-entered custom subjects.
 */
function libGetCustomSubjects(): array {
    libEnsureTables();
    $known = array_map('strtolower', LIB_SUBJECTS);
    $s = getDB()->query(
        "SELECT DISTINCT subject FROM library_resources
         WHERE status='published' AND subject != '' AND subject != 'Other'
         ORDER BY subject"
    );
    return array_values(array_filter(
        $s->fetchAll(\PDO::FETCH_COLUMN),
        fn($sub) => !in_array(strtolower($sub), $known, true)
    ));
}

function libFormatSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1024) . ' KB';
}

function libIsAdmin(string $email): bool {
    $e = strtolower(trim($email));
    if (defined('PROD_ADMIN_EMAIL') && $e === strtolower(trim(PROD_ADMIN_EMAIL))) return true;
    return $e === 'lp54@bctf.ca';
}

function libStars(float $avg, int $count): string {
    $full  = floor($avg);
    $half  = ($avg - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $out   = '';
    for ($i = 0; $i < $full;  $i++) $out .= '★';
    if ($half)                       $out .= '½';
    for ($i = 0; $i < $empty; $i++) $out .= '☆';
    return $out . ($count ? ' <span style="color:var(--gray-400);font-size:.8em;">(' . $count . ')</span>' : '');
}

// Send email notification to uploader when a new rating/comment is posted
function libNotifyRating(array $resource, string $raterName, int $rating, string $comment): void {
    if ($resource['anonymous'] || empty($resource['uploader_email'])) return;
    $title    = $resource['title'];
    $uploader = $resource['uploader_name'];
    $stars    = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    $commentLine = $comment ? "\nComment: \"{$comment}\"" : '';
    $body = <<<TEXT
Hi {$uploader},

{$raterName} left a rating on your resource "{$title}":

Rating: {$stars} ({$rating}/5){$commentLine}

View your resource at:
https://bvtu.ca/library-resource.php?id={$resource['id']}

Bulkley Valley Teachers' Union
TEXT;
    $headers = "From: BVTU <noreply@bvtu.ca>\r\nReply-To: lp54@bctf.ca\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($resource['uploader_email'], "New rating on \"{$title}\" — BVTU Library", $body, $headers);
}

// ── Bookmarks ─────────────────────────────────────────────────────────────────
function libToggleBookmark(int $resourceId, string $email): bool {
    libEnsureTables();
    $db = getDB();
    $s  = $db->prepare("SELECT id FROM library_bookmarks WHERE resource_id=? AND member_email=?");
    $s->execute([$resourceId, $email]);
    if ($s->fetch()) {
        $db->prepare("DELETE FROM library_bookmarks WHERE resource_id=? AND member_email=?")->execute([$resourceId, $email]);
        return false; // removed
    }
    $db->prepare("INSERT INTO library_bookmarks (resource_id, member_email) VALUES (?,?)")->execute([$resourceId, $email]);
    return true; // added
}

function libIsBookmarked(int $resourceId, string $email): bool {
    $s = getDB()->prepare("SELECT id FROM library_bookmarks WHERE resource_id=? AND member_email=?");
    $s->execute([$resourceId, $email]);
    return (bool) $s->fetch();
}

function libGetBookmarks(string $email): array {
    libEnsureTables();
    $s = getDB()->prepare(
        "SELECT r.* FROM library_resources r
         JOIN library_bookmarks b ON b.resource_id = r.id
         WHERE b.member_email = ? AND r.status = 'published'
         ORDER BY b.created_at DESC"
    );
    $s->execute([$email]);
    return $s->fetchAll();
}

// ── Tags ──────────────────────────────────────────────────────────────────────
/**
 * Returns suggested tags based on subject, type, and grades.
 * Used by the upload form to help lazy taggers.
 */
function libGetTagSuggestions(): array {
    return [
        'subject' => [
            'Math'              => ['number sense','place value','addition','subtraction','multiplication',
                                    'division','fractions','decimals','algebra','geometry','measurement',
                                    'statistics','patterns','integers','ratios','spatial reasoning',
                                    'pre-calculus','calculus','probability'],
            'English / ELA'     => ['reading','writing','grammar','comprehension','vocabulary',
                                    'oral language','phonics','spelling','poetry','media literacy',
                                    'text features','literary analysis','creative writing','research skills'],
            'Science'           => ['inquiry','scientific method','life science','ecosystems','matter',
                                    'energy','forces','sustainability','systems thinking',
                                    'climate','biodiversity','cells'],
            'Social Studies'    => ['community','history','geography','culture','first nations',
                                    'reconciliation','indigenous perspectives','local history',
                                    'government','economics','citizenship','continuity and change',
                                    'identity and culture','human rights'],
            'Physics'           => ['forces','motion','energy','waves','electricity','magnetism',
                                    'kinematics','dynamics','optics','modern physics'],
            'Chemistry'         => ['atoms','molecules','periodic table','reactions','stoichiometry',
                                    'acids and bases','organic chemistry','solutions','bonding'],
            'Biology'           => ['cells','genetics','evolution','ecology','body systems',
                                    'biodiversity','reproduction','photosynthesis','DNA'],
            'Earth Science'     => ['geology','plate tectonics','weather','climate','space',
                                    'oceans','rocks and minerals','natural resources'],
            'Computer Science'  => ['coding','programming','algorithms','data structures',
                                    'computational thinking','python','scratch','web design','cybersecurity'],
            'ADST'              => ['design thinking','making','prototyping','woodworking',
                                    'textiles','electronics','3D printing','entrepreneurship'],
            'Business Education'=> ['entrepreneurship','marketing','finance','accounting',
                                    'economics','career planning','digital literacy'],
            'French'            => ['vocabulary','grammar','conversation','reading','culture',
                                    'francophone','immersion'],
            'Arts'              => ['visual art','drama','music','dance','drawing','painting',
                                    'sculpture','performance','creative expression','Elements of Art'],
            'PE / Health'       => ['fitness','games','movement','cooperation','teamwork',
                                    'outdoor education','wellness','mental health','sport','nutrition'],
            'Psychology'        => ['mental health','behaviour','cognition','development',
                                    'research methods','social psychology'],
            'Drama'             => ['performance','script writing','improv','stagecraft',
                                    'character development','theatre history'],
            'Visual Art'        => ['drawing','painting','sculpture','design','art history',
                                    'elements of art','principles of design','printmaking'],
            'Music'             => ['theory','notation','rhythm','harmony','performance',
                                    'composition','ear training','history of music'],
            'Other'             => ['cross-curricular','project-based','career education',
                                    'life skills','core competencies','big ideas'],
        ],
        'type' => [
            'Lesson Plan'  => ['direct instruction','inquiry-based','differentiated','centres','project-based'],
            'Unit Plan'    => ['big ideas','cross-curricular','long-range planning','inquiry','backwards design'],
            'Rubric'       => ['self-assessment','peer assessment','criteria','performance standards','single-point'],
            'Activity'     => ['hands-on','group work','independent practice','game','outdoor','lab'],
            'Assessment'   => ['formative','summative','portfolio','checklist','observation','exit ticket'],
            'Other'        => ['template','parent communication','classroom management','anchor chart'],
        ],
        'grade' => [
            'K'  => ['kindergarten','early learning','play-based','emergent literacy'],
            '1'  => ['primary','grade 1'],
            '2'  => ['primary','grade 2'],
            '3'  => ['primary','grade 3'],
            '4'  => ['intermediate','grade 4'],
            '5'  => ['intermediate','grade 5'],
            '6'  => ['intermediate','grade 6'],
            '7'  => ['intermediate','grade 7'],
            '8'  => ['secondary','grade 8','middle school'],
            '9'  => ['secondary','grade 9','middle school'],
            '10' => ['secondary','grade 10'],
            '11' => ['secondary','grade 11'],
            '12' => ['secondary','grade 12','graduation'],
        ],
        // BC Curriculum language — shown when any subject is selected
        'bc_curriculum' => [
            'core competencies','big ideas','curricular competency','first peoples principles',
            'indigenous knowledge','reconciliation','inquiry','place-based learning',
            'project-based learning','differentiated instruction','universal design for learning',
        ],
    ];
}
