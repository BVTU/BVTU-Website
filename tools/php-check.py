#!/usr/bin/env python3
"""
php-check.py — the pre-commit check for PHP in this repo.

Runs three things over each file:

  1. `php -l`, the real parser. This is the one that matters: it catches the
     parse errors that otherwise reach production, like a double quote inside
     a double-quoted SQL string closing it early.
  2. a PHP 7.4 guard. The local php is 8.0 (7.4 is not available prebuilt for
     this Mac), so `php -l` happily accepts syntax the Hostinger host rejects
     with a fatal. These greps cover what 8.0 added.
  3. tools/alt-syntax-check.py, for if/endif balance in templates.

    python3 tools/php-check.py <file> [more...]
    python3 tools/php-check.py --all

`php` comes from ~/.local/bin (a standalone static build); ~/.zprofile puts it
on PATH. If it is missing this script says so and still runs 2 and 3 rather
than passing silently.
"""
import os
import re
import subprocess
import sys
import glob

HERE = os.path.dirname(os.path.abspath(__file__))

# Syntax and functions PHP 8.0 accepts but 7.4 fatals on. Each is (regex, why).
# Patterns run against code with strings and comments blanked out.
PHP8_ONLY = [
    (r'\?->',                             'nullsafe operator ?-> is PHP 8.0'),
    (r'(?<![\w$])match\s*\(',             'match expression is PHP 8.0'),
    (r'(?<![\w$])str_contains\s*\(',      'str_contains() is PHP 8.0'),
    (r'(?<![\w$])str_starts_with\s*\(',   'str_starts_with() is PHP 8.0'),
    (r'(?<![\w$])str_ends_with\s*\(',     'str_ends_with() is PHP 8.0'),
    (r'(?<![\w$])get_debug_type\s*\(',    'get_debug_type() is PHP 8.0'),
    (r'(?<![\w$])array_is_list\s*\(',     'array_is_list() is PHP 8.1'),
    (r'(?<![\w$])enum\s+\w+',             'enums are PHP 8.1'),
    (r'(?<![\w$])readonly\s+',            'readonly is PHP 8.1'),
    (r'#\[',                              'attributes #[...] are PHP 8.0'),
    (r'catch\s*\(\s*[\\\w|]+\s*\)',       'catch without a variable is PHP 8.0'),
    (r'function\s+__construct\s*\([^)]*\b(?:public|private|protected)\b',
     'constructor property promotion is PHP 8.0'),
    (r':\s*(?:static|mixed|never)\s*(?:\{|;)',
     'static/mixed/never return types are PHP 8.0+'),
]


def blank_literals(src):
    """
    Blank everything that is not PHP code, keeping offsets so line numbers
    stay right: HTML, the JavaScript in <script> blocks, and PHP's own string
    and comment bodies. Without the HTML/JS part the guard reported JavaScript
    `match(`, `catch {` and CSS `: static;` as PHP 8 syntax in ten files.
    """
    out = list(src)
    i, n = 0, len(src)
    in_php = False
    while i < n:
        if not in_php:
            m = re.compile(r'<\?(?:php\b|=)').search(src, i)
            stop = m.start() if m else n
            for k in range(i, stop):
                if out[k] != '\n':
                    out[k] = ' '
            if not m:
                break
            i = m.end()
            in_php = True
            continue
        c = src[i]
        if c == '?' and i + 1 < n and src[i + 1] == '>':
            in_php = False
            i += 2
            continue
        if c == '/' and i + 1 < n and src[i + 1] == '*':
            j = src.find('*/', i + 2)
            j = n if j == -1 else j + 2
        elif (c == '/' and i + 1 < n and src[i + 1] == '/') or c == '#':
            # `#[` is an attribute, not a comment — leave it for the guard.
            if c == '#' and i + 1 < n and src[i + 1] == '[':
                i += 1
                continue
            j = src.find('\n', i)
            j = n if j == -1 else j
        elif c in '"\'':
            q, j = c, i + 1
            while j < n:
                if src[j] == '\\':
                    j += 2
                    continue
                if src[j] == q:
                    j += 1
                    break
                j += 1
        else:
            i += 1
            continue
        for k in range(i, min(j, n)):
            if out[k] != '\n':
                out[k] = ' '
        i = j
    return ''.join(out)


def check(path, have_php):
    problems = []

    if have_php:
        r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
        if r.returncode != 0:
            msg = (r.stdout + r.stderr).strip().splitlines()
            problems.append('  ' + (msg[0] if msg else 'parse error'))

    code = blank_literals(open(path, encoding='utf8', errors='replace').read())
    for pat, why in PHP8_ONLY:
        for m in re.finditer(pat, code):
            problems.append('  line %d: %s' % (code.count('\n', 0, m.start()) + 1, why))

    r = subprocess.run([sys.executable, os.path.join(HERE, 'alt-syntax-check.py'), path],
                       capture_output=True, text=True)
    if r.returncode != 0 or 'OK' not in r.stdout:
        problems.append('  ' + r.stdout.strip())

    if problems:
        print('%s:' % path)
        for p in problems:
            print(p)
        return False
    return True


def main(argv):
    files = [a for a in argv[1:] if a != '--all']
    if '--all' in argv or not files:
        files = [f for f in sorted(glob.glob('**/*.php', recursive=True))
                 if not f.startswith('members/lib/')]

    have_php = subprocess.run(['which', 'php'], capture_output=True).returncode == 0
    if not have_php:
        print('WARNING: no `php` on PATH — skipping `php -l`, the check that matters.')
        print('         Expected ~/.local/bin/php; see CLAUDE.md.')

    bad = [f for f in files if not check(f, have_php)]
    print('\n%d file(s) checked, %d with problems.' % (len(files), len(bad)))
    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv))
