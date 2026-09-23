# BVTU Member Portal — working rules

## Review before committing

**Every commit that changes PHP gets reviewed by a second agent first.**

Run the `code-review` skill over the working diff, fix what it finds, then
commit. Do not commit and then review. Report the review outcome to Cody along
with the change — including when it found nothing.

**Use `low` by default, `medium` at the most.** Low returns the few
high-confidence findings, which is what this rule is for. `medium` is the
ceiling — reach for it only on money, authentication, permissions or schema, and
never go above it. Do not use `high`, `xhigh` or `max`: Cody capped this on
2026-09-23 because the cost is his and the extra findings at high were mostly
comment wording by the end. Never `ultra` — that is billed and Cody's to
trigger.

**Review once, not once per fix.** Fix everything a pass reports, then run at
most one confirming pass. Re-reviewing the whole diff after each individual fix
multiplies the cost for findings that get smaller every round; when they are
down to wording and style, stop and commit.

Why: there is no staging site and no test suite. Hostinger deploys from `main`
on push, so a defective commit is live for the whole membership immediately.
Bugs that have reached production this way include a CSRF token written into
the middle of an HTML attribute, a query that failed silently on a collation
mismatch and so reported "nothing to reconcile", a sort that only reversed its
last ORDER BY term, and a CSS class collision that rendered text invisible.
Each was found by looking again, not by writing more carefully the first time.

Skip the review only for changes that cannot break a page: a comment, a commit
message, a memory file. When in doubt, review.

`/code-review ultra` is Cody's to trigger, never mine — it is billed.

## Verification that has caught real defects here

- `python3 tools/alt-syntax-check.py <file>` before committing any template. PHP
  alternative syntax (`if: … endif;`) is invisible to brace counting, and a bad
  slice took the dashboard down for every member once. (It lived in /tmp and had
  to be rewritten from memory when that was cleared — hence the repo copy.)
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
