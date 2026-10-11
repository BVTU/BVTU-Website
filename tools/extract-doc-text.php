<?php
/**
 * tools/extract-doc-text.php — pull the text out of the PDFs so search can see it.
 *
 * The site's documents were indexed by filename alone. Searching "experience
 * credit" could not find the document called exactly that, and "harassment"
 * found nothing anywhere, because the word lives in the agreement and not on
 * any page. Nearly 600,000 characters of the union's own documents were
 * invisible.
 *
 * This runs on a machine with pdftotext (poppler) and writes the result to
 * documents-text.json, which is committed. The web host has no pdftotext and
 * should not need one: extraction happens once, here, when a document
 * changes — not on every reindex, and not on the server.
 *
 *   php tools/extract-doc-text.php          rebuild documents-text.json
 *   php tools/extract-doc-text.php --check  report what is missing, change nothing
 *
 * reindex.php says so in its log when a PDF has no text here, so a forgotten
 * run shows up rather than quietly shipping an unsearchable document.
 */
$root = dirname(__DIR__);
$out  = $root . '/documents-text.json';
$check = in_array('--check', $argv, true);

/*
 * Public documents only, and deliberately so.
 *
 * members/protected-docs holds things that need a login. Their text would be
 * written into documents-text.json, which sits in the web root and is served
 * to anyone who asks for it — the whole point of serve-doc.php is that those
 * files are not. It would also never have worked: those PDFs are gitignored
 * and only exist on the server, which has no pdftotext. A path that cannot
 * work and would leak if it did is not worth keeping for symmetry.
 *
 * Protected documents stay searchable by title, as they have always been.
 */
$dirs = [
    'documents' => $root . '/documents',
];

exec('command -v pdftotext', $o, $rc);
if ($rc !== 0 && !$check) {
    fwrite(STDERR, "pdftotext not found. Install poppler (brew install poppler) and run again.\n");
    exit(1);
}

$existing = file_exists($out) ? (json_decode(file_get_contents($out), true) ?: []) : [];
$text = [];
$missing = [];

foreach ($dirs as $label => $dir) {
    if (!is_dir($dir)) continue;
    // glob('*.pdf') is case-sensitive but reindex.php lowercases before
    // comparing, so a file named .PDF was indexed and never extracted.
    $pdfs = array_filter(scandir($dir) ?: [],
        fn($f) => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'pdf');
    sort($pdfs);
    foreach ($pdfs as $pdfName) {
        $pdf = $dir . '/' . $pdfName;
        $key = $label . '/' . basename($pdf);
        if ($check) {
            if (!isset($existing[$key]) || trim((string)$existing[$key]) === '') $missing[] = $key;
            continue;
        }
        $cmd = 'pdftotext -q -enc UTF-8 ' . escapeshellarg($pdf) . ' -';
        $raw = shell_exec($cmd) ?? '';
        // Collapse the layout; the search engine wants words, not columns.
        $raw = preg_replace('/\s+/u', ' ', (string)$raw);
        $raw = trim((string)$raw);
        if ($raw === '') {
            /* A scan with no text layer — or a file this machine could not
             * read, which under OneDrive means a placeholder that has not
             * been downloaded. Either way, whatever was extracted last time
             * is kept: overwriting it with nothing would quietly un-index a
             * document because of a transient local problem. */
            if (trim((string)($existing[$key] ?? '')) !== '') {
                $text[$key] = $existing[$key];
                fwrite(STDERR, "  ! could not read {$key} now — keeping the text from last time\n");
            } else {
                fwrite(STDERR, "  ! no text layer: {$key} (a scan? it will stay unsearchable)\n");
                $missing[] = $key;
            }
            continue;
        }
        $text[$key] = $raw;
        printf("  %-52s %8d chars\n", basename($pdf), mb_strlen($raw));
    }
}

if ($check) {
    if ($missing) {
        echo "PDFs with no extracted text:\n";
        foreach ($missing as $m) echo "  - $m\n";
        echo "Run: php tools/extract-doc-text.php\n";
        exit(1);
    }
    echo "Every PDF has extracted text.\n";
    exit(0);
}

ksort($text);
file_put_contents($out, json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
printf("\n%d documents, %s of text -> documents-text.json\n",
       count($text), number_format(array_sum(array_map('mb_strlen', $text))) . ' chars');
if ($missing) printf("%d could not be read; they stay searchable by title only.\n", count($missing));
