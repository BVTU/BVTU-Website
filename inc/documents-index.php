<?php
/**
 * documents-index.php — every public PDF under documents/, with its title,
 * topic and date written down rather than guessed from a filename.
 *
 * documents.php renders it. A file added to documents/ without a line here
 * does not appear on the index, so the regression check outside the webroot
 * fails until it is listed — and a line whose file has gone is dropped at
 * render time rather than shown as a broken link.
 *
 * Only documents/ is listed. Protected files (members/ uploads, receipts,
 * served documents) are not public and never belong here.
 *
 * Titles, types and dates are copied from the page each file already lives
 * on (lous.php, salary.php, calendars.php …), not written fresh.
 */

/** Topic key => heading, in display order. */
function docIndexTopics(): array {
    return [
        'agreements' => 'Agreements, LOUs &amp; settlements',
        'pay'        => 'Pay, benefits &amp; claim forms',
        'leave'      => 'Leave',
        'calendars'  => 'School calendars',
        'union'      => 'Union governance &amp; elections',
    ];
}

/**
 * One record per file:
 *   file   path under the site root
 *   title  as the member would say it
 *   topic  a key of docIndexTopics()
 *   type   what kind of document it is
 *   date   as printed on the source page ('' when the source gives none)
 *   status only where the source page says so (proposal, expired)
 *   guide  the page that explains it
 */
function docIndexRecords(): array {
    $s = 'documents/settlements/';
    return [
        // ── Agreements ───────────────────────────────────────────────────────
        ['file' => 'documents/provincial-collective-agreement-2025-2029.pdf',
         'title' => 'Provincial Collective Agreement 2025–2029', 'topic' => 'agreements',
         'type' => 'Collective agreement', 'date' => '2025–2029', 'guide' => 'collective-agreement.php'],
        ['file' => 'documents/collective-agreement-2022-2025.pdf',
         'title' => 'Collective Agreement 2022–2025 (local)', 'topic' => 'agreements',
         'type' => 'Collective agreement', 'date' => '2022–2025', 'guide' => 'collective-agreement.php'],

        ['file' => $s . '2026-2027-thursday-early-dismissal-signed.pdf',
         'title' => 'Thursday Afternoon Early Dismissal 2026-2027', 'topic' => 'agreements',
         'type' => 'LOU', 'date' => 'June 25, 2026', 'guide' => 'lous.php'],
        ['file' => $s . '2026-2027-temporary-small-fte-increases-signed.pdf',
         'title' => 'Temporary and Small FTE Increases to Existing Contracts 2026-2027', 'topic' => 'agreements',
         'type' => 'LOU', 'date' => 'June 25, 2026', 'guide' => 'lous.php'],
        ['file' => $s . '2026-2027-prod-alternate-remedy-signed.pdf',
         'title' => 'Professional Development as Alternate Remedy Options 2026-2027', 'topic' => 'agreements',
         'type' => 'LOU', 'date' => 'June 25, 2026', 'guide' => 'lous.php'],
        ['file' => $s . '2026-2027-school-psychologists-signed.pdf',
         'title' => 'School Psychologists 2026-2027', 'topic' => 'agreements',
         'type' => 'LOU', 'date' => 'In effect until June 30, 2027', 'guide' => 'lous.php'],
        ['file' => $s . '2026-non-sexist-environment-joint-letter.pdf',
         'title' => 'Non-Sexist Environment — Joint Letter', 'topic' => 'agreements',
         'type' => 'Joint letter', 'date' => 'September 16, 2026', 'guide' => 'lous.php'],
        ['file' => $s . '2025-2026-lous-signed.pdf',
         'title' => '2025-2026 LOUs — Thursday Early Dismissal · Temporary Small FTE Increases · Pro-D as Alternate Remedy', 'topic' => 'agreements',
         'type' => 'LOU', 'date' => 'June 3, 2025', 'guide' => 'lous.php'],
        ['file' => $s . 'article-c25-part-time-rights.pdf',
         'title' => 'Article C.25 Part-Time Teachers\' Rights — Employer Proposal', 'topic' => 'agreements',
         'type' => 'Proposal', 'date' => 'January 2025', 'status' => 'Employer proposal — not agreed', 'guide' => 'lous.php'],
        ['file' => $s . 'article-d22-staff-meetings.pdf',
         'title' => 'Article D.22 Staff Meetings — Current Text & Employer Proposal', 'topic' => 'agreements',
         'type' => 'Proposal', 'date' => 'January 2025', 'status' => 'Employer proposal — not agreed', 'guide' => 'lous.php'],
        ['file' => $s . '2024-lab-shop-class-size.pdf',
         'title' => 'Labs & Shops Class Size', 'topic' => 'agreements',
         'type' => 'Settlement', 'date' => 'May 2024', 'guide' => 'lous.php'],
        ['file' => $s . '2024-d4-5-elem-prep.pdf',
         'title' => 'Elementary Preparation Time Scheduling (Renewal)', 'topic' => 'agreements',
         'type' => 'LOA', 'date' => 'May 14, 2024', 'guide' => 'lous.php'],
        ['file' => $s . '2023-continuing-to-continuing.pdf',
         'title' => 'Post & Fill Grievance (Continuing-to-Continuing)', 'topic' => 'agreements',
         'type' => 'Arbitration award', 'date' => 'May 9, 2023', 'guide' => 'lous.php'],
        ['file' => $s . '2023-calendar-settlement.pdf',
         'title' => 'School Calendar Grievance (Pro-D Day Consultation)', 'topic' => 'agreements',
         'type' => 'Settlement', 'date' => 'November 20, 2023', 'guide' => 'lous.php'],
        ['file' => $s . '2018-jackson-remedy-rollover.pdf',
         'title' => 'Class Size/Composition Remedy (Jackson Remedy Rollover)', 'topic' => 'agreements',
         'type' => 'Arbitration award', 'date' => 'September 17, 2018', 'guide' => 'lous.php'],
        ['file' => $s . '2017-ttoc-temp-contract-sick-days.pdf',
         'title' => 'TTOC & Temporary Contract Sick Days (Carryover)', 'topic' => 'agreements',
         'type' => 'Arbitration award', 'date' => 'June 28, 2017', 'guide' => 'lous.php'],
        ['file' => $s . '2017-2018-cupe-emergency-replacements.pdf',
         'title' => 'CUPE Emergency Response Replacements for Teacher Absences', 'topic' => 'agreements',
         'type' => 'Guidelines', 'date' => '2017-2018', 'guide' => 'lous.php'],
        ['file' => $s . '2015-part-time-pro-d.pdf',
         'title' => 'Part-Time Teachers and Pro-D Day Attendance', 'topic' => 'agreements',
         'type' => 'Settlement', 'date' => 'February 17, 2015', 'guide' => 'lous.php'],
        ['file' => $s . '2014-ttoc-pay-settlement.pdf',
         'title' => 'TTOC Pay Grievance (Block Pay Rules)', 'topic' => 'agreements',
         'type' => 'Settlement', 'date' => 'January 21, 2014', 'guide' => 'lous.php'],
        ['file' => $s . '2014-ttoc-continuation-settlement.pdf',
         'title' => 'Continuation of TTOC Pay Settlement (from September 2015)', 'topic' => 'agreements',
         'type' => 'LOA', 'date' => 'June 27, 2014', 'guide' => 'lous.php'],
        ['file' => $s . '2013-elem-preptime.pdf',
         'title' => 'Elementary Preparation Time Scheduling (Original)', 'topic' => 'agreements',
         'type' => 'LOA', 'date' => 'February 25, 2013', 'guide' => 'lous.php'],
        ['file' => $s . '2010-sec-preptime.pdf',
         'title' => 'Secondary Preparation Time & Personal Leave', 'topic' => 'agreements',
         'type' => 'Letter & LOU', 'date' => 'April 28, 2010', 'guide' => 'lous.php'],
        ['file' => $s . '2004-early-retirement-incentive.pdf',
         'title' => 'Workforce Adjustment Program (Early Retirement Incentive)', 'topic' => 'agreements',
         'type' => 'LOU', 'date' => 'May 7, 2004', 'status' => 'Expired June 30, 2004', 'guide' => 'lous.php'],
        ['file' => $s . '2001-dorsey-award.pdf',
         'title' => 'Dorsey Award — Seniority List (Tiebreakers, TOC Days, Start Dates)', 'topic' => 'agreements',
         'type' => 'Arbitration award', 'date' => 'June 5, 2001', 'guide' => 'lous.php'],

        // ── Pay, benefits & claim forms ──────────────────────────────────────
        ['file' => 'documents/salary-grids/sd54-salary-grid-2028.pdf',
         'title' => 'SD54 Salary Grid 2028', 'topic' => 'pay', 'type' => 'Salary grid', 'date' => '2028', 'guide' => 'salary.php'],
        ['file' => 'documents/salary-grids/sd54-salary-grid-2027.pdf',
         'title' => 'SD54 Salary Grid 2027', 'topic' => 'pay', 'type' => 'Salary grid', 'date' => '2027', 'guide' => 'salary.php'],
        ['file' => 'documents/salary-grids/sd54-salary-grid-2026.pdf',
         'title' => 'SD54 Salary Grid 2026', 'topic' => 'pay', 'type' => 'Salary grid', 'date' => '2026', 'guide' => 'salary.php'],
        ['file' => 'documents/salary-grids/sd54-salary-grid-2025.pdf',
         'title' => 'SD54 Salary Grid 2025', 'topic' => 'pay', 'type' => 'Salary grid', 'date' => '2025', 'guide' => 'salary.php'],
        ['file' => 'documents/salary-grids/sd54-salary-grid-2024.pdf',
         'title' => 'SD54 Salary Grid 2024', 'topic' => 'pay', 'type' => 'Salary grid', 'date' => '2024', 'guide' => 'salary.php'],
        ['file' => 'documents/medical-referral-travel-claim-form.pdf',
         'title' => 'Medical Referral Travel & Accommodation claim form', 'topic' => 'pay',
         'type' => 'Form', 'date' => '', 'guide' => 'benefits.php'],
        ['file' => 'documents/student-loan-forgiveness-application.pdf',
         'title' => 'Student Loan Forgiveness application', 'topic' => 'pay',
         'type' => 'Form', 'date' => '', 'guide' => 'loan-forgiveness.php'],
        ['file' => 'documents/ttoc-experience-credit.pdf',
         'title' => 'Understanding TTOC Experience Credit', 'topic' => 'pay',
         'type' => 'Guide', 'date' => '', 'guide' => 'ttoc.php'],

        // ── Leave ────────────────────────────────────────────────────────────
        ['file' => 'documents/deferred-salary-leave-plan-policy.pdf',
         'title' => 'Deferred Salary Leave Plan — policy', 'topic' => 'leave',
         'type' => 'Policy', 'date' => '', 'guide' => 'deferred-salary.php'],
        ['file' => 'documents/deferred-salary-leave-plan-agreement-schedule-b.pdf',
         'title' => 'Deferred Salary Leave Plan — agreement (Schedule B)', 'topic' => 'leave',
         'type' => 'Form', 'date' => '', 'guide' => 'deferred-salary.php'],

        // ── School calendars ─────────────────────────────────────────────────
        ['file' => 'documents/calendars/SD54-School-Calendar-2026-2027.pdf',
         'title' => 'SD54 School Calendar 2026–27', 'topic' => 'calendars',
         'type' => 'Calendar', 'date' => '2026–27', 'guide' => 'calendars.php'],
        ['file' => 'documents/calendars/SD54-School-Calendar-2025-2026.pdf',
         'title' => 'SD54 School Calendar 2025–26', 'topic' => 'calendars',
         'type' => 'Calendar', 'date' => '2025–26', 'guide' => 'calendars.php'],

        // ── Union governance & elections ─────────────────────────────────────
        ['file' => 'documents/BVTU-Constitution-and-Bylaws-2026.pdf',
         'title' => 'BVTU Constitution & Bylaws', 'topic' => 'union',
         'type' => 'Governance', 'date' => 'Approved May 2026', 'guide' => 'about.php'],
        ['file' => 'documents/BVTU-Trustee-Candidate-Responses-2026-09-28.pdf',
         'title' => 'Trustee Candidate Responses', 'topic' => 'union',
         'type' => 'Election record', 'date' => 'September 28, 2026', 'guide' => 'trustee-candidates.php'],
    ];
}

/** The page names a guide link is shown as. */
function docIndexGuideLabel(string $page): string {
    $names = [
        'collective-agreement.php' => 'Collective Agreement',
        'lous.php'                 => 'LOUs & settlements',
        'salary.php'               => 'Salary Grids',
        'benefits.php'             => 'Health & Dental',
        'loan-forgiveness.php'     => 'Student Loan Forgiveness',
        'ttoc.php'                 => 'TTOC Resources',
        'deferred-salary.php'      => 'Deferred Salary Leave Plan',
        'calendars.php'            => 'School Calendars',
        'about.php'                => 'About the BVTU',
        'trustee-candidates.php'   => 'Candidate responses',
    ];
    return $names[$page] ?? $page;
}
