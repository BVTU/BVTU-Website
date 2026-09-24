#!/usr/bin/env python3
"""
php-string-check.py — catch a string literal that was closed early.

There is no `php` binary on this machine, so a parse error only shows up when
the page is already live. This checks the one mistake that has actually caused
that here: a double quote inside a double-quoted string (a SQL comment reading
   -- an empty box means "none supplied"
closed the string at the first inner quote, and the whole page fatalled).

The tell is reliable. When a string ends where it was meant to continue, the
words that followed become bare code, so the parser meets an identifier right
after a closing quote. In valid PHP a string literal is always followed by an
operator or punctuation — never by a bare word. `"a" . $b`, `['k' => 1]`,
`f("x")` all pass; `"a comment with "quotes" in it"` does not.

Bracket balance would not catch this: the stray quotes pair up with each other.

    python3 tools/php-string-check.py file.php [more.php ...]
    python3 tools/php-string-check.py --all
"""
import re
import sys
import glob

# A closing quote, optional horizontal space, then a word character. Newlines
# are excluded: a string ending a line is normal, and PHP would not continue a
# statement with a bare word on the next line for any valid reason we write.



def scan(src):
    """
    Walk the file once, tracking PHP-vs-HTML mode and string state together.

    Both have to be tracked in one pass. A `?>` inside a string does not end
    the PHP block (xlsx-writer.php embeds a literal '<?xml ... ?>'), and a
    `<?xml` in HTML is not a PHP open tag — treating either naively produced
    false reports on working files.

    Yields (index_of_closing_quote, quote_char) for each complete string.
    """
    i, n = 0, len(src)
    in_php = False
    while i < n:
        if not in_php:
            m = re.compile(r'<\?(?:php\b|=)').search(src, i)
            if not m:
                return
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
            i = n if j == -1 else j + 2
            continue
        if (c == '/' and i + 1 < n and src[i + 1] == '/') or c == '#':
            # A line comment ends at a newline OR at `?>`, which PHP honours.
            j = src.find('\n', i)
            k = src.find('?>', i)
            if k != -1 and (j == -1 or k < j):
                i = k
                continue
            i = n if j == -1 else j
            continue
        hd = re.match(r"<<<[ \t]*(['\"]?)([A-Za-z_]\w*)\1\r?\n", src[i:])
        if hd:
            tag = hd.group(2)
            i += hd.end()
            m = re.search(r'^[ \t]*' + tag + r'\b', src[i:], re.M)
            i = n if not m else i + m.end()
            continue
        if c in '"\'':
            q = c
            i += 1
            while i < n:
                if src[i] == '\\':
                    i += 2
                    continue
                if src[i] == q:
                    yield i, q
                    i += 1
                    break
                i += 1
            continue
        i += 1


def check(path):
    src = open(path, encoding='utf8', errors='replace').read()
    bad = []
    for end, q in scan(src):
        m = re.match(r'[ \t]*([A-Za-z_]\w*)', src[end + 1:])
        if m:
            bad.append((src.count('\n', 0, end) + 1, q, m.group(1)))
    for line, q, word in bad:
        print('%s:%d: string closed early \u2014 %s-quote is followed by the bare word "%s"'
              % (path, line, 'double' if q == '"' else 'single', word))
    if not bad:
        print('%s: OK' % path)
    return not bad


def main(argv):
    files = argv[1:]
    if files == ['--all'] or not files:
        files = sorted(glob.glob('**/*.php', recursive=True))
    return 0 if all([check(f) for f in files]) else 1


if __name__ == '__main__':
    sys.exit(main(sys.argv))
