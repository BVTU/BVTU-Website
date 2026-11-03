<?php
/**
 * tools/library-search-test.php — what the resource library search must find.
 *
 * The search has two paths: MySQL FULLTEXT where the ft_search index exists,
 * and a LIKE scan otherwise or wherever boolean mode cannot answer honestly.
 * Both have been silently wrong — "math grade 4" returned an empty library for
 * three different reasons at once — and an empty result is indistinguishable
 * from "we have nothing on that", so these are easy failures to ship.
 *
 * Runs against SQLite in memory, with libGetResources() read out of
 * members/library-db.php rather than copied, so the test cannot drift away
 * from the code it is testing. SQLite has no FULLTEXT, so the second suite
 * asserts which path the query was BUILT on instead of running it.
 *
 *   php tools/library-search-test.php
 *
 * Exits non-zero on failure, so it can gate a commit the way php-check.py does.
 *
 * It runs the second suite by re-running itself: libGetResources() remembers
 * whether the index exists in a static, so one process cannot be asked both
 * questions — the first three path assertions passed against a cached "no
 * index" and proved nothing until this was split.
 */

$root  = dirname(__DIR__);
$suite = $argv[1] ?? 'rows';

// ── The function under test, lifted from the source ─────────────────────────
$src = file_get_contents($root . '/members/library-db.php');
if (!preg_match('/\nfunction libGetResources\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
    fwrite(STDERR, "libGetResources() not found in members/library-db.php\n");
    exit(2);
}
$start = $m[0][1] + 1;
$i = strpos($src, '{', $start);
for ($depth = 0; $i < strlen($src); $i++) {
    if ($src[$i] === '{') $depth++;
    elseif ($src[$i] === '}' && --$depth === 0) break;
}
eval(substr($src, $start, $i - $start + 1));

// ── A database that answers the MySQL-only probes ───────────────────────────
class LibTestPDO extends PDO
{
    public $lastSql = '';
    public $hasFT   = false;
    public $minTok  = 3;

    public function query(string $q, ?int $mode = null, ...$args): PDOStatement|false
    {
        if (stripos($q, 'SHOW INDEX') === 0) {
            return parent::query($this->hasFT ? "SELECT 'ft_search' AS Key_name" : 'SELECT 1 WHERE 0');
        }
        if (stripos($q, 'INNODB_FT_DEFAULT_STOPWORD') !== false) {
            // Refused the way a shared host refuses it, so the built-in list
            // is what the stopword tests actually exercise.
            throw new PDOException('Access denied; you need the PROCESS privilege');
        }
        if (stripos($q, 'SHOW VARIABLES') === 0) {
            return parent::query('SELECT ' . (int)$this->minTok . ' AS Value');
        }
        return parent::query($q);
    }

    public function prepare(string $q, array $opts = []): PDOStatement|false
    {
        $this->lastSql = $q;
        return parent::prepare($q, $opts);
    }
}

$DB = new LibTestPDO('sqlite::memory:');
$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$DB->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$DB->sqliteCreateFunction('FIND_IN_SET', function ($needle, $hay) {
    return in_array((string)$needle, array_map('trim', explode(',', (string)$hay)), true) ? 1 : 0;
});

function getDB() { global $DB; return $DB; }
function libEnsureTables() { /* the real one runs MySQL DDL */ }

$DB->exec("CREATE TABLE library_resources (
    id INTEGER PRIMARY KEY, title TEXT DEFAULT '', description TEXT DEFAULT '',
    tags TEXT DEFAULT '', bc_curriculum TEXT DEFAULT '', subject TEXT DEFAULT '',
    grade_levels TEXT DEFAULT '', resource_type TEXT DEFAULT '', materials TEXT DEFAULT '',
    uploader_name TEXT DEFAULT '', uploader_email TEXT DEFAULT '', anonymous INT DEFAULT 0,
    status TEXT DEFAULT 'published', created_at TEXT DEFAULT '', download_count INT DEFAULT 0,
    avg_rating REAL DEFAULT 0, rating_count INT DEFAULT 0)");

$ins = $DB->prepare("INSERT INTO library_resources
    (id,title,description,subject,grade_levels,resource_type,tags,uploader_name,anonymous)
    VALUES (?,?,?,?,?,?,?,?,?)");
$ins->execute([1, 'Fractions warm-ups', 'Daily starters for the maths block',
               'Math', '4,5', 'worksheet', 'fractions', 'Cody Lind', 0]);
$ins->execute([2, 'Grade 4 science unit', 'Habitats and ecosystems',
               'Science', '4', 'unit plan', 'habitats', 'Kathy McIntyre', 0]);
$ins->execute([3, 'Math games', 'Card games for number sense',
               'Math', '6,7', 'activity', 'games', 'Tanya Davidson', 0]);
$ins->execute([4, 'Reading 100% decodable', 'Phonics readers',
               'English', '1', 'reader', 'phonics', 'Vittoria Yano', 0]);
$ins->execute([5, 'Silent reading log', 'A weekly log sheet',
               'English', '2', 'worksheet', 'logs', 'Jo Smith', 1]);

// ── Harness ─────────────────────────────────────────────────────────────────
$fail = 0;
function t(string $name, $got, $want): void {
    global $fail;
    $g = var_export($got, true);
    $w = var_export($want, true);
    if ($g !== $w) { $fail++; echo "FAIL  $name\n      got $g want $w\n"; }
    else           { echo "ok    $name\n"; }
}

function ids(string $q, array $extra = []): array {
    $rows = libGetResources(array_merge(['q' => $q], $extra));
    $out  = array_map('intval', array_column($rows, 'id'));
    sort($out);
    return $out;
}

if ($suite === 'paths') {
    // ── Which path the query is built on ────────────────────────────────────
    //
    // Only meaningful with the index present, and SQLite cannot run MATCH(),
    // so these assert the SQL that was built rather than the rows it returns.
    $DB->hasFT  = true;
    $DB->minTok = 3;

    $pathFor = function (string $q) use ($DB): string {
        $DB->lastSql = '';
        try { libGetResources(['q' => $q]); } catch (\Throwable $e) { /* MATCH is MySQL-only */ }
        if (strpos($DB->lastSql, 'MATCH(') !== false)            return 'fulltext';
        if (strpos($DB->lastSql, "LIKE ? ESCAPE '|'") !== false) return 'like';
        return 'neither';
    };

    echo "— choosing the right path —\n";
    t('ordinary words use the index',      $pathFor('fractions warmups'), 'fulltext');
    t('a three-letter word still does',    $pathFor('art lessons'), 'fulltext');
    t('a one-character word cannot',       $pathFor('math 4'), 'like');
    t('  ...even among long ones',         $pathFor('fractions number 4'), 'like');
    t('a stopword cannot be indexed',      $pathFor('games with dice'), 'like');
    t('  ...whatever its length',          $pathFor('notes from class'), 'like');
    t('"grade 4" is lifted out first',     $pathFor('math grade 4'), 'fulltext');
    t('  ...leaving a grade and no text',  $pathFor('grade 4'), 'neither');

    echo "\n" . ($fail ? "$fail FAILED\n" : "all passed\n");
    exit($fail ? 1 : 0);
}

// ── What a search has to find (LIKE path: no ft_search index) ───────────────
echo "— finding things —\n";
t('the reported case: math grade 4',   ids('math grade 4'), [1]);
t('  ...and grade 4 science',          ids('grade 4 science'), [2]);
t('  ...word order does not matter',   ids('grade 4 math'), [1]);
t('a grade alone',                     ids('grade 4'), [1, 2]);
t('grades, plural',                    ids('grades 6 math'), [3]);
t('no space',                          ids('math grade4'), [1]);
t('one word still works',              ids('math'), [1, 3]);
t('a phrase in one field',             ids('number sense'), [3]);
t('across two fields',                 ids('science habitats'), [2]);
t('nothing matches',                   ids('astronomy'), []);
t('punctuation is stripped',           ids('math, grade 4'), [1]);
t('extra spaces ignored',              ids('  math   games '), [3]);
t('combines with a grade filter',      ids('math', ['grades' => ['4']]), [1]);
t('combines with a subject filter',    ids('games', ['subject' => 'Math']), [3]);

echo "\n— grades written the way people write them —\n";
t('a range',                           ids('grades 4-7 science'), [2]);
t('  ...the whole range, alone',       ids('grades 4-7'), [1, 2, 3]);
t('  ...an en dash',                   ids("grades 4\xe2\x80\x937 science"), [2]);
t('  ...written as "to"',              ids('grades 6 to 7 math'), [3]);
t('  ...backwards',                    ids('grades 7-6 math'), [3]);
t('a comma-separated list',            ids('grades 4, 5 fractions'), [1]);
t('  ...with a slash',                 ids('grade 6/7 math'), [3]);
t('  ...with "and"',                   ids('grades 6 and 7 math'), [3]);
t('a grade that does not exist',       ids('grade 13 science'), []);
t('  ...and "grade" is not searched',  ids('grade 13'), []);
t('a range entirely out of reach',     ids('grade 13-15 math'), []);
t('typed grade ORs with a ticked one', ids('math grade 4', ['grades' => ['6']]), [1, 3]);

echo "\n— and what it must not find —\n";
t('every word counts, not the first six',
  ids('math games card number sense activity tanya'), [3]);
t('  ...an eighth word that misses',
  ids('math games card number sense activity tanya zebra'), []);
t('wildcards stay literal',            ids('100%'), [4]);
t('underscore stays literal',          ids('_'), []);
t('an anonymous uploader is not searchable by name', ids('jo smith'), []);
t('  ...while a named one still is',   ids('tanya davidson'), [3]);

// The other half, in its own process for the reason given at the top.
echo "\n";
$code = 0;
// The child inherits the ini settings it was launched with, not the parent's.
passthru(escapeshellarg(PHP_BINARY)
       . ' -d pcre.jit=' . (ini_get('pcre.jit') ? '1' : '0')
       . ' ' . escapeshellarg(__FILE__) . ' paths', $code);
if ($code !== 0) $fail++;

echo "\n" . ($fail ? "FAILED\n" : "all passed\n");
exit($fail ? 1 : 0);
