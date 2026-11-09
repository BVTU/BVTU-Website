# BVTU Member Portal — working rules

## Review before merging — Codex does it, not me

**Codex handles all code review here.** From 2026-11-09, Cody's instruction:
stop running Claude review agents and the `code-review` skill. Do not invoke
`code-review` at any level, and do not spawn a review subagent. If a skill or
habit suggests one, that is superseded by this section.

**Nothing reaches `main` until Codex has reviewed it.** The sequence is:

1. Build on `test` (see below) and push there — pushing to `test` is deploying
   to staging, so it is still a deploy and still needs the checks in the next
   section.
2. Tell Cody what is on `test` and that it is ready for Codex.
3. **Wait.** Do not merge to `main`, even when asked to "merge", until Codex's
   review has come back and whatever it raises has been dealt with.
4. Fix what Codex finds, push to `test` again, and say what changed.

If Cody asks for a merge before that review has happened, say so and ask him to
confirm rather than merging on the assumption he has forgotten — he may have
reviewed it outside this session, in which case his go-ahead is the answer.

**What I still do myself, every time, unchanged:**

- `python3 tools/php-check.py` before any PHP commit — see the next section.
- The test harnesses, and new assertions for anything new.
- Reading my own diff before committing, and saying plainly what I changed and
  what I could not verify.

These are implementation checks, not review. Dropping them because somebody
else reviews the code would be reading the instruction backwards: Codex reviews
what I have already checked, not instead of my checking it.

**Why the branch rule still matters.** Hostinger deploys `main` on push, so a
defective commit is live for the whole membership immediately, and staging only
helps with what is visible on a page. Bugs that have reached production this way
include a CSRF token written into the middle of an HTML attribute, a query that
failed silently on a collation mismatch and so reported "nothing to reconcile",
a sort that only reversed its last ORDER BY term, and a CSS class collision that
rendered text invisible. Each was found by looking again, not by writing more
carefully the first time — which is the whole reason a second reader exists,
whoever it is.

## Verification that has caught real defects here

- **`python3 tools/php-check.py <file>` before committing any PHP.** This is the
  one that matters. It runs `php -l`, a PHP 7.4 guard, and the alt-syntax check
  together. A double quote inside a double-quoted SQL string once closed it
  early and fatalled the Link Shortener page for real users — no amount of
  bracket counting sees that, only a parser does.
  - `php` is a standalone **8.0.30** build at `~/.local/bin/php`, put on PATH by
    `~/.zprofile`. Homebrew could not install one (its directories need a `sudo
    chown`), and 7.4 is not available prebuilt for this Mac.
  - **The host runs 7.4, the local php is 8.0**, so `php -l` alone would accept
    syntax that fatals in production. That is what the guard in php-check.py is
    for: `?->`, `match()`, `str_starts_with()`, attributes, promoted
    constructor properties, `catch` with no variable. It reads PHP regions only,
    so JavaScript in a `<script>` block is not mistaken for PHP.
  - The alt-syntax check it wraps exists because PHP alternative syntax
    (`if: … endif;`) is invisible to brace counting, and a bad slice once took
    the dashboard down for every member.
- `node --check` on JavaScript extracted from a `<script>` block.
- PHP is **7.4**. `str_starts_with()` and other PHP 8 functions are fatal.
- Never compare two string columns from different tables in SQL. `members`,
  `member_invitations` and `contacts` can carry different collations; join them
  in PHP on the normalised email instead.
- Never wrap a column in `LOWER()`/`DATE_FORMAT()` and compare it to a bound
  parameter — same collation failure, and it discards the index.
- Check new CSS class names against `css/style.css` before using them. The
  public site stylesheet is loaded on every member page. Page-local panels use
  `.pcard`, never `.card`.
- Never rewrite a file with a broad regex that spans lines. One `.*?` across a
  docblock deleted the entire header — requires, auth gate and POST handlers —
  from four files at once. Anchor on exact strings instead.
