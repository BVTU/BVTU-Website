# BVTU Website — Information Architecture Audit

**Audited:** 6 November 2026
**Codebase:** `bvtu-new-site` (PHP 7.4 on Hostinger; `main` deploys to bvtu.ca, `test` to bvtu.ca/test)
**Scope:** audit only. No site code was changed.

Companion file: **`site-inventory.csv`** — one row per destination, 109 rows.

---

## 0. How the site is put together (for a reader new to it)

The BVTU is a teachers' union local in northwest British Columbia representing
educators in Houston, Telkwa and Smithers. The site serves four quite different
groups from one codebase:

| Group | What they need |
|---|---|
| **Public / prospective members** | Who the union is, trustee election material |
| **Teachers and TTOCs** | The contract, pay, benefits, Pro-D, booking time off, resources |
| **Union leadership** (President, Treasurer, VP, exec) | Expense approvals, grants, contacts, events |
| **Site administrators** | Email templates, analytics, OneDrive, short links |

There are two distinct environments, and the redesign must keep them apart:

1. **The public site** — everything at the web root (`/about.php`, `/salary.php`…).
   It uses one shared header from **`inc/nav.php`** and one footer from
   **`inc/footer.php`**. The header is generated from a single PHP array, so the
   whole public navigation tree is declared in one place.
2. **The member portal** — everything under **`/members/`**. It does **not** use
   the public navigation tree. Its entry point is **`/members/dashboard.php`**, a
   page of tiles, and almost all portal navigation is tiles and back-links rather
   than a menu.

A third thing sits inside the portal and is worth naming separately because the
brief asks for it: the **LP administrative dashboard**
(`/members/lp-dashboard.php`, labelled **"Expenses & Grants"**). "LP" is the
BCTF's *Local Presidents'* grant funding. This is the President's and
Treasurer's financial area — budgets, vouchers, signatures, payments, the
release-time claim. It has its own internal navigation and is reached from one
tile on the member dashboard.

### Counts

| | Count |
|---|---|
| Unique destinations inventoried | **109** |
| Public pages at the web root | 54 PHP files, all 54 inventoried (several are endpoints rather than pages — see §7) |
| Pages under `/members/` | 154 PHP files, of which **55 are inventoried as destinations**; the rest are libraries, includes and AJAX endpoints |
| Top-level navigation items | **8** |
| Dropdown entries | **28** (across 5 of the 8 items; 30 when signed in) |
| Footer navigation links | **8** (the same 8 top-level items) + Contact Us + Privacy + a Facebook button |
| Homepage internal links | **16** distinct pages |
| Destinations requiring a login | **49** (11 login only, 33 login + role, 5 login via redirect) |
| Destinations with no inbound link anywhere | **11** (see §7) |

---

## 1. Full current navigation tree

### 1.1 Public header (`inc/nav.php`)

Labels are reproduced exactly, including the one-line descriptions that appear
under each dropdown entry. **↗** marks an external link that opens in a new tab.

```
Logo ──────────────────────────── index.php
🔍 Search button ──────────────── opens site search overlay

About ─────────────────────────── about.php          (no dropdown)

Documents ─────────────────────── documents.php
  ├ All Documents ─────────────── documents.php          "Everything we hold, in one searchable list"
  ├ Collective Agreement ──────── collective-agreement.php  "Your contract with School District 54"
  ├ Side agreements (LOUs) ────── lous.php               "What has been agreed since the contract was signed"
  ├ Contract Assistant ────────── ca-assistant.php       "Ask a question, get the article that answers it"
  ├ Constitution & Bylaws ↗ ───── documents/BVTU-Constitution-and-Bylaws-2026.pdf  "How the local is run"
  ├ School Calendars ──────────── calendars.php          "SD54 instructional days and Pro-D days"
  ├ Trustee Zone Map ──────────── trustee-zones.php      "Which zone you vote in"
  └ Trustee Candidate Responses ─ trustee-candidates.php "What the candidates told us"

Members ───────────────────────── members.php
  ├ Member Resources ──────────── members.php            "Everything for members, in one place"
  ├ Salary Grids ──────────────── salary.php             "What you are paid, and how you move up"
  ├ Health & Dental ───────────── benefits.php           "Pacific Blue Cross — what is covered, and how to claim"
  ├ Life Insurance ────────────── life-insurance.php     "Your coverage, beneficiaries and conversion rights"
  ├ Student Loan Forgiveness ──── loan-forgiveness.php   "Up to $30,000 forgiven for teaching in the north"
  ├ Deferred Salary Leave Plan ── deferred-salary.php    "Save part of your pay now and take a year off later"
  ├ Booking time off ──────────── atrieve.php            "Release time and absences, and how to enter them in Atrieve"
  ├ Class size & remedy ───────── remedy-tracker.php     "When your class is over size — work out what you are owed"
  ├ Collaboration Grant ───────── collab-grant.php       "Release days to plan with a colleague"
  ├ Training & workshops ──────── events.php             "SURTs and other sessions you can sign up for"
  ├ What's On ─────────────────── whats-on.php           "Union events, meetings and deadlines on one calendar"
  ├ TTOC Resources ────────────── ttoc.php               "Pay, seniority and call-out for teachers on call"
  ├ My Dashboard ──────────────── members/dashboard.php  (only when signed in)
  └ Sign Out ──────────────────── members/logout.php     (only when signed in)

PRO-D ─────────────────────────── prod.php            (no dropdown)

Health & Safety ───────────────── health-safety.php
  ├ Health & Safety ───────────── health-safety.php      "Committees, reporting, and your right to refuse unsafe work"
  ├ WorkSafe BC ↗ ─────────────── https://www.worksafebc.com   "Claims and workplace injury"
  └ Counselling & support ↗ ───── https://sd54.lifeworks.com/  "Free and confidential, through the employee assistance plan"

BCTF ──────────────────────────── bctf.php
  ├ BCTF Resources ────────────── bctf.php               "What the provincial federation offers members"
  ├ BCTF Website ↗ ────────────── https://bctf.ca        "bctf.ca"
  └ Benefits & Discounts ↗ ────── https://www.bctf.ca/topics/.../view-member-discounts-bctf-advantage  "Member discounts through BCTF Advantage"

Resources ─────────────────────── library.php
  ├ Resource Library ──────────── library.php            "Lesson plans, units and rubrics shared by BVTU members"
  └ Curated Resources ─────────── curated.php            "Picked out by the BVTU"

Newsletters ───────────────────── newsletter-archive.php  (no dropdown)

[ Member Login ] ──────────────── members/login.php     (button; becomes "My Dashboard" when signed in)
```

**Mechanics worth knowing for a redesign:**

- The tree is one PHP array (`bvtuNavItems()`), so adding, moving or renaming an
  item is a single-file change. Labels and descriptions are editorial text in
  that array.
- Which section highlights as "active" is derived from a second map
  (`bvtuNavSection()`), which lists filenames per section. **A page not listed
  there lights up no section** — e.g. `curated.php` is in the Resources dropdown
  and *is* mapped, but `forum.php`, `search.php` and `privacy.php` belong to no
  section at all.
- A dropdown with more than 6 children gets a `dropdown-wide` two-column class.
  Only **Members** (12–14 entries) triggers it.
- Dropdowns are CSS hover menus hidden below 1280px; the script that opens them
  on tap only runs below 681px. **Between 681px and 1280px no dropdown child is
  reachable from the header at all** — the footer and the section landing pages
  are the only routes. This is documented in the footer source as the reason the
  footer is flat.

### 1.2 Public footer (`inc/footer.php`)

```
Column 1  Bulkley Valley Teachers' Union — descriptive text only, no links
Column 2  Contact — President: Cody Lind, postal address, → contact.php
Column 3  Navigate — a FLAT list of the 8 top-level items only (no children)
Column 4  Connect — [Facebook Group] button → href="#"   ⚠ dead placeholder
Small print  … · Privacy → privacy.php
```

### 1.3 Homepage (`index.php`)

The homepage links to 16 internal pages. It is not a reduced copy of the
navigation — it mixes sections:

```
members.php ×2, trustee-zones.php ×2, forum.php ×2, whats-on.php,
trustee-candidates.php, salary.php, prod.php, library.php, health-safety.php,
documents.php, collective-agreement.php, collab-grant.php, benefits.php,
bctf.php, about.php, event.php?s=…  (the next event)

External: bctf.ca, sd54.bc.ca, bcteacherregulation.ca, bctp.ca, tqs.ca,
gov.bc.ca K-12, and a YouTube embed
```

### 1.4 Member portal — dashboard (`/members/dashboard.php`)

Tiles, in page order. Visibility depends on role.

```
(top bar)      Sign Out
Expenses       → lp-dashboard.php        "Expenses & Grants"   [leadership]
               → lp-voucher-new.php
               → exp-dashboard.php       "My Expense Submissions"
               → exp-submit.php          "Submit New Expense"
               → approvals.php           "Approvals & Payments" [signers]
Newsletter Archive  → newsletter-admin.php   "Sync from Mailchimp (Admin)"
Resource Library    → library-admin.php      "Library Admin Panel"
My Collaboration Grant  (status of the member's own application)
Collaboration Grant → collab-grant-admin.php "Review Applications & Export CSV"
My Account     → my-account.php          "My Profile"
               → change-password.php     "Change Password"
Administration → people.php              "People"
               → roles-overview.php      "Roles & Directory"
               → email-templates.php     "Email"
               → bctf-forms.php          (BCTF forms)
               → onedrive.php            "OneDrive Doc Upload"
               → event-admin.php         (events)
               → links-manage.php        (short links / QR)
               → forum-admin.php         "Forum Recording"
               → analytics.php           "Site Analytics"
               → token-usage.php         "Claude API Token Usage"
               → logout.php              "Sign out"
```

### 1.5 LP administrative dashboard (`/members/lp-dashboard.php`)

Reached from one tile. Its own internal navigation:

```
← dashboard.php                     (back to the member dashboard)
  lp-voucher-new.php                create a voucher
  lp-voucher-view.php?id=…          one voucher (from the list)
  lp-grants-manage.php              edit grant budgets and budget lines
  lp-releasetime.php                Release Time Grant — days, invoices, BCTF claim
  lp-archive.php?year=…             a closed year, read-only
  lp-year-end.php                   close off a school year
  lp-receipt.php?f=…                a receipt image
  Grant cards ×N                    (from the database; the "Local Release Time
                                     Grant" card links to lp-releasetime.php,
                                     the others open a transactions modal)
```

---

## 2. Existing landing pages

A "landing page" here means a page whose job is to route onward.

| Landing page | Sits under | What is on it | Relationship to the navigation |
|---|---|---|---|
| **`index.php`** Home | — | Hero, quick links, next event, union news, external bodies | Mixes items from six different nav sections |
| **`documents.php`** All Documents | Documents | Searchable index. Static links to collective-agreement, lous, ca-assistant, the Constitution PDF, calendars, **deferred-salary** | **Duplicates 5 of the 8 Documents dropdown entries.** The dropdown and the page are two routes to the same six places |
| **`members.php`** Member Resources | Members | Links to benefits, life-insurance, loan-forgiveness, deferred-salary, ttoc, atrieve, remedy-tracker, salary, whats-on, events, collab-grant | **Duplicates 11 of the 12 Members dropdown entries** — the dropdown is this page in menu form |
| **`prod.php`** PRO-D | PRO-D | Pro-D funding and process; links to collab-grant and two external sites | A content page, not a router. Has **no dropdown**, so it is the only route into Pro-D |
| **`health-safety.php`** Health & Safety | Health & Safety | Committees, reporting, right to refuse; links to contact and four external resources | Dropdown has 3 entries, 1 of which is this page itself |
| **`bctf.php`** BCTF Resources | BCTF | What the federation offers; the provincial agreement PDF; links to bctf.ca sections | Dropdown has 3 entries, 1 of which is this page itself |
| **`library.php`** Resource Library | Resources | Search and browse member-uploaded resources (**database driven**) | Dropdown has 2 entries, 1 of which is this page itself |
| **`curated.php`** Curated Resources | Resources | Outside resources picked by the BVTU (**database driven**) | Second Resources dropdown entry |
| **`newsletter-archive.php`** Newsletters | Newsletters | Archive of issues (**database driven**, synced from Mailchimp) | Top-level, no dropdown |
| **`members/dashboard.php`** My Dashboard | Member portal | Tiles, role-dependent | The portal's only hub; not part of the public tree |
| **`members/lp-dashboard.php`** Expenses & Grants | LP admin | Budget, grant cards, vouchers, release time | Reached only from one dashboard tile |

**Five of the eight top-level items point at a page that is also the first entry
in their own dropdown** (Documents, Members, Health & Safety, BCTF, Resources).
Clicking the parent and clicking the first child go to the same place.

---

## 3. Duplicate and overlapping destinations

### Confirmed duplicates

| Destination | Appears in | Note |
|---|---|---|
| `documents.php` | Documents top-level **and** "All Documents" first dropdown entry | Same URL twice in the same menu |
| `members.php` | Members top-level **and** "Member Resources" first dropdown entry | Same URL twice in the same menu |
| `health-safety.php` | Health & Safety top-level **and** first dropdown entry | Same URL twice in the same menu |
| `bctf.php` | BCTF top-level **and** "BCTF Resources" first dropdown entry | Same URL twice in the same menu |
| `library.php` | Resources top-level **and** "Resource Library" first dropdown entry | Same URL twice in the same menu |
| `deferred-salary.php` | **Members dropdown** and the **Documents landing page** | The only item that crosses two top-level sections |
| `collab-grant.php` | Members dropdown, **PRO-D page**, homepage | Three routes, from two different sections |
| `whats-on.php` / `events.php` | Both in the Members dropdown, and they cross-link | Two calendars of union activity; the distinction ("Training & workshops" vs "What's On") is ours, not obviously the member's |
| `trustee-zones.php`, `trustee-candidates.php` | Documents dropdown **and** homepage ×2 **and** each other **and** `forum.php` | Election material filed under "Documents" |
| `members/dashboard.php` | Header button **and** Members dropdown ("My Dashboard") | Both only when signed in |
| `members/logout.php` | Members dropdown **and** two places on the dashboard | — |

### Overlapping categories (confirmed, judgement about the fix)

- **"Documents" vs "Members" vs "Resources"** — all three hold things a teacher
  reads. Documents = union/contract paperwork, Members = entitlements and tools,
  Resources = classroom material. The names do not say that.
- **"Resources"** is one word covering *member-uploaded classroom material*
  (`library.php`) and *BVTU-picked outside links* (`curated.php`), while
  "Member Resources" (`members.php`) is a different section entirely, and
  "BCTF Resources" (`bctf.php`) a third. **Four things called "resources".**
- **Election material** (trustee zones, candidate responses, the all-candidates
  forum recording) is spread across Documents, the homepage and an unfiled page
  (`forum.php`).
- **Expenses** exist in three parallel systems in the portal — member claims
  (`exp-*`), President's vouchers (`lp-*`) and Pro-D claims (`prod-*`) — each
  with its own dashboard, submit form, receipt handling and admin screens.

---

## 4. Content that is hard to categorise

| Page | Problem |
|---|---|
| `forum.php` — All-Candidates Forum | Linked from the homepage and two other pages, in **no navigation section**, highlights nothing in the header. Election content with no home |
| `atrieve.php` — "Booking time off" | Under Members, but it is a how-to for an **employer** system (SD54's Atrieve), and is also the practical next step after a Collaboration Grant or a Pro-D day is approved |
| `deferred-salary.php` | A benefit (Members) that is also a set of agreement documents (Documents). Currently in both |
| `remedy-tracker.php` — "Class size & remedy" | An interactive calculator sitting in a list of reading material |
| `collab-grant.php` | A public application form that lives under Members, is advertised on PRO-D, and whose admin half is in the portal |
| `calendars.php` | SD54 instructional calendars filed under union Documents |
| `search.php`, `privacy.php` | In no section; reachable only from the header magnifier and the footer small print |
| `bctf-forms.php` | A **portal** page mapped to the **public** BCTF nav section in `bvtuNavSection()`, though it is not in the public menu |
| `members/treasurer-ledger.php` | Finished but deliberately unlinked, carrying a "paused" banner |

---

## 5. Audience and permission distinctions

Five observed gates:

| Gate | Count | Meaning |
|---|---|---|
| **Public** | 21 | No session touched |
| **Public (calls isLoggedIn)** | 39 | Public content. Most call `isLoggedIn()` only so the header shows "My Dashboard" instead of "Member Login"; some also reveal a member-only button (e.g. "Share a resource" on `library.php`) or prefill a form (`collab-grant.php`). **Verify page by page before treating any of these as member-only** |
| **Login (redirect)** | 5 | Public URL that bounces a signed-out visitor to the login page — `library-upload.php`, `library-saved.php`, `library-edit.php`, `newsletter-archive.php` |
| **Login** | 11 | `requireLogin()` with no role test |
| **Login + role** | 33 | `requireLogin()` plus one of `execIsAdmin`, `cgIsAdmin`, `lpCanView`, `expIsAdmin`, `prodIsExec` |

Points for the redesign:

- **`newsletter-archive.php` is in the public navigation but requires a login.**
  A signed-out visitor clicking "Newsletters" in the header is redirected to the
  login page. Confirmed from the code.
- **`whats-on.php` is the only root page calling `requireLogin()` outright**, and
  it too is in the public Members dropdown and on the homepage.
- Role names in the code: President (`expIsPresident`), Treasurer
  (`expIsTreasurerRole`), Vice-President (`expIsVP`), plus a broader
  `execIsAdmin`. Several admin tiles are visible only to `execIsAdmin`.
- The public site and the portal **do not share navigation**. A member deep in
  `/members/` gets the public header (since `nav.php` is shared) but the portal's
  own areas are not represented in it at all.

---

## 6. Potential information architecture issues

Separated into what the code proves and what is a judgement call.

### Confirmed from the code

1. **The 681–1280px dead zone.** Dropdown children are unreachable from the
   header at tablet widths. 28 of the 36 header destinations are children.
2. **The footer "Facebook Group" button is `href="#"`** — verified, goes nowhere.
3. **The Members dropdown holds 12 entries (14 when signed in)** and is the only
   one wide enough to trigger the two-column class.
4. **Five of eight top-level items duplicate their own first dropdown child.**
5. **`newsletter-archive.php` is public-menu but login-walled.**
6. **Three section landing pages are near-copies of their dropdown**
   (`documents.php`, `members.php`), so the same list is maintained twice — in
   `inc/nav.php` and in the page body — and they have already drifted:
   `documents.php` links `deferred-salary.php`, which the Documents dropdown
   does not list.
7. **`forum.php`, `search.php`, `privacy.php` belong to no nav section**, so no
   header item highlights when a visitor is on them.
8. **Eleven destinations have no inbound link** from anywhere in the codebase
   (see §7).
9. **PRO-D has no dropdown**, unlike every other content section, so Pro-D
   sub-topics have no header presence.

### Possible issues (judgement, not proven by code)

1. **"Resources" is used for four different things** (see §3).
2. **Two event calendars** (`events.php`, `whats-on.php`) presented as peers.
3. **The member dashboard mixes personal and administrative tiles** in one list —
   "My Profile" sits in the same page as "Claude API Token Usage".
4. **Three parallel expense systems** surface as separate tiles with similar
   names ("My Expense Submissions", "Expenses & Grants", "Approvals & Payments").
5. **Trustee election material under "Documents"** is likely a timing artefact.
6. **Nothing in the public header signals the portal's contents** beyond the
   single Login/Dashboard button.

---

## 7. Orphans — destinations with no inbound link

Found by scanning every `href`, `action` and `Location:` redirect in the
codebase. **Being unlinked is not always a fault** — several are reached by a
token in an email, a QR code, or a background script.

### Unlinked and apparently retired (confirmed by their own file comments)

| File | Comment in the file |
|---|---|
| `members/invite-manage.php` | "superseded by the Invitations tab on member-manage.php" |
| `members/exec-manage.php` | "Redirects to roles-overview.php" |
| `members/lp-review.php` | "merged into approvals.php" |
| `members/exp-claim-review.php` | "merged into approvals.php" |

### Unlinked by design — reached by token, QR code or script

`collab-not-me.php` (link in a collaborator email), `voucher.php` +
`voucher-receipt.php` (share link), `members/register.php` and
`members/invite-register.php` and `members/reset-password.php` (emailed tokens),
`members/*-mobile-receipt.php` and `members/*-mobile-scan.php` (QR code from a
desktop screen to a phone), `members/*-poll-receipt*.php` (polled by JavaScript),
`members/newsletter-serve.php`, `members/serve-doc.php` (file streaming).

### Unlinked, status unclear — worth a decision

| File | Note |
|---|---|
| `members/treasurer-ledger.php` | Complete, carries a "paused" banner, deliberately unlinked |
| `members/camera-test.php` | Developer utility |
| `members/status.php` | Health check |
| `members/test-email.php` | Admin utility, not on the dashboard |
| `members/contacts.php` | Older contact list; `people.php` appears to have replaced it |
| `members/prod-dashboard.php`, `members/prod-admin.php` | A whole Pro-D claims subsystem with no tile on the member dashboard |
| `members/exp-admin.php`, `members/exp-treasurer.php` | Expense administration screens with no dashboard tile |

> **`prod-*` and `exp-admin`/`exp-treasurer` are the most significant finding
> here:** substantial working subsystems that the dashboard does not advertise.
> They may be reached by direct URL or from inside other pages; confirm with the
> President before treating them as dead.

### Excluded from the inventory

Roughly 45 files under `/members/` are **libraries and includes**, not
destinations: `*-db.php`, `smtp.php`, `xlsx-writer.php`, `scan-concerns.php`,
`auth.php`, `mailchimp.php`, `lp-review-card.php`, `email-admin-nav.php` and
similar. They define functions and render nothing.

---

## 8. External links — verified

Every external URL in the navigation and on the section landing pages was
requested on 6 November 2026.

| URL | Result |
|---|---|
| `https://www.worksafebc.com` | **200** |
| `https://www.worksafebc.com/en/health-safety/hazards-exposures/violence` | **200** |
| `https://sd54.lifeworks.com/` | **200** |
| `https://bctf.ca` | **200** |
| `https://www.bctf.ca/topics/.../view-member-discounts-bctf-advantage` | **200** |
| `https://bctf.ca/pro-d` | **200** |
| `https://bctf.ca/social-justice` | **200** |
| `https://www.bctf.ca/topics/services-information/professional-development` | **200** |
| `https://bctf.ca/member-services/health-and-wellness` | **200** |
| `https://www.sd54.bc.ca` | **200** |
| `https://www.sd54.bc.ca/.../Understanding-your-Employee-Assistance-Program-Aug-2025.pdf` | **200** |
| `https://www.bcteacherregulation.ca`, `https://www.bctp.ca`, `https://www.tqs.ca` | **200** |
| `https://www2.gov.bc.ca/gov/content/education-training/k-12` | **200** |
| `https://bvsd54.sharepoint.com/sites/SD54ProDCalendar/...` (PRO-D page) | **401** — SD54 SharePoint, requires an SD54 sign-in. Not broken, but **not reachable for a member who is not signed in to SD54**, and it is presented as an ordinary link |
| Footer "Facebook Group" | **`href="#"` — dead placeholder** |

---

## 9. Content held in a database and not inventoried here

These pages are shells; their content lives in MySQL and **cannot be listed from
the source code**. A complete content inventory needs a database export.

| Area | Tables | What is in them |
|---|---|---|
| Documents index | *(reads the `documents/` folder and `documents-text.json`)* | 12 files plus `calendars/`, `salary-grids/`, `settlements/` subfolders |
| Resource Library | `library_resources`, `library_resource_files`, `library_bookmarks`, `library_ratings`, `library_flags` | Member-uploaded lesson plans, units, rubrics |
| Curated Resources | `curated_resources`, `curated_curators` | Outside links chosen by the BVTU |
| Newsletters | `newsletters` | Issues synced from Mailchimp |
| Events | `ev_events`, `ev_documents`, `ev_questions`, `ev_registrations`, `ev_sends` | Everything behind `events.php`, `whats-on.php`, `event.php` |
| Contacts / people | `contacts`, `contact_emails`, `contact_audit`, `exec_roles`, `member_invitations` | The contact list and who holds which role |
| Expenses (3 systems) | `exp_*`, `lp_*`, `prod_*` | Claims, vouchers, grants, budgets, receipts, release time |
| Collaboration Grant | `collab_grant_applications`, `collab_grant_edits`, `collab_grant_exempt` | Applications and the day ledger |
| Forum recording | `forum_videos`, `forum_chapters` | Chapters for the all-candidates video |
| Short links / QR | `short_links`, `qr_codes` | Destinations behind `/go.php` |
| Analytics | `site_views`, `site_clicks`, `site_daily`, `site_email_log` | — |
| BCTF forms | `bctf_forms` | Forms stored for the local |
| Email templates | `email_templates` | **Editable wording of every automated email** |

Also not in the repository: **`members/config.php`** (database credentials,
deliberately git-ignored). The grant list in `lp-db.php` seeds names such as
"Local Release Time Grant" but **grant names are editable in the database**.

---

## 10. Notes for whoever designs the replacement

- The public tree is **one array in `inc/nav.php`**. Restructuring the public
  menu is largely an edit to that array plus the `bvtuNavSection()` map beside
  it. The footer derives from the same array automatically.
- The **section landing pages are hand-maintained copies** of their dropdowns.
  Any redesign should decide whether a section page routes (and so duplicates
  the menu) or holds content (and so does not).
- The **member portal has no navigation system to redesign** — only a tile page.
  Giving the portal a real menu is a separate piece of work from the public tree.
- **Keep the LP dashboard distinct.** It is the union's financial controls, with
  its own permission model (President / Treasurer / VP signatures). Folding it
  into general member navigation would misrepresent who may do what.
