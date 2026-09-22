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
    """Blank out comments and string literals inside one PHP region."""
    code = re.sub(r'/\*.*?\*/', ' ', code, flags=re.S)
    code = re.sub(r'(?m)//[^\n]*$', ' ', code)
    code = re.sub(r'#[^\n]*', ' ', code)
    code = re.sub(r'"(?:[^"\\]|\\.)*"', '""', code)
    code = re.sub(r"'(?:[^'\\]|\\.)*'", "''", code)
    return code

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
