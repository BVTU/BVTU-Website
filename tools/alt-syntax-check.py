#!/usr/bin/env python3
"""PHP alternative-syntax balance checker.

Brace counting cannot see `if (...): ... endif;`, which every template in
members/ uses. A slice that drops an opening or closing keyword leaves a parse
error that looks fine to any brace-based check — that is how the member
dashboard went down for everyone once.

Only PHP regions are examined. Apostrophes in ordinary HTML text ("President's
expense vouchers") are not string delimiters, and treating them as such
destroys the structure and invents failures.
"""
import re, sys

OPEN = ('if', 'foreach', 'for', 'while', 'switch')
CLOSE = {'endif', 'endforeach', 'endfor', 'endwhile', 'endswitch'}
KEYWORDS = re.compile(r'\b(' + '|'.join(OPEN) + '|' + '|'.join(CLOSE) + r')\b')

def php_regions(src):
    """Yield (offset, text) for each <?php … ?> region."""
    for m in re.finditer(r'<\?(?:php|=)?(.*?)(?:\?>|$)', src, re.S):
        yield m.start(1), m.group(1)

def scrub(code):
    """
    Blank out comments and string literals inside one PHP region.

    One pass, not a sequence of regexes. Blanking comments first breaks on a
    string holding a '#' or a '//' — a literal '#' used to build an invoice
    label blanked the rest of its line, which threw off every quote after it
    and let prose inside a later string be read as code. "for invoice(s):" then
    counted as an alternative-syntax for(...): and the file was reported
    unbalanced. Blanking strings first breaks the mirror case, a comment
    containing an apostrophe. Only tracking both together is correct.

    Newlines are preserved so offsets, and therefore reported line numbers,
    stay right.
    """
    out = list(code)
    i, n = 0, len(code)

    def blank(a, b):
        for k in range(a, min(b, n)):
            if out[k] != '\n':
                out[k] = ' '

    while i < n:
        c = code[i]
        if c == '/' and i + 1 < n and code[i + 1] == '*':
            j = code.find('*/', i + 2)
            j = n if j == -1 else j + 2
            blank(i, j); i = j; continue
        if (c == '/' and i + 1 < n and code[i + 1] == '/') or c == '#':
            j = code.find('\n', i)
            j = n if j == -1 else j
            blank(i, j); i = j; continue
        if c == '<' and code[i:i + 3] == '<<<':
            m = re.match(r"<<<[ \t]*(['\"]?)([A-Za-z_]\w*)\1\r?\n", code[i:])
            if m:
                tag = m.group(2)
                end = re.search(r'^[ \t]*' + tag + r'\b', code[i + m.end():], re.M)
                j = n if not end else i + m.end() + end.end()
                blank(i, j); i = j; continue
        if c in '"\'':
            q, j = c, i + 1
            while j < n:
                if code[j] == '\\':
                    j += 2; continue
                if code[j] == q:
                    j += 1; break
                j += 1
            # Blank the whole literal, quotes included. On an unterminated
            # string j lands at n, and trimming the ends there left the final
            # character live — enough for a stray '(' or ':' to be counted.
            blank(i, j); i = j; continue
        i += 1
    return ''.join(out)

def check(path):
    src = open(path, encoding='utf-8').read()
    depth = 0
    for base, region in php_regions(src):
        code = scrub(region)
        for m in KEYWORDS.finditer(code):
            word = m.group(1)
            if word in CLOSE:
                depth -= 1
                if depth < 0:
                    print('%s: extra %s near offset %d' % (path, word, base + m.start()))
                    return 1
                continue
            i = code.find('(', m.end())
            if i == -1:
                continue
            level, j = 0, i
            while j < len(code):
                if code[j] == '(':
                    level += 1
                elif code[j] == ')':
                    level -= 1
                    if level == 0:
                        break
                j += 1
            if code[j + 1:].lstrip()[:1] == ':':
                depth += 1
    if depth != 0:
        print('%s: UNBALANCED, depth=%d' % (path, depth))
        return 1
    print('%s: depth=0 OK' % path)
    return 0

sys.exit(max(check(p) for p in sys.argv[1:]))
