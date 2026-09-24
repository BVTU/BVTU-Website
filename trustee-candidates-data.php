<?php
/**
 * trustee-candidates-data.php — survey wording and candidate responses.
 *
 * ── THIS IS THE ONLY FILE YOU EDIT TO PUBLISH RESPONSES ──────────────────────
 * trustee-candidates.php reads this and never needs changing to add, amend or
 * withdraw an answer.
 *
 * Nothing here is published until every question's `prompt` is filled in. While
 * any prompt is still empty the public page says responses are not yet
 * available and shows no questions, names or answers — so a half-filled file
 * cannot leak a placeholder onto a public page.
 *
 * Answers are printed VERBATIM. Paragraph breaks are blank lines; a line that
 * starts with "- " becomes a bullet. Nothing else is interpreted, and nothing
 * is corrected — spelling, grammar, punctuation and claims are the candidate's.
 */

// ── Page-level settings ──────────────────────────────────────────────────────

/** Shown as "Last updated" at the top of the page. Update when you change anything. */
const TC_LAST_UPDATED = '';          // e.g. '2026-09-24'

/** The candidate-organised PDF. Leave '' to hide the download button entirely. */
const TC_PDF_URL      = '';          // e.g. 'documents/BVTU-Trustee-Candidate-Responses-2026.pdf'

/** Survey deadline, and the date we last checked for late responses. */
const TC_DEADLINE     = '2026-09-26';
const TC_LAST_CHECKED = '';          // e.g. '2026-09-24'

/**
 * Set true ONLY after the deadline has passed AND you have checked again.
 * Until then a non-responding candidate reads "No response received as of
 * <last checked>", which is a statement about what we know rather than about
 * what they did.
 */
const TC_RECHECKED_AFTER_DEADLINE = false;

// ── Survey questions ─────────────────────────────────────────────────────────
//
// Paste the EXACT wording BVTU sent. The short labels below are only to help
// you match each slot to the right question — they are never displayed.
//
// 'id'     never change once responses reference it.
// 'label'  your own shorthand, for this file only.
// 'prompt' the exact question as sent.
// 'follow' an exact follow-up prompt shown beneath the main one, or null.

const TC_SURVEYS = [
    'new' => [
        'name'      => 'New candidates',
        'questions' => [
            ['id' => 'n1',  'label' => 'Why running',            'prompt' => '', 'follow' => null],
            ['id' => 'n2',  'label' => 'Education partners',     'prompt' => '',
             // 2(a). Its own prompt. A candidate who answered 2 and 2(a) together
             // is handled by answering under the id 'n2+n2a' — see below.
             'follow' => null],
            ['id' => 'n2a', 'label' => 'Community voices',       'prompt' => '', 'follow' => null],
            ['id' => 'n3',  'label' => 'Differing opinion',      'prompt' => '', 'follow' => null],
            ['id' => 'n4',  'label' => 'Live streaming',         'prompt' => '', 'follow' => null],
            ['id' => 'n5',  'label' => 'Memory of an educator',  'prompt' => '', 'follow' => null],
        ],
    ],
    'incumbent' => [
        'name'      => 'Incumbent trustees',
        'questions' => [
            ['id' => 'i1',  'label' => 'Terms / why again',      'prompt' => '', 'follow' => null],
            ['id' => 'i2',  'label' => 'Classroom / changed mind / voted against', 'prompt' => '', 'follow' => null],
            ['id' => 'i3',  'label' => 'Education partners',     'prompt' => '', 'follow' => null],
            ['id' => 'i3a', 'label' => 'Community voices',       'prompt' => '', 'follow' => null],
            ['id' => 'i4',  'label' => 'Live streaming',         'prompt' => '', 'follow' => null],
        ],
    ],
];

/**
 * Combined answers.
 *
 * If a candidate gave ONE answer covering both the partners question and its
 * community-voices follow-up, put that answer under the combined id and the
 * page shows both prompts together above their single answer. Their words are
 * neither duplicated nor split.
 *
 *   'answers' => [ 'n2+n2a' => "their one answer" ]
 */
const TC_COMBINED = [
    'n2+n2a' => ['n2', 'n2a'],
    'i3+i3a' => ['i3', 'i3a'],
];

// ── Candidates ───────────────────────────────────────────────────────────────
//
// 'slug'   used in the URL (#candidate-jane-smith). Never change it once shared.
// 'name'   as it appears on the ballot.
// 'group'  'new' or 'incumbent' — decides which survey they are shown against.
// 'status' 'responded' | 'no_response' | 'declined'
// 'answers' keyed by question id. Omit a question, or leave it '', and the page
//           says "No answer provided to this question." — which is different
//           from not responding at all.
// 'updated' optional 'YYYY-MM-DD' shown as a dated note when an answer is added
//           or changed after first publication.
//
// Order does not matter: the page sorts alphabetically by surname within group.

const TC_CANDIDATES = [

    /* ── PASTE CANDIDATES BELOW. Template: ───────────────────────────────────
    [
        'slug'    => 'jane-smith',
        'name'    => 'Jane Smith',
        'group'   => 'new',
        'status'  => 'responded',
        'updated' => null,
        'answers' => [
            'n1'  => "First paragraph exactly as written.\n\nSecond paragraph.\n\n- a bullet\n- another bullet",
            'n2'  => "",
            'n2a' => "",
            'n3'  => "",
            'n4'  => "",
            'n5'  => "",
        ],
    ],
    [
        'slug'   => 'alex-doe',
        'name'   => 'Alex Doe',
        'group'  => 'incumbent',
        'status' => 'responded',
        'answers' => [
            'i1'     => "",
            'i2'     => "",
            'i3+i3a' => "One answer that covers both the partners question and the community-voices follow-up.",
            'i4'     => "",
        ],
    ],
    [
        'slug'   => 'sam-jones',
        'name'   => 'Sam Jones',
        'group'  => 'new',
        'status' => 'no_response',
    ],
    [
        'slug'   => 'pat-lee',
        'name'   => 'Pat Lee',
        'group'  => 'new',
        'status' => 'declined',
    ],
    ─────────────────────────────────────────────────────────────────────────── */

];
