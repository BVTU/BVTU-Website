<?php
/**
 * email-admin-nav.php — the tab strip shared by the three email admin pages.
 *
 * They were three separate dashboard tiles for what is one job: what the site
 * says, what it actually sent, and sending a test. One strip, included by each,
 * so adding a page later means editing one list.
 *
 * The three pages do not share a gate — wording is President-level
 * (execIsAdmin), the sent log is either, and the test send is expense-portal
 * admin (expIsAdmin) — so a tab is only shown to someone who can actually open
 * it. A visible tab that bounces you to the dashboard reads as a broken page.
 *
 * These conditions must match the gate on each page. A tab shown to someone the
 * page then redirects is the bug this arrangement exists to avoid.
 */
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/exp-db.php';

function emailAdminNav(string $current, string $email): string {
    $tabs = [
        'wording' => ['email-templates.php', 'Wording',     execIsAdmin($email)],
        'log'     => ['email-log.php',       'Sent log',    expIsAdmin($email) || execIsAdmin($email)],
        'test'    => ['test-email.php',      'Send a test', expIsAdmin($email)],
    ];

    $out = '<div class="email-tabs">';
    foreach ($tabs as $key => $t) {
        list($href, $label, $allowed) = $t;
        if (!$allowed) continue;
        $on = ($key === $current) ? ' on' : '';
        $out .= '<a class="etab' . $on . '" href="' . htmlspecialchars($href) . '">'
              . htmlspecialchars($label) . '</a>';
    }
    return $out . '</div>';
}

function emailAdminNavStyles(): string {
    return '<style>
    .email-tabs { display:flex;gap:.3rem;flex-wrap:wrap;margin:0 0 1.4rem;
                  border-bottom:1px solid var(--gray-200);padding-bottom:.6rem; }
    .email-tabs .etab { font-size:.88rem;font-weight:700;color:var(--gray-600);
                        text-decoration:none;padding:.35rem .8rem;border-radius:7px; }
    .email-tabs .etab:hover { background:var(--gray-100);color:var(--primary); }
    .email-tabs .etab.on { background:var(--primary);color:#fff; }
    </style>';
}
