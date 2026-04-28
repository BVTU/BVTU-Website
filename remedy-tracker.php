<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Remedy Tracker &mdash; Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Calculate class size and composition remedies under your collective agreement &mdash; BVTU Remedy Tracker.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <style>

        .remedy-app {
            --ink:        #1e2b3a;
            --ink-soft:   #4a5568;
            --ink-muted:  #8a97a8;
            --primary:    #2b5282;
            --primary-hover: #1e3a5f;
            --accent:     #d97706;
            --accent-soft:#fef3c7;
            --surface:    #f8f7f4;
            --card:       #ffffff;
            --border:     #e2e1dc;
            --border-strong: #c8c5bc;
            --row-alt:    #f8f7f4;
            --danger:     #b91c1c;
            --danger-soft:#fef2f2;
            --success:    #15803d;
            --radius-sm:  6px;
            --radius-md:  10px;
            --radius-lg:  16px;
            --shadow-sm:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:  0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.05);
            --shadow-lg:  0 8px 24px rgba(0,0,0,0.10), 0 4px 8px rgba(0,0,0,0.06);
        }

        *, *::before, *::after { box-sizing: border-box; }


        

        

        

        

        

        /* ── LAYOUT ── */
        .rt-container {
            max-width: 1180px;
            margin: 32px auto;
            padding: 0 24px 48px;
        }

        /* ── DISTRICT PANEL ── */
        #districtPanel {
            background: var(--card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 24px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .district-icon {
            width: 44px;
            height: 44px;
            background: var(--accent-soft);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.3rem;
        }

        .district-fields {
            flex: 1;
            min-width: 220px;
        }

        #districtPanel label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ink-soft);
        }

        #districtPanel select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            background: var(--card);
            cursor: pointer;
            transition: border-color 0.15s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%234a5568' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        #districtPanel select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43,82,130,0.12);
        }

        #districtMeta {
            margin-top: 12px;
            font-size: 0.875rem;
            color: var(--ink-soft);
            display: none;
            padding: 10px 14px;
            background: var(--surface);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--accent);
        }

        #districtMetaName {
            font-weight: 600;
            color: var(--ink);
        }

        .meta-note {
            color: var(--ink-muted);
            margin-top: 3px;
            font-size: 0.83rem;
        }

        /* ── MAIN CONTENT ── */
        #mainContent { display: none; }

        /* ── SECTION CARDS ── */
        .card {
            background: var(--card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        .card-title {
            font-family: 'DM Serif Display', serif;
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--ink);
            margin: 0 0 20px;
            padding-bottom: 14px;
            border-bottom: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title .title-icon {
            width: 32px;
            height: 32px;
            background: var(--accent-soft);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        /* ── NOTE BAR ── */
        .note {
            font-size: 0.875rem;
            color: var(--ink-soft);
            background: var(--surface);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            border-left: 3px solid var(--primary);
            margin-bottom: 24px;
            line-height: 1.65;
        }

        /* ── FORM ── */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ink-soft);
        }

        .form-group input,
        .form-group select {
            padding: 9px 12px;
            border: 1.5px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            background: var(--card);
            transition: border-color 0.15s, box-shadow 0.15s;
            width: 100%;
            appearance: none;
        }

        .form-group select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%234a5568' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-color: var(--card);
            padding-right: 32px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43,82,130,0.12);
        }

        .form-group input:disabled {
            background: var(--surface);
            color: var(--ink-muted);
            cursor: not-allowed;
        }

        /* ── MONTH CHIPS ── */
        .months-section { grid-column: 1 / -1; }

        .months-label {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ink-soft);
            margin-bottom: 10px;
        }

        .months {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .month-chip {
            padding: 6px 14px;
            border-radius: 100px;
            border: 1.5px solid var(--border-strong);
            color: var(--ink-soft);
            background: var(--card);
            cursor: pointer;
            user-select: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s;
        }

        .month-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .month-chip.selected {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* ── BUTTONS ── */
        .btn-row {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            padding-top: 8px;
        }

        .remedy-app button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 1px 3px rgba(43,82,130,0.3);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            box-shadow: 0 2px 6px rgba(43,82,130,0.35);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--ink-soft);
            border: 1.5px solid var(--border-strong);
        }

        .btn-secondary:hover {
            background: var(--border);
            color: var(--ink);
        }

        .btn-danger {
            background: var(--danger-soft);
            color: var(--danger);
            border: 1.5px solid #fecaca;
        }

        .btn-danger:hover {
            background: #fee2e2;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 0.82rem;
        }

        /* ── TABLE ── */
        .table-wrapper {
            overflow-x: auto;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
        }

        .remedy-app table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .remedy-app thead th {
            background: var(--primary);
            color: white;
            padding: 11px 12px;
            text-align: left;
            font-weight: 500;
            font-size: 0.8rem;
            letter-spacing: 0.03em;
            white-space: nowrap;
            border-bottom: none;
        }

        thead th:first-child { border-radius: 0; }

        .remedy-app tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--ink-soft);
            vertical-align: middle;
            white-space: nowrap;
        }

        .remedy-app tbody td:first-child,
        .remedy-app tbody td:nth-child(4) {
            color: var(--ink);
            font-weight: 500;
        }

        .remedy-app tbody tr:last-child td { border-bottom: none; }

        .remedy-app tbody tr:nth-child(even) td { background: var(--row-alt); }

        .remedy-app tbody tr:hover td {
            background: #eef2f8;
        }

        /* ── YEAR HEADER ROWS ── */
        .year-header td {
            background: var(--surface);
            color: var(--ink);
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.04em;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            border-top: 2px solid var(--border);
        }

        .year-header button {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1rem;
            cursor: pointer;
            margin-right: 6px;
            padding: 0;
            line-height: 1;
            font-weight: 700;
        }

        /* ── SPEND FORM ── */
        .spend-form {
            padding: 16px;
            background: var(--surface);
            border-top: 1.5px solid var(--border);
        }

        .spend-form-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .spend-form-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1 1 140px;
        }

        .spend-form-field label {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ink-muted);
        }

        .spend-form-field input,
        .spend-form-field select {
            padding: 7px 10px;
            border: 1.5px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            background: var(--card);
            width: 100%;
        }

        .spend-form-field input:focus,
        .spend-form-field select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43,82,130,0.10);
        }

        /* ── USAGE LOG ── */
        .usage-log {
            margin-top: 12px;
            font-size: 0.82rem;
        }

        .usage-log ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .usage-log li {
            padding: 5px 0;
            border-bottom: 1px solid var(--border);
            color: var(--ink-soft);
            display: flex;
            gap: 8px;
        }

        .usage-log li::before {
            content: '↳';
            color: var(--ink-muted);
            flex-shrink: 0;
        }

        .usage-log li:last-child { border-bottom: none; }

        /* ── SPENDING HISTORY DROPDOWN ── */
        .usage-log-details {
            margin-top: 12px;
        }
        .usage-log-details > summary {
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--primary);
            padding: 4px 0;
            user-select: none;
            list-style: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .usage-log-details > summary::before {
            content: '▶';
            font-size: 0.65rem;
            transition: transform 0.15s;
        }
        .usage-log-details[open] > summary::before {
            transform: rotate(90deg);
        }
        .usage-log-details > summary:hover { color: var(--primary-dark, #1a3a6b); }
        .usage-log-details .usage-log { margin-top: 6px; }

        /* ── ENTRY DETAIL STRIP ── */
        .entry-detail-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 24px;
            padding: 10px 16px;
            background: var(--row-alt);
            border-bottom: 1.5px solid var(--border);
            font-size: 0.8rem;
            color: var(--ink-soft);
        }
        .entry-detail-strip span {
            display: flex;
            align-items: baseline;
            gap: 5px;
        }
        .entry-detail-strip label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--ink-muted);
        }

        /* ── TRANSACTION HISTORY (SUMMARY) ── */
        .txn-history {
            border-top: 1px solid var(--border);
        }
        .txn-history > summary {
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--primary);
            padding: 7px 14px;
            user-select: none;
            list-style: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .txn-history > summary::before {
            content: '▶';
            font-size: 0.62rem;
            transition: transform 0.15s;
            flex-shrink: 0;
        }
        .txn-history[open] > summary::before { transform: rotate(90deg); }
        .txn-history > summary:hover { color: var(--primary-dark, #1a3a6b); }
        .txn-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .txn-table th {
            background: #dde4f0;
            color: var(--ink-muted);
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 5px 12px;
            text-align: left;
            white-space: nowrap;
        }
        .txn-table td {
            padding: 5px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--ink-soft);
            white-space: nowrap;
        }
        .txn-table td.txn-note { white-space: normal; color: var(--ink-muted); font-style: italic; }
        .txn-table tr:last-child td { border-bottom: none; }
        .txn-empty {
            padding: 10px 14px;
            font-size: 0.8rem;
            color: var(--ink-muted);
            font-style: italic;
        }

        /* ── SUMMARY ── */
        .summary-table thead th {
            background: #1e3a5f;
        }

        /* ── TOOLBAR ── */
        .table-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--ink-muted);
        }

        .empty-state .empty-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state p {
            margin: 0;
            font-size: 0.95rem;
        }

        /* ── COMPOSITION REFERENCE ── */
        .composition-ref {
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: #fffbeb;
            border: 1.5px solid #fde68a;
            border-left: 4px solid var(--accent);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 0.875rem;
        }

        .comp-ref-label {
            font-weight: 600;
            color: #92400e;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .comp-ref-text {
            color: #78350f;
            line-height: 1.55;
            font-style: italic;
        }

        /* ── FIELD HELPER TEXT ── */
        .field-hint {
            font-size: 0.78rem;
            color: var(--ink-muted);
            margin-top: 4px;
            line-height: 1.4;
        }

        /* ── CLASS SIZE CAP BAR (fixed bottom) ── */
        .class-cap-badge {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: #1e3a5f;
            border-top: 2px solid #2b5282;
            padding: 10px 32px;
        }
        .class-cap-badge.visible { display: block; }
        .cap-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .cap-stat {
            display: flex;
            align-items: baseline;
            gap: 5px;
        }
        .cap-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255,255,255,0.55);
        }
        .cap-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
        }
        .cap-article {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.45);
        }
        .cap-note {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            font-style: italic;
        }
        .cap-divider {
            width: 1px;
            height: 18px;
            background: rgba(255,255,255,0.2);
            flex-shrink: 0;
        }
        body.cap-bar-visible { padding-bottom: 52px; }

        /* ── S2 DESIGNATION GUIDE ── */
        .s2-guide {
            margin-top: 8px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            font-size: 0.78rem;
        }
        .s2-guide > summary {
            cursor: pointer;
            padding: 7px 11px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            list-style: none;
            display: flex;
            align-items: center;
            gap: 6px;
            user-select: none;
        }
        .s2-guide > summary::before {
            content: '▶';
            font-size: 0.6rem;
            transition: transform 0.15s;
            flex-shrink: 0;
        }
        .s2-guide[open] > summary::before { transform: rotate(90deg); }
        .s2-guide-body {
            padding: 0 12px 10px;
            color: var(--ink-soft);
        }
        .s2-guide-body p {
            margin: 0 0 8px;
            font-size: 0.76rem;
        }
        .s2-cat-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px 16px;
            margin: 0 0 10px;
            padding: 0;
            list-style: none;
        }
        .s2-cat-list li { font-size: 0.75rem; }
        .s2-cat-list li strong {
            display: inline-block;
            width: 18px;
            color: var(--primary);
            font-weight: 700;
        }
        .s2-gq-note {
            padding: 7px 10px;
            background: #fef9ec;
            border: 1.5px solid #f0c842;
            border-radius: var(--radius-sm);
            font-size: 0.73rem;
            color: #7a5c00;
            line-height: 1.5;
        }
        .s2-gq-note strong {
            display: block;
            margin-bottom: 3px;
        }

        /* ── S2 RULE CALLOUT ── */
        .s2-rule {
            margin-top: 6px;
            padding: 8px 11px;
            background: #fef9ec;
            border: 1.5px solid #f0c842;
            border-radius: var(--radius-sm);
            font-size: 0.76rem;
            color: #7a5c00;
            line-height: 1.5;
        }
        .s2-rule strong {
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
            color: #5a4400;
        }

        /* ── CHECKBOX STYLE ── */
        .remedy-app input[type="checkbox"] {
            accent-color: var(--primary);
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 700px) {
            .rt-container { padding: 0 14px 40px; }
            .card { padding: 20px 16px; }
            
            .form-grid { grid-template-columns: 1fr; }
            #districtPanel { flex-direction: column; }
        }
      </style>
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers&#39; Union</span>
          <span class="logo-sub">Local of the BC Teachers&#39; Federation</span>
        </div>
      </a>
      <button class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li class="has-dropdown">
            <a href="members.php" class="active">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="remedy-tracker.php" style="font-weight:700;">Remedy Tracker</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? 'members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Remedy Tracker</h1>
      <p>Calculate class size and composition remedies under your collective agreement. Select your school district to get started.</p>
    </div>
  </section>

  <!-- districtSubtitle: referenced by JS — kept hidden, text updated dynamically -->
  <p id="districtSubtitle" style="display:none;"></p>

  <div class="remedy-app">


    <div class="rt-container">

        <!-- District selector -->
        <div id="districtPanel">
            <div class="district-icon">🏫</div>
            <div class="district-fields">
                <label for="districtSelect">School District / Local</label>
                <select id="districtSelect">
                    <option value="">-- Select your district --</option>
                </select>
                <div id="districtMeta">
                    <span id="districtMetaName"></span>
                    <div class="meta-note" id="districtMetaNote"></div>
                </div>
            </div>
        </div>

        <!-- Everything below hidden until district chosen -->
        <div id="mainContent">
            <p class="note" id="mainNote"></p>

            <div class="card">
                <div class="card-title">
                    <span class="title-icon">➕</span>
                    Add Entry
                </div>
                <form id="entryForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="schoolYear">School Year</label>
                            <select id="schoolYear" required>
                                <option value="">Select year</option>
                                <option value="2025-2026">2025-2026</option>
                                <option value="2026-2027">2026-2027</option>
                                <option value="2027-2028">2027-2028</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="classType">Class Type</label>
                            <select id="classType" required>
                                <option value="">Select class type</option>
                            </select>
                        </div>
                        <div class="form-group" id="courseTypeGroup" style="display:none;">
                            <label for="courseType">Course Type</label>
                            <select id="courseType">
                                <option value="">Select course type</option>
                                <option value="Linear">Linear (full year)</option>
                                <option value="Semester">Semester (half year)</option>
                            </select>
                            <div class="field-hint">Selecting this pre-fills P below — adjust if needed.</div>
                        </div>
                        <div class="form-group">
                            <label for="courseName">Course / Class Name</label>
                            <input type="text" id="courseName" placeholder="e.g., Math 10" />
                        </div>
                        <div class="form-group">
                            <label for="students">Students Enrolled</label>
                            <input type="number" id="students" min="0" required placeholder="0" />
                        </div>
                        <div id="s2Container">
                            <!-- populated by updateS2Fields() -->
                        </div>
                        <div class="form-group" id="pValueGroup">
                            <label for="pValue">P — Proportion of teaching load</label>
                            <input type="number" id="pValue" min="0.01" max="1" step="0.001" placeholder="e.g. 1.0" />
                            <div class="field-hint" id="pValueHint">1.0 = full time &nbsp;·&nbsp; 0.8 = 4 days/week &nbsp;·&nbsp; 0.6 = 3 days/week</div>
                        </div>
                        <div class="months-section">
                            <div class="months-label">Months</div>
                            <div class="months" id="monthsContainer"></div>
                            <button type="button" class="btn-secondary btn-sm" id="selectOctJun">Select Oct–Jun</button>
                        </div>
                        <div class="btn-row">
                            <button type="submit" class="btn-primary">Add Entry/Entries</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-title">
                    <span class="title-icon">📋</span>
                    Entries &amp; Usage
                </div>
                <div class="table-toolbar">
                    <button type="button" class="btn-danger btn-sm" onclick="deleteSelected()">Delete Selected</button>
                    <button type="button" class="btn-secondary btn-sm" onclick="downloadCSV()">Export CSV</button>
                </div>
                <div class="table-wrapper">
                    <table id="entriesTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll()" /></th>
                                <th>Year</th>
                                <th>Month</th>
                                <th>Name</th>
                                <th>Class Type</th>
                                <th>Total Min</th>
                                <th>Total $</th>
                                <th>Min Rem</th>
                                <th>$ Rem</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="entriesBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <span class="title-icon">📊</span>
                    Summary by Year
                </div>
                <div class="table-wrapper">
                    <table id="summaryTable" class="summary-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Total Min</th>
                                <th>Total $</th>
                                <th>Min Used</th>
                                <th>$ Used</th>
                                <th>Min Rem</th>
                                <th>$ Rem</th>
                            </tr>
                        </thead>
                        <tbody id="summaryBody"></tbody>
                    </table>
                </div>
            </div>

        </div><!-- end #mainContent -->
    </div>

    
  </div><!-- /.remedy-app -->

<div class="class-cap-badge" id="classSizeBadge">
        <div class="cap-inner">
            <div class="cap-stat">
                <span class="cap-label">Contract cap</span>
                <span class="cap-value" id="capMaxSize">—</span>
                <span class="cap-label">students</span>
            </div>
            <div class="cap-divider"></div>
            <div class="cap-stat">
                <span class="cap-label">Remedy starts at</span>
                <span class="cap-value" id="capThreshold">—</span>
                <span class="cap-label">students</span>
                <span class="cap-article">(cap + 2 flex)</span>
            </div>
            <div class="cap-divider" id="capCompDivider"></div>
            <div class="cap-stat" id="capCompStat">
                <span class="cap-label">Composition limit</span>
                <span class="cap-value" id="capCompValue">—</span>
                <span class="cap-label" id="capCompUnit">designated</span>
            </div>
            <div class="cap-divider"></div>
            <div class="cap-note" id="capArticleNote"></div>
        </div>
    </div>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers&#39; Union</h3>
        <p>Local of the BC Teachers&#39; Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div>
        <h3>Contact</h3>
        <p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p>
        <p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p>
        <p><a href="contact.php">Contact Us</a></p>
      </div>
      <div>
        <h3>Navigate</h3>
        <ul class="footer-nav-list">
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
        </ul>
      </div>
      <div>
        <h3>Connect</h3>
        <a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>&copy; 2026 Bulkley Valley Teachers&#39; Union &middot; Local of the BC Teachers&#39; Federation</p>
      </div>
    </div>
  </footer>

  <script>

        // -------------------------------------------------------
        // CONFIGURATION — loaded from embedded districts data
        // In the Claude Code version this will be fetched from
        // districts.json at runtime. Here it is inlined for
        // portability as a single standalone HTML file.
        // -------------------------------------------------------
        const DISTRICTS_DATA = [
  {
    "id": "sd5",
    "sdNumber": 5,
    "name": "SD5 SE Kootenay (CFTA)",
    "localName": "Cranbrook and Fernie Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.4.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.4",
    "compositionNote": "There shall be a maximum of two (2) dependent handicapped and/or low incidence-high cost students integrated into any regular classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary multi-age/multi-program (Grades K-1)",
        "value": "Primary multi-age/multi-program (Grades K-1)",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary multi-age/multi-program (Grades 1-3)",
        "value": "Primary multi-age/multi-program (Grades 1-3)",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "A grouping combining both Primary and Intermediate students",
        "value": "A grouping combining both Primary and Intermediate students",
        "maxSize": 24,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate split or multi-age/multi-program (Grades 4-7)",
        "value": "Intermediate split or multi-age/multi-program (Grades 4-7)",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Other Intermediate (Grades 4-7)",
        "value": "Other Intermediate (Grades 4-7)",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 8\u201312",
        "value": "Grades 8-12",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd6",
    "sdNumber": 6,
    "name": "SD6 Rocky Mountain (RMTA)",
    "localName": "Rocky Mountain Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "No specific numerical limit on the number of special needs students per classroom is stated in Article D.2. The article addresses integration process and factors to consider but does not set a maximum count.",
    "needsReview": true,
    "reviewNote": "No specific composition limit number found in D.2. Article describes integration process only.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate",
        "value": "Intermediate",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary",
        "value": "Secondary",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd8",
    "sdNumber": 8,
    "name": "SD8 Kootenay Lake (KLTF)",
    "localName": "Kootenay Lake Teachers' Federation",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.7.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.7",
    "compositionNote": "The school-based team shall endeavour to limit the number of special needs students it integrates into any regular classroom as follows: Two (2) Low Incidence - High Cost students, or Four (4) High Incidence - Low Cost students, or One (1) Low Incidence - High Cost student and two (2) High Incidence - Low Cost students.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Grades 4-7) average",
        "value": "Intermediate (Grades 4-7) average",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary \u2013 Shops, laboratories or beginning band average",
        "value": "Secondary \u2013 Shops, laboratories or beginning band average",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary \u2013 Humanities (English or Social Studies) average",
        "value": "Secondary \u2013 Humanities (English or Social Studies) average",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary \u2013 Average all other classes",
        "value": "Secondary \u2013 Average all other classes",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      }
    ],
    "s2Categories": [
      {
        "id": "s2Low",
        "label": "Low Incidence (High Cost)"
      },
      {
        "id": "s2High",
        "label": "High Incidence (Low Cost)"
      }
    ],
    "s2Rule": "Valid combinations: 2 Low Incidence, OR 4 High Incidence, OR 1 Low Incidence + 2 High Incidence. Each Low Incidence counts as 2 points; each High Incidence counts as 1 \u2014 combined total may not exceed 4."
  },
  {
    "id": "sd10",
    "sdNumber": 10,
    "name": "SD10 Arrow Lakes (ALTA)",
    "localName": "Arrow Lakes Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "No specific numerical limit on the number of special needs students per classroom is stated in Article D.2. The article addresses school-based team planning and support but does not set a maximum count of special needs students per class.",
    "needsReview": true,
    "reviewNote": "No local class size language beyond LOU No. 12 K-3 limits. No specific composition limit number found.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd19",
    "sdNumber": 19,
    "name": "SD19 Revelstoke (RTA)",
    "localName": "Revelstoke Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "No specific numerical limit on the number of special needs students per classroom is stated in Article D.2. The article establishes a joint committee and consultation process for integration but does not set a maximum count.",
    "needsReview": true,
    "reviewNote": "No specific composition limit number found in D.2.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Kindergarten/Grade 1 class",
        "value": "Kindergarten/Grade 1 class",
        "maxSize": 15,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary split class (1, 2, 3, 4)",
        "value": "Primary split class (1, 2, 3, 4)",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate split class (4, 5, 6, 7)",
        "value": "Intermediate split class (4, 5, 6, 7)",
        "maxSize": 24,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English class",
        "value": "Secondary English class",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class (4-12)",
        "value": "Any other class (4-12)",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd20",
    "sdNumber": 20,
    "name": "SD20 Kootenay Columbia (KCTU)",
    "localName": "Kootenay Columbia Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "No specific numerical limit on the number of special needs students per classroom is stated in Article D.2. The article addresses integration process, school-based team roles, and review processes but does not set a maximum count of special needs students per class.",
    "needsReview": true,
    "reviewNote": "No specific composition limit number found in D.2.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 4-7: multi-age, split grades",
        "value": "Grades 4-7: multi-age, split grades",
        "maxSize": 24,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 8-12: multi-age, labs in Science, Home Economics, Industrial Education",
        "value": "Grades 8-12: multi-age, labs in Science, Home Economics, Industrial Education",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class (Grades 4-12)",
        "value": "Any other class (Grades 4-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd22",
    "sdNumber": 22,
    "name": "SD22 Vernon (VTA)",
    "localName": "Vernon Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.7.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.7",
    "compositionNote": "To ensure that all students receive adequate attention, no more than two (2) students with special educational needs shall normally be integrated at the same time into any one regular classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 3/4 Split Classes",
        "value": "Grade 3/4 Split Classes",
        "maxSize": 23,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "English Language Learner Classes",
        "value": "English Language Learner Classes",
        "maxSize": 18,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (4,5,6,7) Split Classes",
        "value": "Intermediate (4,5,6,7) Split Classes",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English Classes",
        "value": "Secondary English Classes",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science Classes",
        "value": "Science Classes",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Ec. Classes",
        "value": "Home Ec. Classes",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Tech. Ed. Lab (I.E. Lab)",
        "value": "Tech. Ed. Lab (I.E. Lab)",
        "maxSize": 22,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any Other Class (4-12)",
        "value": "Any Other Class (4-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd23",
    "sdNumber": 23,
    "name": "SD23 Central Okanagan (COTA)",
    "localName": "Central Okanagan Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.10.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.10",
    "compositionNote": "To ensure that all students receive adequate attention, no more than three (3) students with exceptional educational needs shall be integrated at the same time into any one regular classroom. Where the Superintendent or designate identifies a student with a severe behavioural problem, that student shall be included in the students with exceptional educational needs for the purposes of this clause. No more than one (1) such student shall be assigned to any one regular classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 3/4",
        "value": "Grade 3/4",
        "maxSize": 23,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (4,5,6,7) Split Classes",
        "value": "Intermediate (4,5,6,7) Split Classes",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English Class",
        "value": "Secondary English Class",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Science Class",
        "value": "Secondary Science Class",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Life Skills & Family Management",
        "value": "Life Skills & Family Management",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Ec & I.E.",
        "value": "Home Ec & I.E.",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any Other Class (4-12)",
        "value": "Any Other Class (4-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd27",
    "sdNumber": 27,
    "name": "SD27 Cariboo-Chilcotin (CCTA)",
    "localName": "Cariboo-Chilcotin Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.1.6.",
    "compositionLimit": 2,
    "compositionArticle": "D.1.6",
    "compositionNote": "No such class shall have more than two (2) LIIHC special needs students enrolled on a regular full-time basis.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 4-7",
        "value": "Grade 4-7",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 8-10",
        "value": "Grade 8-10",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 11-12",
        "value": "Grade 11-12",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd28",
    "sdNumber": 28,
    "name": "SD28 Quesnel (QDTA)",
    "localName": "Quesnel District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "No specific numerical limit on the number of special needs students per classroom is stated in Article D.2. The article addresses school-based team consultation and integration process but does not set a maximum count of special needs students per class.",
    "needsReview": true,
    "reviewNote": "No specific composition limit number found in D.2.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Single Grade Intermediate Classes",
        "value": "Single Grade Intermediate Classes",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multi Grade Intermediate Classes",
        "value": "Multi Grade Intermediate Classes",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Lab Oriented Science class \u2013 Sr. Level",
        "value": "Lab Oriented Science class \u2013 Sr. Level",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Lab Oriented Science class \u2013 Jr. Level",
        "value": "Lab Oriented Science class \u2013 Jr. Level",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Workshop (I.E., Home Ec.)",
        "value": "Workshop (I.E., Home Ec.)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Computer Labs",
        "value": "Computer Labs",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Graphic Arts (Correlieu)",
        "value": "Graphic Arts (Correlieu)",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English Class",
        "value": "Secondary English Class",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class (except Music, Choir, Band)",
        "value": "Any other class (except Music, Choir, Band)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd33",
    "sdNumber": 33,
    "name": "SD33 Chilliwack (CTA)",
    "localName": "Chilliwack Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Class size and composition shall be given consideration as per D.1.3 when classroom placement is to be determined for a student with exceptional needs. To ensure that all students receive adequate attention, only a reasonable number of students with exceptional needs shall be integrated into any one regular classroom at the same time. No specific numeric limit stated.",
    "needsReview": true,
    "reviewNote": "No local class size table found in CA excerpt \u2014 only D.2 composition content present. Class size data beyond K-3 needs verification. Composition article states only \"a reasonable number\" \u2014 no hard limit.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd34",
    "sdNumber": 34,
    "name": "SD34 Abbotsford (ATU)",
    "localName": "Abbotsford Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.8.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.8",
    "compositionNote": "A maximum of three (3) students with special needs (other than Gifted) may be included in a single class.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Grades 4-10)",
        "value": "Intermediate (Grades 4-10)",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary Splits",
        "value": "Primary Splits",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary/Intermediate Splits",
        "value": "Primary/Intermediate Splits",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Splits",
        "value": "Intermediate Splits",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Graduation (Grades 11-12)",
        "value": "Graduation (Grades 11-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Multi-Grade Splits (Grade 7 to 12)",
        "value": "Multi-Grade Splits (Grade 7 to 12)",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd35",
    "sdNumber": 35,
    "name": "SD35 Langley (LTA)",
    "localName": "Langley Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.5.b.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "CA excerpt (Article D.2 Mainstreaming and Integration) contains procedural language and definitions only; no specific numeric composition limit found.",
    "needsReview": true,
    "reviewNote": "No local class size table found in CA excerpt. No specific numeric composition limit found in D.2 excerpt. Both class size and composition data need full CA review.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd36",
    "sdNumber": 36,
    "name": "SD36 Surrey (STA)",
    "localName": "Surrey Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.4.b.ii.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.4.b.ii",
    "compositionNote": "Not more than two (2) low incidence students with special needs will be enrolled in a 'regular' class. Not more than one (1) high incidence (Severe Behaviour) student with special needs will be enrolled in a 'regular' class. When a high incidence (Severe Behaviour) student with special needs is enrolled in a 'regular' class, only one (1) low incidence special education student may be enrolled in that class.",
    "needsReview": true,
    "reviewNote": "CA excerpt begins mid-sentence (\"of class size limits in Article D.1\") \u2014 full class size table was not captured. Class size data beyond K-3 requires verification. Composition limits verified from excerpt (max 2 low incidence / max 1 Severe Behaviour).",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ],
    "s2Categories": [
      {
        "id": "s2Low",
        "label": "Low Incidence"
      },
      {
        "id": "s2High",
        "label": "High Incidence (Severe Behaviour)"
      }
    ],
    "s2Rule": "Max 2 Low Incidence per class. Max 1 High Incidence (Severe Behaviour) per class. If any High Incidence student is present, the Low Incidence cap drops to 1."
  },
  {
    "id": "sd37",
    "sdNumber": 37,
    "name": "SD37 Delta (DTA)",
    "localName": "Delta Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.10.a.i.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.10.a.i",
    "compositionNote": "Class composition will be a factor in determining placement and a maximum of three (3) students may be included in a regular class.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate",
        "value": "Intermediate",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Science/Life Skills and Family Management",
        "value": "Science/Life Skills and Family Management",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics/Industrial Education",
        "value": "Home Economics/Industrial Education",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary",
        "value": "Secondary",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd38",
    "sdNumber": 38,
    "name": "SD38 Richmond (RTA)",
    "localName": "Richmond Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.5.b.v.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.5.b.v",
    "compositionNote": "A maximum of three (3) identified students with special needs, as per Article D.2.1, may be included in any one regular class from Articles D.2.5.b.i through D.2.5.b.iv. Sub-limits also apply: a maximum of one (1) low incidence student (D.2.5.b.i) and a maximum of three (3) high incidence students (D.2.5.b.ii) per class.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Lower Intermediate (4-7)",
        "value": "Lower Intermediate (4-7)",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Upper Intermediate/Graduation (8-12)",
        "value": "Upper Intermediate/Graduation (8-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science Labs",
        "value": "Science Labs",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economic Labs, Grade 8 Industrial Education/Home Economics",
        "value": "Home Economic Labs, Grade 8 Industrial Education/Home Economics",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Drafting, Electronics, Secondary Modified",
        "value": "Drafting, Electronics, Secondary Modified",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grades 9-12 Shops (Woodwork, Metalwork, Automotive, Power Mechanics)",
        "value": "Grades 9-12 Shops (Woodwork, Metalwork, Automotive, Power Mechanics)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "ELL",
        "value": "ELL",
        "maxSize": 20,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Integrated Program (FPE)",
        "value": "Secondary Integrated Program (FPE)",
        "maxSize": 15,
        "pValue": null,
        "isSecondary": true
      }
    ],
    "s2Categories": [
      {
        "id": "s2Low",
        "label": "Low Incidence"
      },
      {
        "id": "s2High",
        "label": "High Incidence"
      }
    ],
    "s2Rule": "Max 3 total designated students. Sub-limits: max 1 Low Incidence, max 3 High Incidence per class."
  },
  {
    "id": "sd39-elem",
    "sdNumber": 39,
    "name": "SD39 Vancouver Elementary (VESTA)",
    "localName": "Vancouver Elementary School Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Articles D.1 and D.2 both state 'REMOVED BY LEGISLATION'. Provincial limits only apply.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Article D.1 and D.2 both state 'REMOVED BY LEGISLATION'. No local class size or composition language.",
    "needsReview": true,
    "reviewNote": "Article D.1 and D.2 both state 'REMOVED BY LEGISLATION'. Class sizes are governed by provincial limits only. No local class size or composition language.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd39-sec",
    "sdNumber": 39,
    "name": "SD39 Vancouver Secondary (VSTA)",
    "localName": "Vancouver Secondary Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Articles D.1 and D.2 both state 'REMOVED BY LEGISLATION'. No local secondary class size limits.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Article D.1 and D.2 both state 'REMOVED BY LEGISLATION'. No local class size or composition language.",
    "needsReview": true,
    "reviewNote": "Article D.1 and D.2 both state 'REMOVED BY LEGISLATION'. Class sizes are governed by provincial limits only. No local class size or composition language.",
    "classTypes": []
  },
  {
    "id": "sd40",
    "sdNumber": 40,
    "name": "SD40 New Westminster (NWTU)",
    "localName": "New Westminster Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.1.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.1",
    "compositionNote": "In any case the number of High Incidence/Low Cost or Low Incidence/High Cost students, excepting Gifted and Talented, in any one class shall not exceed three (3). If there are three (3) High Incidence Low Cost/Low Incidence High Cost students in a class the size will be reduced by at least two (2) from the numbers listed above.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Ungraded/Split (4,5,6,7)",
        "value": "Intermediate Ungraded/Split (4,5,6,7)",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (straight grade) (4,5,6,7)",
        "value": "Intermediate (straight grade) (4,5,6,7)",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multilevel",
        "value": "Multilevel",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Lab Sciences",
        "value": "Lab Sciences",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Shop",
        "value": "Shop",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Modified Class (Secondary)",
        "value": "Modified Class (Secondary)",
        "maxSize": 16,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Communications 11",
        "value": "Communications 11",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics Labs/Ind.Ed./Art",
        "value": "Home Economics Labs/Ind.Ed./Art",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Computer",
        "value": "Computer",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "E.S.L.",
        "value": "E.S.L.",
        "maxSize": 15,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any Other Class (4-12)",
        "value": "Any Other Class (4-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ],
    "s2Categories": [
      {
        "id": "s2Low",
        "label": "Low Incidence (High Cost)"
      },
      {
        "id": "s2High",
        "label": "High Incidence (Low Cost)"
      }
    ]
  },
  {
    "id": "sd41",
    "sdNumber": 41,
    "name": "SD41 Burnaby (BTA)",
    "localName": "Burnaby Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "The Board agrees that the following guidelines shall be followed in integrating a student with special needs into regular classrooms. Special consideration shall be given for the provision of additional resources that are required to integrate an exceptional student into a classroom bearing in mind the need to maximize educational benefits with the resources available at any time.",
    "needsReview": true,
    "reviewNote": "No local class size table found in CA excerpt \u2014 only D.2 Class Composition and Inclusion content present. Class size data beyond K-3 needs verification. No specific numeric composition limit found in D.2.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd42",
    "sdNumber": 42,
    "name": "SD42 Maple Ridge-Pitt Meadows (MRTA)",
    "localName": "Maple Ridge Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.7.a.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.7.a",
    "compositionNote": "No more than three (3) exceptional students shall be integrated at the same time in a regular classroom. Classes with three (3) exceptional students shall have their class size guideline reduced by two (2) (exception Grade 11 and 12 non-academic electives) and classes with two (2) exceptional students shall have their class size maximum reduced by one (1).",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Multi-age",
        "value": "Intermediate Multi-age",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Traditional",
        "value": "Intermediate Traditional",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Traditional (split)",
        "value": "Intermediate Traditional (split)",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Technical Ed. Lab. (I.E. Lab.)",
        "value": "Technical Ed. Lab. (I.E. Lab.)",
        "maxSize": 22,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "\"Secondary\" English",
        "value": "\"Secondary\" English",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics",
        "value": "Home Economics",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Junior Science",
        "value": "Junior Science",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Senior Science",
        "value": "Senior Science",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Computer",
        "value": "Computer",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Life Skills",
        "value": "Life Skills",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any Other Class",
        "value": "Any Other Class",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd43",
    "sdNumber": 43,
    "name": "SD43 Coquitlam (CTA)",
    "localName": "Coquitlam Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.5.a.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.5.a",
    "compositionNote": "No more than three (3) special needs students shall be integrated at the same time in a regular classroom. Classes with three (3) special needs students shall have their class size maximum reduced by two (2).",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (grades 4, 5, 6, 7)",
        "value": "Intermediate (grades 4, 5, 6, 7)",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary (grades 8\u201312)",
        "value": "Secondary (grades 8\u201312)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd44",
    "sdNumber": 44,
    "name": "SD44 North Vancouver (NVTA)",
    "localName": "North Vancouver Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.1.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.1",
    "compositionNote": "No more than three (3) students with special needs shall be integrated into a single regular classroom. Only one (1) of these may be from a low incidence category or from Category 1.17 (Severe Behaviour).",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 3/4 split",
        "value": "Grades 3/4 split",
        "maxSize": 23,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate split/multi-age (grades 4\u20137)",
        "value": "Intermediate split/multi-age (grades 4\u20137)",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Regular classes (grades 4\u20137)",
        "value": "Regular classes (grades 4\u20137)",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Regular classes (grades 8\u201312)",
        "value": "Regular classes (grades 8\u201312)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ],
    "s2Categories": [
      {
        "id": "s2Low",
        "label": "Low Incidence"
      },
      {
        "id": "s2Other",
        "label": "Other Special Needs"
      }
    ],
    "s2Rule": "Max 3 total designated students. Only 1 may be Low Incidence."
  },
  {
    "id": "sd45",
    "sdNumber": 45,
    "name": "SD45 West Vancouver (WVTA)",
    "localName": "West Vancouver Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Article D.1 states no local language (only LOU No. 12). Article D.2 states no local language.",
    "compositionLimit": null,
    "compositionArticle": "",
    "compositionNote": "Article D.2 states no local composition language. No numerical limit on designated students per class is stated.",
    "needsReview": true,
    "reviewNote": "No local D.1 language beyond K-3 and no local D.2 composition language.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd46",
    "sdNumber": 46,
    "name": "SD46 Sunshine Coast (SCTA)",
    "localName": "Sunshine Coast Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.14.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.14",
    "compositionNote": "To ensure that all students receive adequate attention, no more than two (2) special needs students shall be placed in a single classroom. For the purposes of this article 'Special Needs Students' shall be those identified as 'Low Incidence' in Ministry of Education guidelines.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Grades 4\u20137",
        "value": "Intermediate Grades 4\u20137",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multi-age Intermediate",
        "value": "Multi-age Intermediate",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary Science, Home Economics, Industrial Education",
        "value": "Secondary Science, Home Economics, Industrial Education",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary (other)",
        "value": "Secondary (other)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd47",
    "sdNumber": 47,
    "name": "SD47 Powell River (PRDTA)",
    "localName": "Powell River District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.15.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.15",
    "compositionNote": "To ensure that all students receive adequate attention, no more than two students as identified in D.2.14 shall be integrated at the same time in a regular classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 4, 5, 6, 7",
        "value": "Grades 4, 5, 6, 7",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 8, 9, 10",
        "value": "Grades 8, 9, 10",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grades 11, 12",
        "value": "Grades 11, 12",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd48",
    "sdNumber": 48,
    "name": "SD48 Sea to Sky (SSTA)",
    "localName": "Sea to Sky Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Article D.2 contains procedural language only with no specific composition number.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Article D.2 outlines placement procedures for special needs students via the School Based Team but does not specify a numerical limit on designated students per class.",
    "needsReview": true,
    "reviewNote": "No specific composition number in D.2; only procedural language.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate \u2013 ungraded, split",
        "value": "Intermediate \u2013 ungraded, split",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary \u2013 English",
        "value": "Secondary \u2013 English",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary \u2013 Labs (Ind. Educ./Tech. Ed., Home Econ., Sr. Science)",
        "value": "Secondary \u2013 Labs (Ind. Educ./Tech. Ed., Home Econ., Sr. Science)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class",
        "value": "Any other class",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd49",
    "sdNumber": 49,
    "name": "SD49 Central Coast (CCTA)",
    "localName": "Central Coast Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Article D.2 contains mainstreaming procedural language with no specific composition number.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Article D.2 addresses mainstreaming of pupils with exceptional needs through the Administrative Officer and Special Education Committee, but specifies no numerical limit on designated students per class.",
    "needsReview": true,
    "reviewNote": "No specific composition number in D.2; only procedural mainstreaming language.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Kindergarten split class",
        "value": "Kindergarten split class",
        "maxSize": 15,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary split (excluding Kindergarten)",
        "value": "Primary split (excluding Kindergarten)",
        "maxSize": 21,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate",
        "value": "Intermediate",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (split)",
        "value": "Intermediate (split)",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 8\u201312",
        "value": "Grades 8\u201312",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Science Lab",
        "value": "Secondary Science Lab",
        "maxSize": 22,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Home Economics",
        "value": "Secondary Home Economics",
        "maxSize": 20,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Industrial Education",
        "value": "Secondary Industrial Education",
        "maxSize": 20,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd50",
    "sdNumber": 50,
    "name": "SD50 Haida Gwaii (HGTA)",
    "localName": "Haida Gwaii Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Article D.1 contains only LOU No. 12 K\u20133 limits with no local upper-grade language. Article D.2 contains procedural language with no specific composition number.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Article D.2 outlines a Step 1/Step 2 procedure for teachers concerned about class composition, referencing resolution through the principal and Superintendent, but specifies no numerical limit on designated students per class.",
    "needsReview": true,
    "reviewNote": "No local D.1 language for grades 4\u201312 and no specific composition number in D.2.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd51",
    "sdNumber": 51,
    "name": "SD51 Boundary (BDTA)",
    "localName": "Boundary District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Article D.2.3.a uses 'best efforts' language for a limit of two integrated students.",
    "compositionLimit": null,
    "compositionArticle": "D.2.3.a",
    "compositionNote": "The Board shall make best efforts to limit to two (2) the number of such students integrated into any regular class.",
    "needsReview": true,
    "reviewNote": "D.2.3.a uses 'best efforts' language, not a hard numerical composition limit.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Class",
        "value": "Intermediate Class",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Multiage (Intermediate 1\u20134)",
        "value": "Intermediate Multiage (Intermediate 1\u20134)",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary",
        "value": "Secondary",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd52",
    "sdNumber": 52,
    "name": "SD52 Prince Rupert (PRDTU)",
    "localName": "Prince Rupert District Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 and LOU No. 12. Kindergarten and Grades 1-3 from provincial language.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Refer to Article D.2 for class composition and inclusion language. Specific integration limits not stated numerically \u2014 flag for review.",
    "needsReview": true,
    "reviewNote": "Composition article D.2 does not state a specific numerical integration limit. Needs manual review of full article language.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 3/4 Split",
        "value": "Grade 3/4 Split",
        "maxSize": 24,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Split (multi-age)",
        "value": "Intermediate Split",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (single grade)",
        "value": "Intermediate",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English/Social Studies",
        "value": "Secondary English/Social Studies",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Multi-course Secondary Humanities",
        "value": "Multi-course Secondary Humanities",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Industrial Ed / Home Ec / Lab Science",
        "value": "Industrial Ed/Home Ec/Lab Science",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary/Graduation",
        "value": "Secondary/Graduation",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ],
    "s2Categories": [
      {
        "id": "s2High",
        "label": "High Incidence"
      },
      {
        "id": "s2Low",
        "label": "Low Incidence"
      }
    ]
  },
  {
    "id": "sd53",
    "sdNumber": 53,
    "name": "SD53 Okanagan Similkameen (SOSTU)",
    "localName": "South Okanagan Similkameen Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1. Composition per Article D.2.8.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.8",
    "compositionNote": "The maximum number of exceptional students as identified in D.2.1 shall be two in a single class unless additional exceptional students are requested by the teacher.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary (3,4) Split class",
        "value": "Primary (3,4) Split class",
        "maxSize": 23,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (4,5,6,7) Split classes",
        "value": "Intermediate (4,5,6,7) Split classes",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English class",
        "value": "Secondary English class",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Science",
        "value": "Secondary Science",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Home Economics",
        "value": "Secondary Home Economics",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Tech. Ed. Lab (I.E. Lab)",
        "value": "Secondary Tech. Ed. Lab (I.E. Lab)",
        "maxSize": 22,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class (4\u201312)",
        "value": "Any other class (4\u201312)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd54",
    "sdNumber": 54,
    "name": "SD54 Bulkley Valley (BVTU)",
    "localName": "Bulkley Valley Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Dollar value per minute fixed at $2.401 for 2025-2026.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.9",
    "compositionNote": "No more than two (2) special needs students shall be integrated into one classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Multi-age",
        "value": "Intermediate Multi-age",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate",
        "value": "Intermediate",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "En./S.S./French",
        "value": "En./S.S./French",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Labs and Shops",
        "value": "Labs and Shops",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary/Graduation",
        "value": "Secondary/Graduation",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd57",
    "sdNumber": 57,
    "name": "SD57 Prince George (PGDTA)",
    "localName": "Prince George District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and Article D.1 local language (Gr 4-12). Composition per Article D.2 (procedural only, no numeric limit).",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Article D.2 outlines a placement and consultation process for students who significantly affect classroom management, routines, or instruction. No numeric cap on designated students per class is stated.",
    "needsReview": true,
    "reviewNote": "D.2 contains only procedural/consultation language with no hard numeric composition limit.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 4, 5, 6 and 7",
        "value": "Grades 4, 5, 6 and 7",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 8-12",
        "value": "Grades 8-12",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd58",
    "sdNumber": 58,
    "name": "SD58 Nicola-Similkameen (NVTU/PDTU)",
    "localName": "Nicola Valley Teachers' Union / Princeton District Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1.3 class size guidelines use 'where possible' language (not hard limits). Composition limit per D.1.5.",
    "compositionLimit": 2,
    "compositionArticle": "D.1.5",
    "compositionNote": "Administrative officers shall make reasonable efforts to ensure that no more than two (2) low incidence or severe behavioural students (as identified by the Superintendent of Schools and agreed to by the Ministry of Education) are placed in any one regular class.",
    "needsReview": true,
    "reviewNote": "D.1.3 class size guidelines use 'where possible' language rather than hard limits. D.1.5 composition limit uses 'reasonable efforts' rather than an absolute cap.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Split",
        "value": "Intermediate Split",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate",
        "value": "Intermediate",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary 4/Intermediate 1",
        "value": "Primary 4/Intermediate 1",
        "maxSize": 24,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Graduation",
        "value": "Graduation",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Shops",
        "value": "Shops",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Labs",
        "value": "Labs",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd59",
    "sdNumber": 59,
    "name": "SD59 Peace River South (PRSTA)",
    "localName": "Peace River South Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1 sets overall cap of 30 students per class. Composition per D.2.1.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.1",
    "compositionNote": "No class is to enroll more than two (2) students with exceptional needs as defined in Article D.2.3.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Any class (maximum)",
        "value": "Any class (maximum)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd60",
    "sdNumber": 60,
    "name": "SD60 Peace River North (PRNDTA)",
    "localName": "Peace River North District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1 class size figures are guidelines. Composition per D.2.1 uses 'best efforts' language.",
    "compositionLimit": null,
    "compositionArticle": "D.2.1",
    "compositionNote": "As per LOU No. 12, best efforts will be made to limit the composition of a class to two special needs students.",
    "needsReview": true,
    "reviewNote": "Composition clause uses 'best efforts' language \u2014 not a hard limit. Class size figures in D.1 are described as 'guidelines'.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Class",
        "value": "Intermediate Class",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Multi-Age Class",
        "value": "Intermediate Multi-Age Class",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary Classes",
        "value": "Secondary Classes",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Technology Education",
        "value": "Technology Education",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science Laboratories",
        "value": "Science Laboratories",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics Laboratories",
        "value": "Home Economics Laboratories",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd61",
    "sdNumber": 61,
    "name": "SD61 Greater Victoria (GVTA)",
    "localName": "Greater Victoria Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1 sets limits for Gr 4-12. Composition per D.2.11 uses 'every effort' language.",
    "compositionLimit": null,
    "compositionArticle": "D.2.11",
    "compositionNote": "The Board shall make every effort to ensure that no more than two students with special needs are integrated in any regular classroom at the same time.",
    "needsReview": true,
    "reviewNote": "D.2.11 composition clause uses 'every effort' language rather than an absolute hard limit.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Grades 4-7)",
        "value": "Intermediate (Grades 4-7)",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multiage Intermediate Split",
        "value": "Multiage Intermediate Split",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary (8 to 12)",
        "value": "Secondary (8 to 12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "English/Socials (Secondary)",
        "value": "English/Socials (Secondary)",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Computer Science (Secondary)",
        "value": "Computer Science (Secondary)",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science Labs/Home Ec. (Secondary)",
        "value": "Science Labs/Home Ec. (Secondary)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "I.E. Workshop (Secondary)",
        "value": "I.E. Workshop (Secondary)",
        "maxSize": 22,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Min. Essentials (Secondary)",
        "value": "Min. Essentials (Secondary)",
        "maxSize": 20,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd62",
    "sdNumber": 62,
    "name": "SD62 Sooke (STA)",
    "localName": "Sooke Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1 sets limits for Gr 4-12. D.2 contains no numeric composition limit.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Article D.2 establishes a Joint Integration Implementation Committee and School Based Team process for determining appropriate support levels for students with special needs. No numeric per-class composition cap is stated.",
    "needsReview": true,
    "reviewNote": "D.2 contains no numeric composition limit; placement decisions are determined through the Joint Integration Implementation Committee and School Based Team process.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Grades 4-7)",
        "value": "Intermediate (Grades 4-7)",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Multi-aged Groupings)",
        "value": "Intermediate (Multi-aged Groupings)",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class",
        "value": "Any other class",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Labs and workshops (safety factor)",
        "value": "Labs and workshops (safety factor)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd63",
    "sdNumber": 63,
    "name": "SD63 Saanich (STA)",
    "localName": "Saanich Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1.1 sets limits for Gr 3-12. Composition per D.2.4.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.4",
    "compositionNote": "The Board shall limit to two (2) the number of such students integrated into any regular class.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Split/Primary Intermediate (Grades 3 & 4)",
        "value": "Split/Primary Intermediate (Grades 3 & 4)",
        "maxSize": 24,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Class (Grades 4 & 5)",
        "value": "Intermediate Class (Grades 4 & 5)",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Split/Multi Intermediate Class (Grades 4 & 5)",
        "value": "Split/Multi Intermediate Class (Grades 4 & 5)",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Middle School Class",
        "value": "Middle School Class",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Middle School Split Class",
        "value": "Middle School Split Class",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English/French Immersion Language Arts",
        "value": "Secondary English/French Immersion Language Arts",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Multi-Programmed/Minimum Essentials (Gr. 9-12)",
        "value": "Multi-Programmed/Minimum Essentials (Gr. 9-12)",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class (Grade 9 through 12)",
        "value": "Any other class (Grade 9 through 12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd64",
    "sdNumber": 64,
    "name": "SD64 Gulf Islands (GITA)",
    "localName": "Gulf Islands Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "K=20 from LOU No. 12; Gr 1-3=21 per local Article D.1.5. Composition per D.2.3.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.3",
    "compositionNote": "Normally a maximum of two (2) children may be integrated into a single classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades 1-3",
        "value": "Grades 1-3",
        "maxSize": 21,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Elementary Intermediate",
        "value": "Elementary Intermediate",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary",
        "value": "Secondary",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd67",
    "sdNumber": 67,
    "name": "SD67 Okanagan Skaha (OSTU)",
    "localName": "Okanagan Skaha Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1.1 sets additional maximums. Composition per D.2.12.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.12",
    "compositionNote": "To ensure that all students receive adequate attention, no more than two (2) students with special educational needs shall normally be integrated at the same time into any one (1) regular classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Kindergarten/Grade 1 class (combined)",
        "value": "Kindergarten/Grade 1 class (combined)",
        "maxSize": 15,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary (Grade 3,4) Split class",
        "value": "Primary (Grade 3,4) Split class",
        "maxSize": 23,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (4,5,6,7) Split Classes",
        "value": "Intermediate (4,5,6,7) Split Classes",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English Class",
        "value": "Secondary English Class",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science (Secondary)",
        "value": "Science (Secondary)",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics (Secondary)",
        "value": "Home Economics (Secondary)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Tech.Ed. Lab / I.E. Lab (Secondary)",
        "value": "Tech.Ed. Lab / I.E. Lab (Secondary)",
        "maxSize": 22,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class (4-12)",
        "value": "Any other class (4-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd68",
    "sdNumber": 68,
    "name": "SD68 Nanaimo-Ladysmith (NDTA)",
    "localName": "Nanaimo District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "K-3 limits from LOU No. 12. Local D.1.5 sets maximums for Gr 3-12. Composition per D.2.3.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.3",
    "compositionNote": "Normally, no more than two (2) students with exceptional needs shall be registered into any regular class at the same time.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary/Intermediate Grade 3-4",
        "value": "Primary/Intermediate Grade 3-4",
        "maxSize": 24,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 4-7",
        "value": "Grade 4-7",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Split",
        "value": "Intermediate Split",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 8-10",
        "value": "Grade 8-10",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 8-10 English and Social Studies",
        "value": "Grade 8-10 English and Social Studies",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 8-10 Lab Sciences",
        "value": "Grade 8-10 Lab Sciences",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 9-10 Home Ec. Lab courses",
        "value": "Grade 9-10 Home Ec. Lab courses",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 9-10 Industrial Ed. shops (except Drafting)",
        "value": "Grade 9-10 Industrial Ed. shops (except Drafting)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 11-12",
        "value": "Grade 11-12",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 11-12 English and Social Studies",
        "value": "Grade 11-12 English and Social Studies",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 11-12 Lab Sciences",
        "value": "Grade 11-12 Lab Sciences",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 11-12 Home Ec. Lab courses",
        "value": "Grade 11-12 Home Ec. Lab courses",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grade 11-12 Industrial Ed. shops (except Drafting)",
        "value": "Grade 11-12 Industrial Ed. shops (except Drafting)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd69",
    "sdNumber": 69,
    "name": "SD69 Qualicum (MATA)",
    "localName": "Mount Arrowsmith Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and local Article D.1. Composition per Article D.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "The school-based team shall review the needs of the exceptional students assigned to the school. It shall determine appropriate placements within the school based on current numbers of exceptional students integrated into each regular classroom and based on other educational considerations.",
    "needsReview": true,
    "reviewNote": "No specific numeric composition limit found in D.2; language is procedural (school-based team process) with no hard cap on number of designated students per classroom.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multi-year Intermediate",
        "value": "Multi-year Intermediate",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate",
        "value": "Intermediate",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary Combined Classes",
        "value": "Secondary Combined Classes",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Intermediate",
        "value": "Secondary Intermediate",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Graduation",
        "value": "Secondary Graduation",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd70",
    "sdNumber": 70,
    "name": "SD70 Pacific Rim (ADTU)",
    "localName": "Alberni District Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and local Article D.1. Composition per Article D.1.9.",
    "compositionLimit": null,
    "compositionArticle": "D.1.9",
    "compositionNote": "In no case shall there be more than two (2) special needs students integrated into a classroom except where no practical alternative exists, and assistance is provided.",
    "needsReview": true,
    "reviewNote": "Composition language at D.1.9 includes exception clause (\"except where no practical alternative exists\") making the two-student limit non-binding; not a hard cap.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multi-grade Primary \u2013 Grade 3/4",
        "value": "Multi-grade Primary \u2013 Grade 3/4",
        "maxSize": 23,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades Four, Five and Six",
        "value": "Grades Four, Five and Six",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multigrade (Lower Intermediate)",
        "value": "Multigrade (Lower Intermediate)",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grades Seven, Eight and Nine",
        "value": "Grades Seven, Eight and Nine",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Multigrade (Upper Intermediate)",
        "value": "Multigrade (Upper Intermediate)",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Grades Ten, Eleven and Twelve",
        "value": "Grades Ten, Eleven and Twelve",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Industrial Education (Jr. Sec.)",
        "value": "Industrial Education (Jr. Sec.)",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Industrial Education (Sr. Sec.)",
        "value": "Industrial Education (Sr. Sec.)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science (Sr. Sec.)",
        "value": "Science (Sr. Sec.)",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "English/Social Studies (Sr. Sec.)",
        "value": "English/Social Studies (Sr. Sec.)",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd71",
    "sdNumber": 71,
    "name": "SD71 Comox Valley (CDTA)",
    "localName": "Comox District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and local Article D.1. Composition per Article D.2.4.c.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.4.c",
    "compositionNote": "A maximum of two (2) special needs students may be integrated into a single elementary school class. A maximum of two (2) students classified under Programs 1.18 and 1.19 may be integrated into a single junior school class.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Grades 4-6)",
        "value": "Intermediate (Grades 4-6)",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multi-grade Intermediate",
        "value": "Multi-grade Intermediate",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary (Grades 7-12)",
        "value": "Secondary (Grades 7-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Science",
        "value": "Secondary Science",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "IE/Home Economics (Grades 8-12)",
        "value": "IE/Home Economics (Grades 8-12)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd72",
    "sdNumber": 72,
    "name": "SD72 Campbell River (CRDTA)",
    "localName": "Campbell River District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Provincial baseline only. No local class size or composition provisions found in available CA.",
    "compositionLimit": null,
    "compositionArticle": "",
    "compositionNote": "",
    "needsReview": true,
    "reviewNote": "CA PDF appears to be 2013-2019 working document with no local class size/composition provisions found. Verify current 2022-2025 agreement.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd73",
    "sdNumber": 73,
    "name": "SD73 Kamloops-Thompson (KTTA)",
    "localName": "Kamloops Thompson Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and local Article D.1. Composition per Article D.2.3.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.3",
    "compositionNote": "A maximum of three special needs students may be integrated into a single school class with support.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Elementary Multi-Intermediate",
        "value": "Elementary Multi-Intermediate",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Single Grade",
        "value": "Intermediate Single Grade",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English, Science Lab Class",
        "value": "Secondary English, Science Lab Class",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics",
        "value": "Home Economics",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Industrial Education",
        "value": "Industrial Education",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Other Secondary Classes",
        "value": "Other Secondary Classes",
        "maxSize": 29,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd74",
    "sdNumber": 74,
    "name": "SD74 Gold Trail (GTTA)",
    "localName": "Gold Trail Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3). Local D.1.1 applies to Lillooet schools only; non-Lillooet schools governed by School Act s.76.1. Composition per Article D.2.2.",
    "compositionLimit": null,
    "compositionArticle": "D.2.2",
    "compositionNote": "The Board and the Association recognize that it is educationally desirable to limit the number of exceptional students in a regular class.",
    "needsReview": true,
    "reviewNote": "No specific numeric composition limit found; D.2.2.g states only that it is 'educationally desirable to limit the number of exceptional students' without a hard cap. Local class size provisions in D.1.1 apply to Lillooet schools only.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Kindergarten and others (Lillooet)",
        "value": "Kindergarten and others (Lillooet)",
        "maxSize": 18,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary Multi-Year (Lillooet)",
        "value": "Primary Multi-Year (Lillooet)",
        "maxSize": 21,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Straight (Lillooet)",
        "value": "Intermediate Straight (Lillooet)",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Multi-Year (Lillooet)",
        "value": "Intermediate Multi-Year (Lillooet)",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary (Lillooet)",
        "value": "Secondary (Lillooet)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English Class (Lillooet)",
        "value": "Secondary English Class (Lillooet)",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Industrial Ed/Home Economics Class (Lillooet)",
        "value": "Industrial Ed/Home Economics Class (Lillooet)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd75",
    "sdNumber": 75,
    "name": "SD75 Mission (MTU)",
    "localName": "Mission Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and local Article D.1. Composition per Article D.2.5.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.5",
    "compositionNote": "The integration of students with special needs, who fall into the categories included in D.2.9, will result in a smaller class size by at least one than the numbers listed in Article D.1. In any case, the number of students identified in D.2.9D in any one class shall not exceed three (3), of which only one may be severe behavior disordered as defined in D.2.9.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate class",
        "value": "Intermediate class",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Split",
        "value": "Intermediate Split",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multi-age (4-7)",
        "value": "Multi-age (4-7)",
        "maxSize": 23,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary",
        "value": "Secondary",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Social Studies",
        "value": "Secondary Social Studies",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science/Home Ec",
        "value": "Science/Home Ec",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Industrial Education",
        "value": "Industrial Education",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd78",
    "sdNumber": 78,
    "name": "SD78 Fraser-Cascade (FCTA)",
    "localName": "Fraser-Cascade Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and local Article D.1 (separate East Side and West Side provisions). Composition per Article D.1.7 (East Side).",
    "compositionLimit": 3,
    "compositionArticle": "D.1.7",
    "compositionNote": "The integration of students with special needs who fall into the categories for which Function 3 funds are provided, excepting Gifted & Talented, will result in smaller class size by at least one than the numbers listed above. In any case the number of Function 3 students, excepting Gifted & Talented, in any one class shall not exceed three (3).",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Ungraded Split (4,5,6,7)",
        "value": "Intermediate Ungraded Split (4,5,6,7)",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (4,5,6,7)",
        "value": "Intermediate (4,5,6,7)",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Lab Sciences",
        "value": "Lab Sciences",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Shop",
        "value": "Shop",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Modified Class (Secondary)",
        "value": "Modified Class (Secondary)",
        "maxSize": 16,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Communications 11/12",
        "value": "Communications 11/12",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics Labs / Ind. Ed. / Art",
        "value": "Home Economics Labs / Ind. Ed. / Art",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any Other Class (4-12)",
        "value": "Any Other Class (4-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd79",
    "sdNumber": 79,
    "name": "SD79 Cowichan Valley (CVTF)",
    "localName": "Cowichan Valley Teachers' Federation",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per LOU No. 12 (K-3) and local Article D.1. Composition per Article D.2.4.d.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.4.d",
    "compositionNote": "No more than three students with special needs shall be enrolled in a regular class without the endorsement of the School Based Team. No more than one low incidence student, as defined in Article D.2.2.a.(i,ii,iii,vii) and one student with severe behaviour disorders D.2.2.b,(iii) shall be enrolled in a regular class.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate & Graduation",
        "value": "Intermediate & Graduation",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate / Graduation Split",
        "value": "Intermediate / Graduation Split",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      }
    ],
    "s2Categories": [
      {
        "id": "s2Low",
        "label": "Low Incidence"
      },
      {
        "id": "s2Behav",
        "label": "Severe Behaviour"
      }
    ],
    "s2Rule": "Max 3 total. No more than 1 Low Incidence and no more than 1 Severe Behaviour per class."
  },
  {
    "id": "sd81",
    "sdNumber": 81,
    "name": "SD81 Fort Nelson (FNDTA)",
    "localName": "Fort Nelson District Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size per LOU No. 12 provincial baseline (K-3) only; no local Article D.1 class size provisions found in CA excerpt. Composition per D.2 (procedural only).",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "The Association and the Board agree that class composition is critical to effective education. A teacher who is concerned that the physical environment or composition of their class seriously affects normal exceptions for student learning, or where safety is a factor, has the responsibility to bring their concerns to the attention of the principal of the school.",
    "needsReview": true,
    "reviewNote": "No specific numeric composition limit found in D.2; language is procedural only with no hard cap on number of designated students per classroom.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd82",
    "sdNumber": 82,
    "name": "SD82 Coast Mountains (CMTF)",
    "localName": "Coast Mountains Teachers' Federation",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 (LOU No. 12 for K-3; local language for other grades). Composition per Article D.2.1.f.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.1.f",
    "compositionNote": "No more than two (2) students with special needs, low incidence category, as defined in Article D.2.1 shall be placed in a regular classroom. When two (2) such students are placed according to this provision the class size shall not exceed the limit defined in Article D.1.1.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Kindergarten/Grade 1 Split",
        "value": "Kindergarten/Gr. 1 split",
        "maxSize": 16,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary Split Class",
        "value": "Primary split class",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 3/4 Split",
        "value": "Grade 3/4 split",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Class",
        "value": "Intermediate class",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Split Class",
        "value": "Intermediate split class",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary Classes",
        "value": "Secondary classes",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary English Classes",
        "value": "Secondary English Classes",
        "maxSize": 27,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Split Classes",
        "value": "Secondary Split Classes",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Science 8, 9, 10",
        "value": "Science 8, 9, 10",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Biology/Chemistry/Physics/Earth Science 11 & 12",
        "value": "Biology 11 & 12, Chemistry 11 & 12, Earth Science 11, Geology 12, Physics 11 & 12",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Home Economics (foods or textiles)",
        "value": "Home Economics (foods or textiles)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Industrial Education",
        "value": "Industrial Education (auto, metalwork, drafting, power mechanics, woodwork)",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Workshop Classes such as Drama",
        "value": "Workshop Classes such as Drama",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd83",
    "sdNumber": 83,
    "name": "SD83 North Okanagan-Shuswap (NOSTA)",
    "localName": "North Okanagan-Shuswap Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 (LOU No. 12 for K-3; local language for other grades). Composition per Article D.2.1.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.1",
    "compositionNote": "In any case there shall be no more than two (2) Function three (3) pupils, excluding Gifted and Talented.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Grade 4-7)",
        "value": "Intermediate (Grade 4-7)",
        "maxSize": 28,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English",
        "value": "Secondary English",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Laboratory",
        "value": "Laboratory",
        "maxSize": 26,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Shop & Home Ec.",
        "value": "Shop & Home Ec.",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other Class (Grade 8-12)",
        "value": "Any other Class (Grade 8-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd84",
    "sdNumber": 84,
    "name": "SD84 Vancouver Island West (VIWTU)",
    "localName": "Vancouver Island West Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 (LOU No. 12 for K-3; local language per D.1.1 for other grades). Composition per Article D.2.6.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.6",
    "compositionNote": "In no case shall there be more than two (2) special needs students integrated into a classroom.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Multigraded/Ungraded Intermediate Class",
        "value": "Multigraded/Ungraded Intermediate Class",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Split Classes (4,5,6,7)",
        "value": "Intermediate Split Classes (4,5,6,7)",
        "maxSize": 26,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary Humanities Classes (English and Socials)",
        "value": "Secondary Humanities Classes (English and Socials)",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other Class (4-12)",
        "value": "Any other Class (4-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Shops and Laboratories",
        "value": "Shops and Laboratories",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd85",
    "sdNumber": 85,
    "name": "SD85 Vancouver Island North (VINTA)",
    "localName": "Vancouver Island North Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 (LOU No. 12 for K-3; local language for other grades). Composition per Article D.2.5.",
    "compositionLimit": 2,
    "compositionArticle": "D.2.5",
    "compositionNote": "There shall be a maximum of two low incidence exceptional students in a regular classroom and only one can be autistic.",
    "needsReview": false,
    "reviewNote": "",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Kindergarten/Grade 1",
        "value": "Kindergarten/Grade 1",
        "maxSize": 17,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary split (grades 1/2/3/4)",
        "value": "Primary split (grades 1/2/3/4)",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate split (4/5/6/7)",
        "value": "Intermediate split (4/5/6/7)",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Other (4-12)",
        "value": "Other (4 - 12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ],
    "s2Categories": [
      {
        "id": "s2Low",
        "label": "Low Incidence (non-autistic)"
      },
      {
        "id": "s2Auto",
        "label": "Autistic"
      }
    ],
    "s2Rule": "Max 2 Low Incidence exceptional students total; only 1 may be autistic."
  },
  {
    "id": "sd87",
    "sdNumber": 87,
    "name": "SD87 Stikine (STA)",
    "localName": "Stikine Teachers' Association",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 (LOU No. 12 for K-3 only; no additional local limits). Composition per Article D.2 is procedural only.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "The Local and the Board recognize the difficulties in adhering to any class size and/or composition formula.",
    "needsReview": true,
    "reviewNote": "No local class size limits beyond K-3 LOU No. 12 baseline. No specific hard composition limit found; D.2 is procedural only.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      }
    ]
  },
  {
    "id": "sd91",
    "sdNumber": 91,
    "name": "SD91 Nechako Lakes (BLNTU)",
    "localName": "Burns Lake and Nechako Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 (LOU No. 12 for K-3; local language for other grades). Composition per Article D.2 is procedural only.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "Where the teacher determines that there are students in their class who significantly affect classroom management, routines, or instruction, they shall immediately notify the principal or vice-principal.",
    "needsReview": true,
    "reviewNote": "No specific hard numerical composition limit found in Article D.2; language is procedural.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate (Grades 4-10)",
        "value": "Intermediate (Grades 4-10)",
        "maxSize": 30,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Graduation (Grades 11-12)",
        "value": "Graduation (Grades 11-12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd92",
    "sdNumber": 92,
    "name": "SD92 Nisga'a (NTU)",
    "localName": "Nisga'a Teachers' Union",
    "ratePerMinute": 2.401,
    "notes": "Class size limits per Article D.1 (LOU No. 12 for K-3; local language per D.1.1 for other grades). Composition per Article D.2 is procedural only.",
    "compositionLimit": null,
    "compositionArticle": "D.2",
    "compositionNote": "The following integration process shall be used when planning for the placement of, and program for, special needs students.",
    "needsReview": true,
    "reviewNote": "No specific hard numerical composition limit found in Article D.2; language is procedural (School Based Team process).",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary Split Class (K, Grade 1,2,3)",
        "value": "Primary Split Class (K, Grade 1,2,3)",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate Split Class (4,5,6,7)",
        "value": "Intermediate Split Class (4,5,6,7)",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary English Class",
        "value": "Secondary English Class",
        "maxSize": 25,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Lab-oriented Science Class",
        "value": "Lab-oriented Science Class",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Workshops",
        "value": "Workshops",
        "maxSize": 20,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Any other class (4-12)",
        "value": "Any other class (4 - 12)",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      }
    ]
  },
  {
    "id": "sd93",
    "sdNumber": 93,
    "name": "SD93 Conseil Scolaire Francophone (SEPF)",
    "localName": "Syndicat des enseignantes et enseignants du programme francophone de la Colombie-Britannique",
    "ratePerMinute": 2.401,
    "notes": "French-language CA (SEPF). Class size limits per Article D.1. Composition per Article D.2.5.b.",
    "compositionLimit": 3,
    "compositionArticle": "D.2.5.b",
    "compositionNote": "A teacher of any regular classroom shall not be required to enrol more than three (3) special needs students. Up to one (1) special needs student with severe behavioural disorder, as defined by the Ministry of Education guidelines, may be among the three (3) students.",
    "needsReview": true,
    "reviewNote": "French-language CA; article numbering differs from English-sector locals. Composition limit is 3 (vs. typical 2). Verify class size and composition language matches current 2022-2025 agreement.",
    "classTypes": [
      {
        "label": "Kindergarten",
        "value": "Kindergarten",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Grade 1, 2, or 3",
        "value": "Grade 1, 2, or 3",
        "maxSize": 22,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary \u2013 Multi-Grade (including Kindergarten)",
        "value": "Primary \u2013 Multi-Grade (including Kindergarten)",
        "maxSize": 20,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Primary \u2013 Multi-Grade (no Kindergarten)",
        "value": "Primary \u2013 Multi-Grade (no Kindergarten)",
        "maxSize": 21,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate \u2013 Single Grade",
        "value": "Intermediate \u2013 Single Grade",
        "maxSize": 29,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate \u2013 Split-Grade",
        "value": "Intermediate \u2013 Split-Grade",
        "maxSize": 27,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Intermediate \u2013 Multi-Grade",
        "value": "Intermediate \u2013 Multi-Grade",
        "maxSize": 25,
        "pValue": 1.0,
        "isSecondary": false
      },
      {
        "label": "Secondary",
        "value": "Secondary",
        "maxSize": 30,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Sciences (Gr. 10-12)",
        "value": "Secondary Sciences (Gr. 10-12)",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "Secondary Language Arts (Francais Language)",
        "value": "Secondary Language Arts (Francais Language)",
        "maxSize": 28,
        "pValue": null,
        "isSecondary": true
      },
      {
        "label": "I.E. Shops and Home Ec. Food Labs",
        "value": "I.E. Shops and Home Ec. Food Labs",
        "maxSize": 24,
        "pValue": null,
        "isSecondary": true
      }
    ]
  }
];

        // -------------------------------------------------------
        // STATE
        // -------------------------------------------------------
        const DEFAULT_RATE = 2.401;
        const monthNames = ['October','November','December','January','February','March','April','May','June'];

        let selectedMonths = [];
        let collapsedYears = {};
        let currentDistrict = null; // the active district object from DISTRICTS_DATA

        // localStorage key is district-scoped so data never bleeds between districts
        function storageKey() {
            return currentDistrict
                ? 'remedyTracker_' + currentDistrict.id
                : 'remedyTracker_default';
        }

        // -------------------------------------------------------
        // DISTRICT SELECTOR INIT
        // -------------------------------------------------------
        function initDistrictSelector() {
            const sel = document.getElementById('districtSelect');
            // Sort by SD number
            const sorted = [...DISTRICTS_DATA].sort((a, b) => a.sdNumber - b.sdNumber);
            sorted.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name;
                sel.appendChild(opt);
            });

            sel.addEventListener('change', () => {
                const chosen = DISTRICTS_DATA.find(d => d.id === sel.value);
                if (chosen) {
                    activateDistrict(chosen);
                } else {
                    deactivateDistrict();
                }
            });
        }

        function activateDistrict(district) {
            currentDistrict = district;

            // Header
            document.getElementById('districtSubtitle').textContent = district.localName;

            // Meta info below selector
            const meta = document.getElementById('districtMeta');
            meta.style.display = 'block';
            document.getElementById('districtMetaName').textContent =
                district.localName + ' — $' + district.ratePerMinute.toFixed(3) + '/min';
            document.getElementById('districtMetaNote').textContent = district.notes || '';

            // Note paragraph
            document.getElementById('mainNote').innerHTML =
                '<strong>Note (' + district.name + '):</strong> V is the value of compensation <em>per month</em>. ' +
                'The dollar value per minute is $' + district.ratePerMinute.toFixed(3) + ' (2025–2026). ' +
                'Use the form below to add remedy entries. Remedy is calculated using the flex factor ' +
                '(remedy starts after two students beyond the class size limit). ' +
                'Minutes and dollars are tracked independently.';

            // Composition reference box — show district's actual CA language
            let compRef = document.getElementById('compositionRef');
            if (!compRef) {
                compRef = document.createElement('div');
                compRef.id = 'compositionRef';
                compRef.className = 'composition-ref';
                // Insert it after the main note
                const mainNote = document.getElementById('mainNote');
                mainNote.parentNode.insertBefore(compRef, mainNote.nextSibling);
            }
            if (district.compositionNote) {
                const art = district.compositionArticle ? 'Article ' + district.compositionArticle + ': ' : '';
                compRef.innerHTML =
                    '<span class="comp-ref-label">📌 Composition limit — ' + district.name + '</span>' +
                    '<span class="comp-ref-text">' + art + district.compositionNote + '</span>';
                compRef.style.display = 'flex';
            } else {
                compRef.style.display = 'none';
            }

            // Populate class type dropdown
            const ctSel = document.getElementById('classType');
            ctSel.innerHTML = '<option value="">Select class type</option>';
            district.classTypes.forEach(ct => {
                const opt = document.createElement('option');
                opt.value = ct.value;
                opt.textContent = ct.label;
                ctSel.appendChild(opt);
            });

            // Show main content
            document.getElementById('mainContent').style.display = 'block';

            // Load district-scoped entries and render
            loadEntries();
            renderEntries();
            renderSummary();

            // Reset form state
            resetForm();
            updateS2Fields();
            updateCourseTypeVisibility();
            updatePValue();
        }

        function deactivateDistrict() {
            currentDistrict = null;
            document.getElementById('districtSubtitle').textContent = 'Select your school district to begin';
            document.getElementById('districtMeta').style.display = 'none';
            document.getElementById('mainContent').style.display = 'none';
        }

        // -------------------------------------------------------
        // MONTH CHIPS
        // -------------------------------------------------------
        function initMonths() {
            const container = document.getElementById('monthsContainer');
            container.innerHTML = '';
            monthNames.forEach((name, idx) => {
                const chip = document.createElement('div');
                chip.className = 'month-chip';
                chip.textContent = name;
                chip.dataset.index = idx;
                chip.addEventListener('click', () => toggleMonth(idx));
                container.appendChild(chip);
            });
        }

        function toggleMonth(index) {
            const chip = document.querySelector(`.month-chip[data-index="${index}"]`);
            if (selectedMonths.includes(index)) {
                selectedMonths = selectedMonths.filter(i => i !== index);
                chip.classList.remove('selected');
            } else {
                selectedMonths.push(index);
                chip.classList.add('selected');
            }
        }

        // -------------------------------------------------------
        // P VALUE AND COURSE TYPE LOGIC
        // (driven entirely by currentDistrict.classTypes)
        // -------------------------------------------------------
        function getClassTypeConfig(value) {
            if (!currentDistrict) return null;
            return currentDistrict.classTypes.find(ct => ct.value === value) || null;
        }

        function updateS2Fields() {
            const container = document.getElementById('s2Container');
            const cats = currentDistrict && currentDistrict.s2Categories;
            const rule = currentDistrict && currentDistrict.s2Rule;
            const cl = currentDistrict && currentDistrict.compositionLimit;
            const art = currentDistrict && currentDistrict.compositionArticle
                ? ` (Article ${currentDistrict.compositionArticle})` : '';
            const hint = cl !== null && cl !== undefined
                ? `Enter the number of designated students in this class <strong>above your district's limit of ${cl}</strong>${art}. If your class has ${cl} or fewer designated students, enter 0.`
                : `Students with designations above your district's composition limit${art}. Check your collective agreement for the specific number, or enter 0 if unsure.`;

            const ruleHTML = rule
                ? `<div class="s2-rule"><strong>CA Rule</strong>${rule}</div>`
                : '';

            const guideHTML = `
                <details class="s2-guide">
                    <summary>Which designations count toward S2?</summary>
                    <div class="s2-guide-body">
                        <p>Count students who push the class over the composition limit and hold one of these Ministry designations:</p>
                        <ul class="s2-cat-list">
                            <li><strong>A</strong> Physically Dependent</li>
                            <li><strong>B</strong> Deafblind</li>
                            <li><strong>C</strong> Moderate to Profound Intellectual Disability</li>
                            <li><strong>D</strong> Physical Disability or Chronic Health Impairment</li>
                            <li><strong>E</strong> Visual Impairment</li>
                            <li><strong>F</strong> Deaf or Hard of Hearing</li>
                            <li><strong>G</strong> Autism Spectrum Disorder <em>*</em></li>
                            <li><strong>H</strong> Intensive Behaviour Interventions or Serious Mental Illness</li>
                        </ul>
                        <div class="s2-gq-note">
                            <strong>G &amp; Q — Jackson Arbitration caveat</strong>
                            Category G (Autism) and Category Q (Learning Disabilities) students only count if they would have met the <em>1995</em> Ministry definitions. Many current G/Q students may not qualify — contact your local for guidance on individual cases.
                        </div>
                    </div>
                </details>`;

            if (cats && cats.length > 1) {
                container.innerHTML = cats.map(cat => `
                    <div class="form-group">
                        <label for="${cat.id}">S2 — ${cat.label}</label>
                        <input type="number" id="${cat.id}" min="0" value="0" />
                        <div class="field-hint">${hint}</div>
                    </div>`).join('') + ruleHTML + guideHTML;
            } else {
                container.innerHTML = `
                    <div class="form-group">
                        <label for="s2">S2 — Extra students with designations</label>
                        <input type="number" id="s2" min="0" value="0" />
                        <div class="field-hint" id="s2Hint">${hint}</div>
                    </div>${ruleHTML}${guideHTML}`;
            }
        }

        function updateClassSizeBadge() {
            const badge = document.getElementById('classSizeBadge');
            const classTypeVal = document.getElementById('classType').value;
            const config = getClassTypeConfig(classTypeVal);

            if (!config || !currentDistrict) {
                badge.classList.remove('visible');
                document.body.classList.remove('cap-bar-visible');
                return;
            }

            document.getElementById('capMaxSize').textContent = config.maxSize;
            document.getElementById('capThreshold').textContent = config.maxSize + 3;

            const compStat = document.getElementById('capCompStat');
            const compVal = document.getElementById('capCompValue');
            const compUnit = document.getElementById('capCompUnit');
            const articleNote = document.getElementById('capArticleNote');

            const cl = currentDistrict.compositionLimit;
            const art = currentDistrict.compositionArticle;
            const note = currentDistrict.compositionNote;

            if (cl !== null && cl !== undefined) {
                compVal.textContent = cl;
                compUnit.textContent = 'designated students per class';
                compStat.style.display = 'flex';
            } else {
                compStat.style.display = 'none';
            }

            const parts = [];
            if (art) parts.push(art);
            if (note) parts.push(note);
            articleNote.textContent = parts.join(' — ');
            articleNote.style.display = parts.length ? 'block' : 'none';

            badge.classList.add('visible');
            document.body.classList.add('cap-bar-visible');
        }

        function updateCourseTypeVisibility() {
            const classTypeVal = document.getElementById('classType').value;
            const config = getClassTypeConfig(classTypeVal);
            const courseTypeGroup = document.getElementById('courseTypeGroup');
            const pHint = document.getElementById('pValueHint');

            if (config && config.isSecondary) {
                courseTypeGroup.style.display = 'block';
                pHint.textContent = 'Pre-filled by course type above. Adjust if your load differs (e.g. 0.286 for a full-time semester course).';
            } else {
                courseTypeGroup.style.display = 'none';
                document.getElementById('courseType').value = '';
                pHint.textContent = '1.0 = full time · 0.8 = 4 days/week · 0.6 = 3 days/week';
            }
            // P is always editable — never disabled
        }

        function updatePValue() {
            const classTypeVal = document.getElementById('classType').value;
            const courseType = document.getElementById('courseType').value;
            const config = getClassTypeConfig(classTypeVal);
            const pInput = document.getElementById('pValue');

            // Only pre-fill if the field is currently empty or was previously pre-filled
            // (don't overwrite a value the user has manually entered)
            if (!config) {
                // No class type selected — clear
                pInput.value = '';
                return;
            }

            if (config.isSecondary) {
                // Pre-fill from course type selection; blank if none chosen yet
                if (courseType === 'Linear') pInput.value = 0.143;
                else if (courseType === 'Semester') pInput.value = 0.286;
                else pInput.value = '';
            } else {
                // Elementary/intermediate — pre-fill with district pValue (usually 1.0)
                // Only set if blank, so a teacher who has typed a custom value isn't overwritten
                // when an unrelated field changes
                if (pInput.value === '') {
                    pInput.value = config.pValue !== null ? config.pValue : 1.0;
                }
            }
        }

        // -------------------------------------------------------
        // FORM SUBMIT
        // -------------------------------------------------------
        document.getElementById('entryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!currentDistrict) return;

            const year = document.getElementById('schoolYear').value;
            const classTypeVal = document.getElementById('classType').value;
            const students = parseInt(document.getElementById('students').value, 10);
            const courseName = document.getElementById('courseName').value.trim();
            const courseType = document.getElementById('courseType').value;
            const cats = currentDistrict.s2Categories;
            let s2 = 0;
            const s2Detail = {};
            if (cats && cats.length > 1) {
                cats.forEach(cat => {
                    const v = parseInt(document.getElementById(cat.id).value || '0', 10);
                    s2Detail[cat.id] = v;
                    s2 += v;
                });
            } else {
                s2 = parseInt(document.getElementById('s2').value || '0', 10);
            }
            const rate = currentDistrict.ratePerMinute || DEFAULT_RATE;

            const config = getClassTypeConfig(classTypeVal);
            if (!config) {
                alert('Please select a valid class type.');
                return;
            }

            // Read P directly from the field — always user-controlled
            const pVal = parseFloat(document.getElementById('pValue').value);
            if (isNaN(pVal) || pVal <= 0 || pVal > 1) {
                alert('Please enter a valid P value between 0.01 and 1.0.');
                return;
            }

            // Secondary classes require a course type for the record, even though P is editable
            if (config.isSecondary && !courseType) {
                alert('Please select Linear or Semester for secondary classes.');
                return;
            }

            if (!year || !classTypeVal || !students || selectedMonths.length === 0) {
                alert('Please fill in all required fields and select at least one month.');
                return;
            }

            selectedMonths.forEach(idx => {
                const monthName = monthNames[idx];
                // Flex factor: remedy starts after two students beyond limit
                const maxSize = config.maxSize + 2;
                let s1 = students - maxSize;
                if (s1 < 0) s1 = 0;
                const totalMin = 180 * pVal * (s1 + s2);
                const totalDollars = totalMin * rate;

                const entry = {
                    id: Date.now() + Math.random(),
                    districtId: currentDistrict.id,
                    year,
                    month: monthName,
                    classType: classTypeVal,
                    courseName: courseName || '',
                    courseType: courseType || '',
                    students,
                    pVal,
                    s1,
                    s2,
                    s2Detail: Object.keys(s2Detail).length ? s2Detail : null,
                    rate,
                    totalMin,
                    totalDollars,
                    minutesUsed: 0,
                    dollarsUsed: 0,
                    remainingMinutes: totalMin,
                    remainingDollars: totalDollars,
                    usageLogs: []
                };
                window.entries.push(entry);
            });

            saveEntries();
            resetForm();
            renderEntries();
            renderSummary();
        });

        function resetForm() {
            document.getElementById('entryForm').reset();
            document.getElementById('pValue').value = '';
            document.getElementById('courseTypeGroup').style.display = 'none';
            document.getElementById('pValueHint').textContent = '1.0 = full time · 0.8 = 4 days/week · 0.6 = 3 days/week';
            document.getElementById('classSizeBadge').classList.remove('visible');
            document.body.classList.remove('cap-bar-visible');
            selectedMonths = [];
            document.querySelectorAll('.month-chip').forEach(ch => ch.classList.remove('selected'));
        }

        // -------------------------------------------------------
        // PERSISTENCE
        // -------------------------------------------------------
        function loadEntries() {
            const data = localStorage.getItem(storageKey());
            try {
                window.entries = data ? JSON.parse(data) : [];
            } catch (e) {
                window.entries = [];
            }
        }

        function saveEntries() {
            localStorage.setItem(storageKey(), JSON.stringify(window.entries));
        }

        // -------------------------------------------------------
        // RENDER ENTRIES
        // -------------------------------------------------------
        function renderEntries() {
            const body = document.getElementById('entriesBody');
            body.innerHTML = '';
            const sortedEntries = [...window.entries].sort((a, b) => {
                if (a.year < b.year) return -1;
                if (a.year > b.year) return 1;
                return monthNames.indexOf(a.month) - monthNames.indexOf(b.month);
            });

            if (sortedEntries.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td colspan="10">
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No entries yet. Add your first remedy entry above.</p>
                    </div>
                </td>`;
                body.appendChild(tr);
                return;
            }

            let currentYear = null;
            sortedEntries.forEach(entry => {
                if (entry.year !== currentYear) {
                    currentYear = entry.year;
                    if (collapsedYears[currentYear] === undefined) {
                        collapsedYears[currentYear] = false;
                    }
                    const yearRow = document.createElement('tr');
                    yearRow.className = 'year-header';
                    const symbol = collapsedYears[currentYear] ? '▶' : '▼';
                    yearRow.innerHTML = `<td colspan="10"><button onclick="toggleYear('${currentYear}')">${symbol}</button>${currentYear}</td>`;
                    body.appendChild(yearRow);
                }
                if (collapsedYears[currentYear]) return;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="rowSelect" value="${entry.id}" /></td>
                    <td>${entry.year}</td>
                    <td>${entry.month}</td>
                    <td>${entry.courseName || '<span style="color:var(--ink-muted)">—</span>'}</td>
                    <td>${entry.classType}</td>
                    <td><strong>${entry.totalMin.toFixed(2)}</strong></td>
                    <td><strong>$${entry.totalDollars.toFixed(2)}</strong></td>
                    <td style="color:${entry.remainingMinutes > 0 ? 'var(--success)' : entry.remainingMinutes < 0 ? 'var(--danger)' : 'var(--ink-muted)'}">${entry.remainingMinutes.toFixed(2)}</td>
                    <td style="color:${entry.remainingDollars > 0 ? 'var(--success)' : entry.remainingDollars < 0 ? 'var(--danger)' : 'var(--ink-muted)'}">$${entry.remainingDollars.toFixed(2)}</td>
                    <td>
                        <button class="btn-secondary btn-sm" onclick="showSpendForm(${entry.id})">Spend</button>
                        <button class="btn-danger btn-sm" onclick="deleteEntry(${entry.id})">Delete</button>
                    </td>
                `;
                body.appendChild(tr);

                const trForm = document.createElement('tr');
                trForm.id = `spendFormRow-${entry.id}`;
                trForm.style.display = 'none';
                const td = document.createElement('td');
                td.colSpan = 10;
                td.innerHTML = `
                    <div class="entry-detail-strip">
                        <span><label>Course Type</label>${entry.courseType || '—'}</span>
                        <span><label>Students</label>${entry.students}</span>
                        <span><label>P Value</label>${entry.pVal.toFixed(3)}</span>
                        <span><label>S1</label>${entry.s1}</span>
                        <span><label>S2</label>${entry.s2Detail
                            ? Object.entries(entry.s2Detail).map(([k,v]) => {
                                const cat = currentDistrict && currentDistrict.s2Categories
                                    ? currentDistrict.s2Categories.find(c => c.id === k) : null;
                                return (cat ? cat.label : k) + ': ' + v;
                              }).join(', ')
                            : entry.s2}</span>
                        <span><label>Min Used</label>${entry.minutesUsed.toFixed(2)}</span>
                        <span><label>$ Used</label>$${entry.dollarsUsed.toFixed(2)}</span>
                    </div>
                    <div class="spend-form">
                        <div class="spend-form-grid">
                            <div class="spend-form-field">
                                <label>Minutes Spent</label>
                                <input type="number" min="0" step="0.01" id="minutesSpent-${entry.id}" placeholder="0" />
                            </div>
                            <div class="spend-form-field">
                                <label>Money Spent ($)</label>
                                <input type="number" min="0" step="0.01" id="moneySpent-${entry.id}" placeholder="0.00" />
                            </div>
                            <div class="spend-form-field" style="flex: 1 1 180px;">
                                <label>Category</label>
                                <select id="category-${entry.id}">
                                    <option value="Additional Prep Time">Additional Prep Time</option>
                                    <option value="Non-Enrolling Staff">Additional Non-Enrolling Staff</option>
                                    <option value="Enrolling Staff">Additional Enrolling Staff</option>
                                    <option value="Pro-D Use">Pro-D Use</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="spend-form-field" style="flex: 2 1 200px;">
                                <label>Note (optional)</label>
                                <input type="text" id="note-${entry.id}" placeholder="Optional description" />
                            </div>
                            <div class="spend-form-field" style="flex: 0 0 auto; justify-content: flex-end;">
                                <label>&nbsp;</label>
                                <button class="btn-primary btn-sm" onclick="recordSpend(${entry.id})">Record</button>
                            </div>
                        </div>
                        <details class="usage-log-details" id="usageLogDetails-${entry.id}" ${entry.usageLogs.length > 0 ? 'open' : ''}>
                            <summary id="usageLogSummary-${entry.id}">Spending History (${entry.usageLogs.length})</summary>
                            <div class="usage-log" id="usageLog-${entry.id}"></div>
                        </details>
                    </div>
                `;
                trForm.appendChild(td);
                body.appendChild(trForm);
                renderUsageLogs(entry);
            });
        }

        function toggleYear(year) {
            collapsedYears[year] = !collapsedYears[year];
            renderEntries();
        }

        // -------------------------------------------------------
        // SPEND / USAGE
        // -------------------------------------------------------
        function showSpendForm(id) {
            const row = document.getElementById(`spendFormRow-${id}`);
            if (!row) return;
            row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
        }

        function recordSpend(id) {
            const entry = window.entries.find(e => e.id === id);
            if (!entry) return;
            const minInput = document.getElementById(`minutesSpent-${id}`);
            const moneyInput = document.getElementById(`moneySpent-${id}`);
            const category = document.getElementById(`category-${id}`).value;
            const note = document.getElementById(`note-${id}`).value;
            const minutesSpent = parseFloat(minInput.value || '0');
            const moneySpent = parseFloat(moneyInput.value || '0');

            if (minutesSpent <= 0 && moneySpent <= 0) {
                alert('Enter minutes or money spent.');
                return;
            }
            const totalRemainingMinutes = window.entries.reduce((sum, e) => sum + e.remainingMinutes, 0);
            const totalRemainingDollars = window.entries.reduce((sum, e) => sum + e.remainingDollars, 0);
            if (minutesSpent > totalRemainingMinutes) {
                alert(`Minutes spent (${minutesSpent.toFixed(2)}) exceeds total remaining minutes across all entries (${totalRemainingMinutes.toFixed(2)}).`);
                return;
            }
            if (moneySpent > totalRemainingDollars) {
                alert(`Money spent ($${moneySpent.toFixed(2)}) exceeds total remaining dollars across all entries ($${totalRemainingDollars.toFixed(2)}).`);
                return;
            }

            const now = new Date().toLocaleString();
            if (minutesSpent > 0) {
                entry.minutesUsed += minutesSpent;
                entry.remainingMinutes = entry.remainingMinutes - minutesSpent;
                entry.usageLogs.push({ date: now, type: 'Minutes', amount: minutesSpent, category, note });
                minInput.value = '';
            }
            if (moneySpent > 0) {
                entry.dollarsUsed += moneySpent;
                entry.remainingDollars = entry.remainingDollars - moneySpent;
                entry.usageLogs.push({ date: now, type: 'Money', amount: moneySpent, category, note });
                moneyInput.value = '';
            }
            document.getElementById(`note-${id}`).value = '';
            saveEntries();
            renderEntries();
            renderSummary();
        }

        function renderUsageLogs(entry) {
            const summaryEl = document.getElementById(`usageLogSummary-${entry.id}`);
            if (summaryEl) summaryEl.textContent = `Spending History (${entry.usageLogs.length})`;
            const details = document.getElementById(`usageLogDetails-${entry.id}`);
            if (details && entry.usageLogs.length > 0) details.open = true;
            const logDiv = document.getElementById(`usageLog-${entry.id}`);
            if (!logDiv) return;
            if (entry.usageLogs.length === 0) {
                logDiv.innerHTML = '<em style="color:var(--ink-muted)">No spending recorded yet.</em>';
                return;
            }
            const list = document.createElement('ul');
            entry.usageLogs.forEach(log => {
                const li = document.createElement('li');
                li.textContent = `${log.date}: ${log.type} — ${log.amount.toFixed(2)} (${log.category}${log.note ? ' · ' + log.note : ''})`;
                list.appendChild(li);
            });
            logDiv.innerHTML = '';
            logDiv.appendChild(list);
        }

        // -------------------------------------------------------
        // DELETE
        // -------------------------------------------------------
        function deleteEntry(id) {
            if (!confirm('Are you sure you want to delete this entry?')) return;
            window.entries = window.entries.filter(e => e.id !== id);
            saveEntries();
            renderEntries();
            renderSummary();
        }

        function toggleSelectAll() {
            const master = document.getElementById('selectAll');
            document.querySelectorAll('.rowSelect').forEach(cb => { cb.checked = master.checked; });
        }

        function deleteSelected() {
            const selectedIds = [];
            document.querySelectorAll('.rowSelect:checked').forEach(cb => {
                selectedIds.push(parseFloat(cb.value));
            });
            if (selectedIds.length === 0) {
                alert('Please select at least one entry to delete.');
                return;
            }
            if (!confirm(`Are you sure you want to delete ${selectedIds.length} selected entr${selectedIds.length === 1 ? 'y' : 'ies'}?`)) return;
            window.entries = window.entries.filter(e => !selectedIds.includes(e.id));
            saveEntries();
            const master = document.getElementById('selectAll');
            if (master) master.checked = false;
            renderEntries();
            renderSummary();
        }

        // -------------------------------------------------------
        // SUMMARY
        // -------------------------------------------------------
        function buildTxnRows(txns) {
            if (txns.length === 0) {
                return `<p class="txn-empty">No spending recorded.</p>`;
            }
            const rows = txns.map(t => `
                <tr>
                    <td>${t.date}</td>
                    <td>${t.entryName}</td>
                    <td>${t.month}</td>
                    <td>${t.category}</td>
                    <td>${t.type}</td>
                    <td><strong>${t.type === 'Money' ? '$' : ''}${t.amount.toFixed(2)}</strong></td>
                    <td class="txn-note">${t.note || '—'}</td>
                </tr>`).join('');
            return `
                <table class="txn-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entry Name</th>
                            <th>Month</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>`;
        }

        function renderSummary() {
            const summaryBody = document.getElementById('summaryBody');
            summaryBody.innerHTML = '';
            const totalsByYear = {};
            const txnsByYear = {};
            const allTxns = [];

            window.entries.forEach(entry => {
                if (!totalsByYear[entry.year]) {
                    totalsByYear[entry.year] = { totalMin:0, totalDollars:0, minUsed:0, dollarsUsed:0, minRem:0, dollarsRem:0 };
                    txnsByYear[entry.year] = [];
                }
                const obj = totalsByYear[entry.year];
                obj.totalMin += entry.totalMin;
                obj.totalDollars += entry.totalDollars;
                obj.minUsed += entry.minutesUsed;
                obj.dollarsUsed += entry.dollarsUsed;
                obj.minRem += entry.remainingMinutes;
                obj.dollarsRem += entry.remainingDollars;

                entry.usageLogs.forEach(log => {
                    const txn = { ...log, entryName: entry.courseName || '—', month: entry.month };
                    txnsByYear[entry.year].push(txn);
                    allTxns.push(txn);
                });
            });

            Object.keys(totalsByYear).sort().forEach(year => {
                const row = totalsByYear[year];
                const txns = txnsByYear[year];
                const remColor = row.minRem >= 0 ? 'var(--success)' : 'var(--danger)';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${year}</td>
                    <td>${row.totalMin.toFixed(2)}</td>
                    <td>$${row.totalDollars.toFixed(2)}</td>
                    <td>${row.minUsed.toFixed(2)}</td>
                    <td>$${row.dollarsUsed.toFixed(2)}</td>
                    <td style="color:${remColor}; font-weight:600;">${row.minRem.toFixed(2)}</td>
                    <td style="color:${remColor}; font-weight:600;">$${row.dollarsRem.toFixed(2)}</td>
                `;
                summaryBody.appendChild(tr);

                const txnTr = document.createElement('tr');
                const txnTd = document.createElement('td');
                txnTd.colSpan = 7;
                txnTd.style.padding = '0';
                txnTd.style.background = 'var(--row-alt)';
                txnTd.innerHTML = `
                    <details class="txn-history">
                        <summary>Transaction History (${txns.length})</summary>
                        ${buildTxnRows(txns)}
                    </details>`;
                txnTr.appendChild(txnTd);
                summaryBody.appendChild(txnTr);
            });

            if (Object.keys(totalsByYear).length > 1) {
                const all = { totalMin:0, totalDollars:0, minUsed:0, dollarsUsed:0, minRem:0, dollarsRem:0 };
                Object.values(totalsByYear).forEach(r => {
                    all.totalMin += r.totalMin;
                    all.totalDollars += r.totalDollars;
                    all.minUsed += r.minUsed;
                    all.dollarsUsed += r.dollarsUsed;
                    all.minRem += r.minRem;
                    all.dollarsRem += r.dollarsRem;
                });
                const remColor = all.minRem >= 0 ? 'var(--success)' : 'var(--danger)';
                const tr = document.createElement('tr');
                tr.style.cssText = 'font-weight:700; border-top: 2px solid var(--border-strong);';
                tr.innerHTML = `
                    <td>All Years</td>
                    <td>${all.totalMin.toFixed(2)}</td>
                    <td>$${all.totalDollars.toFixed(2)}</td>
                    <td>${all.minUsed.toFixed(2)}</td>
                    <td>$${all.dollarsUsed.toFixed(2)}</td>
                    <td style="color:${remColor};">${all.minRem.toFixed(2)}</td>
                    <td style="color:${remColor};">$${all.dollarsRem.toFixed(2)}</td>
                `;
                summaryBody.appendChild(tr);

                const txnTr = document.createElement('tr');
                const txnTd = document.createElement('td');
                txnTd.colSpan = 7;
                txnTd.style.padding = '0';
                txnTd.style.background = 'var(--row-alt)';
                txnTd.innerHTML = `
                    <details class="txn-history">
                        <summary>All Transactions (${allTxns.length})</summary>
                        ${buildTxnRows(allTxns)}
                    </details>`;
                txnTr.appendChild(txnTd);
                summaryBody.appendChild(txnTr);
            }
        }

        // -------------------------------------------------------
        // CSV EXPORT
        // -------------------------------------------------------
        function csvEscape(value) {
            const str = (value ?? '').toString();
            if (/[",\n]/.test(str)) return '"' + str.replace(/"/g, '""') + '"';
            return str;
        }

        function downloadCSV() {
            const districtLabel = currentDistrict ? currentDistrict.name : 'Unknown';
            const headers = ['District','Year','Month','Course/Class Name','Class Type','Course Type','Students','P','S1','S2','Total Minutes','Total Dollars','Minutes Used','Dollars Used','Minutes Remaining','Dollars Remaining','Usage Logs'];
            const rows = window.entries.map(entry => {
                const usageString = entry.usageLogs.map(log => {
                    const notePart = log.note ? ' - ' + log.note : '';
                    return `${log.date}: ${log.type} ${log.amount.toFixed(2)} (${log.category}${notePart})`;
                }).join(' | ');
                return [
                    districtLabel,
                    entry.year,
                    entry.month,
                    entry.courseName || '',
                    entry.classType,
                    entry.courseType || '',
                    entry.students,
                    entry.pVal.toFixed(3),
                    entry.s1,
                    entry.s2,
                    entry.totalMin.toFixed(2),
                    entry.totalDollars.toFixed(2),
                    entry.minutesUsed.toFixed(2),
                    entry.dollarsUsed.toFixed(2),
                    entry.remainingMinutes.toFixed(2),
                    entry.remainingDollars.toFixed(2),
                    usageString
                ].map(csvEscape).join(',');
            });
            const csvContent = [headers.map(csvEscape).join(','), ...rows].join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const filename = currentDistrict
                ? 'remedy-data-' + currentDistrict.id + '.csv'
                : 'remedy-data.csv';
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        // -------------------------------------------------------
        // INIT
        // -------------------------------------------------------
        document.addEventListener('DOMContentLoaded', () => {
            initMonths();
            initDistrictSelector();
            window.entries = [];

            document.getElementById('selectOctJun').addEventListener('click', () => {
                const allSelected = (selectedMonths.length === monthNames.length);
                selectedMonths = [];
                document.querySelectorAll('.month-chip').forEach(ch => ch.classList.remove('selected'));
                if (!allSelected) {
                    for (let i = 0; i < monthNames.length; i++) {
                        selectedMonths.push(i);
                        document.querySelector(`.month-chip[data-index="${i}"]`).classList.add('selected');
                    }
                }
            });

            document.getElementById('classType').addEventListener('change', () => {
                // Clear P when class type changes so pre-fill fires fresh
                document.getElementById('pValue').value = '';
                updateCourseTypeVisibility();
                updatePValue();
                updateClassSizeBadge();
            });

            document.getElementById('courseType').addEventListener('change', () => {
                updatePValue();
            });
        });
      </script>
  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
